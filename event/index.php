<?php
require "../db.php"; // Database connection file
session_start();

// Initialize variables
$searchError = "";
$userDetails = null;
$selectedUserId = null;
$localToken = "";
$userEventStatus = null;

$eventid = $_SESSION['eventid'];

// Handle the search query
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['searchName'])) {
    $searchInput = trim($_POST['searchName']);
    $names = explode(" ", $searchInput);

    if (count($names) < 2) {
        $searchError = "Please enter both first and last name.";
    } else {
        $fname = $names[0];
        $lname = $names[1];

        // Fetch user details
        $stmt = $conn->prepare("SELECT uid, fname, lname, email, currboundtoken FROM user_credentials WHERE fname = ? AND lname = ?");
        $stmt->bind_param("ss", $fname, $lname);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $userDetails = $result->fetch_assoc();
            $selectedUserId = $userDetails['uid'];

            // Check participation status
            $stmt2 = $conn->prepare("SELECT join_time, leave_time FROM event_participants WHERE uid = ? AND eventid = ?");
            $stmt2->bind_param("ii", $selectedUserId, $eventid);
            $stmt2->execute();
            $eventResult = $stmt2->get_result();

            if ($eventResult && $eventResult->num_rows > 0) {
                $eventRow = $eventResult->fetch_assoc();

                if (is_null($eventRow['join_time']) && is_null($eventRow['leave_time'])) {
                    $userEventStatus = 0; // Not joined
                } elseif (!is_null($eventRow['join_time']) && is_null($eventRow['leave_time'])) {
                    $userEventStatus = 1; // Currently attending
                } elseif (!is_null($eventRow['join_time']) && !is_null($eventRow['leave_time'])) {
                    $userEventStatus = 2; // Fully attended and left
                }
            } else {
                $userEventStatus = 0; // Not registered
            }
        } else {
            $searchError = "No user found with the provided name.";
        }
    }
}

// Handle Join/Leave Event and generate QR code
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirmUser'])) {
    $selectedUserId = $_POST['userId'];
    $currentTime = date("Y-m-d H:i:s");

    // Generate token and update user state
    $stmt = $conn->prepare("SELECT creationtime, currboundtoken FROM user_credentials WHERE uid = ?");
    $stmt->bind_param("i", $selectedUserId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        $creationTime = $row['creationtime'];
        $currToken = $row['currboundtoken'];

        // Generate new token if older than 10 minutes or if no token exists
        if (is_null($creationTime) || strtotime($creationTime) < (strtotime($currentTime) - 600)) {
            $token = bin2hex(random_bytes(32));
            $updateStmt = $conn->prepare("UPDATE user_credentials SET creationtime = ?, currboundtoken = ? WHERE uid = ?");
            $updateStmt->bind_param("ssi", $currentTime, $token, $selectedUserId);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            $token = $currToken;
        }

        $localToken = $token;

        // Update event participation status
        if ($_POST['action'] === 'join') {
            $stmt = $conn->prepare("INSERT INTO event_participants (uid, eventid, join_time) VALUES (?, ?, ?)
                                    ON DUPLICATE KEY UPDATE join_time = ?");
            $stmt->bind_param("iiss", $selectedUserId, $eventid, $currentTime, $currentTime);
            $stmt->execute();
        } elseif ($_POST['action'] === 'leave') {
            $stmt = $conn->prepare("UPDATE event_participants SET leave_time = ? WHERE uid = ? AND eventid = ?");
            $stmt->bind_param("sii", $currentTime, $selectedUserId, $eventid);
            $stmt->execute();
        }
    }
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
            var action = $("input[name='action']").val();

            $.post("", { confirmUser: true, userId: userId, action: action }, function(response) {
                var qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=https://accounts.dcism.org/accountRegistration/ingress.php?userid=' + userId + '&token=<?php echo $localToken; ?>';
                $('#qrCodeModal img').attr('src', qrCodeUrl);
                $('#qrCodeModal').modal('show');
            });
        });
    });
    </script>
</head>
<body>
<section class="vh-100 gradient-custom">
    <div class="container py-5 h-100">
        <div class="row d-flex justify-content-center align-items-center h-100">
            <div class="col-md-8 col-lg-6 col-xl-5">
                <div class="card bg-dark text-white" style="border-radius: 1rem;">
                    <div class="card-body p-5 text-center">
                        <h2 class="fw-bold mb-4">Search User</h2>
                        <form method="POST" action="">
                            <input type="text" name="searchName" class="form-control mb-4" placeholder="Enter First and Last Name" required />
                            <button class="btn btn-light btn-lg" type="submit">Search</button>
                        </form>

                        <?php if ($searchError): ?>
                            <div class="alert alert-danger mt-3"><?php echo $searchError; ?></div>
                        <?php endif; ?>

                        <?php if ($userDetails): ?>
                            <div class="mt-4">
                                <h4>User Found:</h4>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($userDetails['fname'] . " " . $userDetails['lname']); ?></p>
                                <form id="confirmUserForm">
                                    <input type="hidden" name="userId" value="<?php echo $userDetails['uid']; ?>" />
                                    <?php if ($userEventStatus === 0): ?>
                                        <input type="hidden" name="action" value="join" />
                                        <button class="btn btn-primary" type="submit">Join Event</button>
                                    <?php elseif ($userEventStatus === 1): ?>
                                        <input type="hidden" name="action" value="leave" />
                                        <button class="btn btn-danger" type="submit">Leave Event</button>
                                    <?php elseif ($userEventStatus === 2): ?>
                                        <button class="btn btn-secondary" disabled>Already Attended</button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="qrCodeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Event QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <img src="" alt="QR Code" class="img-fluid" />
            </div>
        </div>
    </div>
</div>
</body>
</html>

