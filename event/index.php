<?php
require "../db.php"; // Database connection file

// Start output buffering to prevent unwanted output
ob_start();
session_start();

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

    if (count($names) < 2) {
        $searchError = "Please enter both first and last name.";
    } else {
        $fname = $names[0];
        $lname = $names[1];

        // Search for the user in the database
        $stmt = $conn->prepare("SELECT uid, fname, lname, email, currboundtoken FROM user_credentials WHERE fname = ? AND lname = ?");
        $stmt->bind_param("ss", $fname, $lname);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $userDetails = $result->fetch_assoc();
            $selectedUserId = $userDetails['uid'];

            // Query to check event participation status
            $stmt2 = $conn->prepare("SELECT join_time, leave_time FROM event_participants WHERE uid = ? AND eventid = ?");
            $stmt2->bind_param("ii", $selectedUserId, $eventid);
            $stmt2->execute();
            $eventResult = $stmt2->get_result();

            if ($eventResult && $eventResult->num_rows > 0) {
                $eventRow = $eventResult->fetch_assoc();

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

// Handle confirmation of user details and token logic
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirmUser'])) {
    header('Content-Type: application/json'); // Set response type to JSON

    $selectedUserId = $_POST['userId'];
    $token = "";
    $currentTime = date("Y-m-d H:i:s");

    $stmt = $conn->prepare("SELECT creationtime, currboundtoken FROM user_credentials WHERE uid = ?");
    $stmt->bind_param("i", $selectedUserId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        $creationTime = $row['creationtime'];
        $currToken = $row['currboundtoken'];

        if (is_null($creationTime)) {
            $token = bin2hex(random_bytes(32));
            $updateStmt = $conn->prepare("UPDATE user_credentials SET creationtime = ?, currboundtoken = ? WHERE uid = ?");
            $updateStmt->bind_param("ssi", $currentTime, $token, $selectedUserId);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            $tenMinutesAgo = strtotime($currentTime) - 600;
            $creationTimestamp = strtotime($creationTime);

            if ($creationTimestamp < $tenMinutesAgo) {
                $token = bin2hex(random_bytes(32));
                $updateStmt = $conn->prepare("UPDATE user_credentials SET creationtime = ?, currboundtoken = ? WHERE uid = ?");
                $updateStmt->bind_param("ssi", $currentTime, $token, $selectedUserId);
                $updateStmt->execute();
                $updateStmt->close();
            } else {
                $token = $currToken;
            }
        }
        echo json_encode(['token' => $token]);
    } else {
        echo json_encode(['error' => 'User not found.']);
    }
    exit;
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

    <script>
    $(document).ready(function() {
        $("form#confirmUserForm").on("submit", function(event) {
            event.preventDefault();
            var userId = $("input[name='userId']").val();

            $.ajax({
                url: "",
                method: "POST",
                data: {
                    confirmUser: true,
                    userId: userId
                },
                success: function(response) {
                    if (response.error) {
                        alert(response.error);
                    } else {
                        var token = response.token;
                        var qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=https://accounts.dcism.org/accountRegistration/ingress.php?userid=' + userId + '&token=' + token;

                        $('#qrCodeModal img').attr('src', qrCodeUrl);
                        $('#qrCodeModal').modal('show');
                    }
                }
            });
        });
    });
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
                                    <input type="text" name="searchName" class="form-control form-control-lg" placeholder="Enter First and Last Name" required />
                                </div>
                                <button class="btn btn-outline-light btn-lg px-5" type="submit">Search</button>
                            </form>

                            <!-- Error Message -->
                            <?php if ($searchError): ?>
                                <div class="alert alert-danger mt-3"><?php echo $searchError; ?></div>
                            <?php endif; ?>

                            <!-- User Details -->
                            <?php if ($userDetails): ?>
                                <div class="mt-4">
                                    <h4>Is This You?</h4>
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($userDetails['fname'] . " " . $userDetails['lname']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($userDetails['email']); ?></p>

                                    <form id="confirmUserForm">
                                        <input type="hidden" name="userId" value="<?php echo $userDetails['uid']; ?>" />
                                        <button class="btn btn-primary" type="submit">Generate QR Code</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- QR Code Modal -->
    <div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qrCodeModalLabel">Your Event QR Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" alt="QR Code" class="img-fluid"/>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

