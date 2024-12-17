<?php
require "../db.php"; // Database connection file

// Initialize variables
$searchError = "";
$userDetails = null; // To store the queried user details
$selectedUserId = null;
$localToken = ""; // To store the final token
$userEventStatus = null; // To save attendance status

$eventid = $_SESSION['eventid']; 

// Handle the search query
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['searchName'])) {
    // Sanitize user input
    $searchInput = trim($_POST['searchName']);
    $names = explode(" ", $searchInput);

    // Ensure at least first and last name are provided
    if (count($names) < 2) {
        $searchError = "Please enter both first and last name.";
    } else {
        $fname = $names[0];
        $lname = $names[1];

        // Search for the user in the database
        $stmt = $conn->prepare("SELECT uid, fname, lname, email FROM user_credentials WHERE fname = ? AND lname = ?");
        $stmt->bind_param("ss", $fname, $lname);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $userDetails = $result->fetch_assoc();
            $selectedUserId = $userDetails['uid'];  // Store selected user ID
            
            // Query to check event participation status
            $stmt2 = $conn->prepare("SELECT join_time, leave_time FROM event_participants WHERE uid = ? AND eventid = ?");
            $stmt2->bind_param("ii", $selectedUserId, $eventid);
            $stmt2->execute();
            $eventResult = $stmt2->get_result();
            
            if ($eventResult && $eventResult->num_rows > 0) {
                $eventRow = $eventResult->fetch_assoc();
                
                // Determine the user's event status
                if (is_null($eventRow['join_time']) && is_null($eventRow['leave_time'])) {
                    $userEventStatus = 0; // Not joined or attended
                } elseif (!is_null($eventRow['join_time']) && is_null($eventRow['leave_time'])) {
                    $userEventStatus = 1; // Currently attending
                } elseif (!is_null($eventRow['join_time']) && !is_null($eventRow['leave_time'])) {
                    $userEventStatus = 2; // Fully attended and left
                }
            } else {
                $userEventStatus = 0; // User has not registered for the event
            }
        } else {
            $searchError = "No user found with the provided name.";
        }
        $stmt->close();
    }
}

// Handle confirmation of user details and attendance logic
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirmUser'])) {
    $selectedUserId = $_POST['userId'];
    $token = "";
    $currentTime = date("Y-m-d H:i:s");

    // Query to get the user's creationtime and currboundtoken
    $stmt = $conn->prepare("SELECT creationtime, currboundtoken FROM user_credentials WHERE uid = ?");
    $stmt->bind_param("i", $selectedUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        $creationTime = $row['creationtime'];
        $currToken = $row['currboundtoken'];

        if (is_null($creationTime)) {
            // If creationtime is NULL, insert current time and generate new token
            $token = bin2hex(random_bytes(32));
            $updateStmt = $conn->prepare("UPDATE user_credentials SET creationtime = ?, currboundtoken = ? WHERE uid = ?");
            $updateStmt->bind_param("ssi", $currentTime, $token, $selectedUserId);
            $updateStmt->execute();
            $updateStmt->close();

            echo "New token generated as creation time was empty.";
        } else {
            // Check if the creationtime is older than 10 minutes
            $tenMinutesAgo = strtotime($currentTime) - 600; // 600 seconds = 10 minutes
            $creationTimestamp = strtotime($creationTime);

            if ($creationTimestamp < $tenMinutesAgo) {
                // Update creationtime and currboundtoken
                $token = bin2hex(random_bytes(32));
                $updateStmt = $conn->prepare("UPDATE user_credentials SET creationtime = ?, currboundtoken = ? WHERE uid = ?");
                $updateStmt->bind_param("ssi", $currentTime, $token, $selectedUserId);
                $updateStmt->execute();
                $updateStmt->close();

                echo "Token updated as creation time exceeded 10 minutes.";
            } else {
                // Use existing token
                $token = $currToken;
                echo "Token reused: " . htmlspecialchars($token);
            }
        }
    } else {
        echo "User not found.";
    }
    $stmt->close();

    // Save token into a local variable for further processing
    $localToken = $token;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search User</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="../style.css" rel="stylesheet">
</head>
<body>
    <section class="vh-100 gradient-custom">
        <div class="container py-5 h-100">
            <div class="row d-flex justify-content-center align-items-center h-100">
                <div class="col-12 col-md-8 col-lg-6 col-xl-5">
                    <div class="card bg-dark text-white" style="border-radius: 1rem;">
                        <div class="card-body p-5 text-center">
                            <h2 class="fw-bold mb-4 text-uppercase">Search User</h2>
                            
                            <!-- Search Form -->
                            <form id="searchForm" method="POST" action="">
                                <div class="form-outline form-white mb-4">
                                    <input type="text" name="searchName" id="searchName" class="form-control form-control-lg" placeholder="Enter First and Last Name" autocomplete="off" required />
                                </div>
                                <button class="btn btn-outline-light btn-lg px-5" type="submit">Search</button>
                            </form>

                            <!-- Error Message -->
                            <?php if ($searchError): ?>
                                <div class="alert alert-danger mt-3"><?php echo $searchError; ?></div>
                            <?php endif; ?>

                            <!-- Display User Details -->
                            <?php if ($userDetails): ?>
                                <div class="mt-4">
                                    <h4>Is This You?:</h4>
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($userDetails['fname'] . " " . $userDetails['lname']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($userDetails['email']); ?></p>
                                    
                                    <!-- Confirmation Form -->
                                    <form method="POST" action="">
                                        <input type="hidden" name="userId" value="<?php echo $userDetails['uid']; ?>" />
                                        <button class="btn btn-primary" type="submit" name="confirmUser">Confirm</button>
                                    </form>
                                    
                                    <!-- QR Code Modal Trigger -->
                                    <?php if (!empty($localToken)): ?>
                                        <button type="button" class="btn btn-info mt-3" data-bs-toggle="modal" data-bs-target="#qrCodeModal">
                                            Show QR Code
                                        </button>

                                        <!-- QR Code Modal -->
                                        <div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="qrCodeModalLabel">Your QR Code</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <img id="qrCodeImage" src="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=https://accounts.dcism.org/accountRegistration/ingress.php?userid=<?php echo $selectedUserId; ?>&token=<?php echo $localToken; ?>&format=svg" alt="QR Code" style="width: 100%; height: auto;">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prevent form submission and refresh
        document.getElementById("searchForm").addEventListener("submit", function(event) {
            event.preventDefault();

            // Get user input
            var searchInput = document.getElementById("searchName").value;

            // Process search functionality with AJAX (or reload page)
            window.location.href = "searchPage.php?searchName=" + encodeURIComponent(searchInput); // Adjust according to your logic
        });
    </script>
</body>
</html>

