<?php
require "../db.php"; // Database connection file

// Initialize variables
$searchError = "";
$userDetails = null; // To store the queried user details
$selectedUserId = null;
$localToken = ""; // To store the final token
$userEventStatus = null; // Save attendance status

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
        $stmt = $conn->prepare("SELECT uid, fname, lname, email, currboundtoken, creationtime FROM user_credentials WHERE fname = ? AND lname = ?");
        $stmt->bind_param("ss", $fname, $lname);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $userDetails = $result->fetch_assoc();
            $selectedUserId = $userDetails['uid'];  // Store selected user ID
            
            // Check if the token is outdated (more than 10 minutes old)
            $currentTime = date("Y-m-d H:i:s");
            $creationTime = $userDetails['creationtime'];
            
            if ($creationTime) {
                $tenMinutesAgo = strtotime($currentTime) - 600; // 600 seconds = 10 minutes
                $creationTimestamp = strtotime($creationTime);

                if ($creationTimestamp < $tenMinutesAgo) {
                    // Token is outdated, generate a new one
                    $newToken = bin2hex(random_bytes(32));
                    $updateStmt = $conn->prepare("UPDATE user_credentials SET creationtime = ?, currboundtoken = ? WHERE uid = ?");
                    $updateStmt->bind_param("ssi", $currentTime, $newToken, $selectedUserId);
                    $updateStmt->execute();
                    $updateStmt->close();

                    // Fetch the new token
                    $userDetails['currboundtoken'] = $newToken; // Update local userDetails with the new token
                    echo "Token updated as creation time exceeded 10 minutes.";
                } else {
                    // Use existing token
                    echo "Token reused: " . htmlspecialchars($userDetails['currboundtoken']);
                }
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

    // Save the updated or existing token into a variable for the QR code
    if (isset($userDetails['currboundtoken'])) {
        $token = $userDetails['currboundtoken'];
    }

    // Re-run the query to fetch the updated token after the update if needed
    $stmt = $conn->prepare("SELECT currboundtoken FROM user_credentials WHERE uid = ?");
    $stmt->bind_param("i", $selectedUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        $token = $row['currboundtoken'];  // Fetch the updated token
    }
    $stmt->close();

    // Embed the token into the QR code generation
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

    <!-- Include the necessary jQuery and Bootstrap JS for modal -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

    <script>
        // Function to open the modal with the QR code
        function showQRCode(event, userId, token) {
            event.preventDefault(); // Prevent page refresh on form submission

            var qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=https://accounts.dcism.org/accountRegistration/ingress.php?token=" + token + "&format=svg";
            $('#qrCodeModal img').attr('src', qrCodeUrl);
            $('#qrCodeModal').modal('show');
        }
    </script>
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
                            <form method="POST" action="">
                                <div class="form-outline form-white mb-4">
                                    <input type="text" name="searchName" class="form-control form-control-lg" placeholder="Enter First and Last Name" autocomplete="off" required />
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

                                        <!-- Button logic based on user event status -->
                                        <?php if ($userEventStatus === 0): ?>
                                            <button class="btn btn-primary" type="submit" name="confirmUser" onclick="showQRCode(event, <?php echo $userDetails['uid']; ?>, '<?php echo $localToken; ?>')">Join Event</button>
                                        <?php elseif ($userEventStatus === 1): ?>
                                            <button class="btn btn-danger" type="submit" name="confirmUser" onclick="showQRCode(event, <?php echo $userDetails['uid']; ?>, '<?php echo $localToken; ?>')">Leave Event</button>
                                        <?php elseif ($userEventStatus === 2): ?>
                                            <button class="btn btn-secondary" type="button" disabled>You have already attended</button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <!-- Display Local Token if available -->
                            <?php if (!empty($localToken)): ?>
                                <div class="alert alert-info mt-4">
                                    <strong>Token:</strong> <?php echo htmlspecialchars($localToken); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Modal for QR Code -->
    <div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qrCodeModalLabel">QR Code for Registration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <img src="" alt="QR Code" class="img-fluid" />
                </div>
            </div>
        </div>
    </div>
</body>
</html>

