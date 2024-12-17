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

            // Check event participation status
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
                    $userEventStatus = 2; // Already left
                }
            } else {
                $userEventStatus = 0; // Not registered
            }
        } else {
            $searchError = "No user found with the provided name.";
        }
        $stmt->close();
    }
}

// Handle AJAX requests for confirming user attendance
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirmUser'])) {
    $selectedUserId = $_POST['userId'];
    $action = $_POST['action'];
    $currentTime = date("Y-m-d H:i:s");
    $response = ["status" => "error", "message" => ""];

    if ($action === "join") {
        // Update join time
        $stmt = $conn->prepare("INSERT INTO event_participants (uid, eventid, join_time) VALUES (?, ?, ?) 
                                ON DUPLICATE KEY UPDATE join_time = VALUES(join_time), leave_time = NULL");
        $stmt->bind_param("iis", $selectedUserId, $eventid, $currentTime);
        $stmt->execute();

        $token = bin2hex(random_bytes(32));
        $stmt2 = $conn->prepare("UPDATE user_credentials SET currboundtoken = ? WHERE uid = ?");
        $stmt2->bind_param("si", $token, $selectedUserId);
        $stmt2->execute();

        $response["status"] = "success";
        $response["token"] = $token;
        $response["message"] = "User has successfully joined the event.";
    } elseif ($action === "leave") {
        // Update leave time
        $stmt = $conn->prepare("UPDATE event_participants SET leave_time = ? WHERE uid = ? AND eventid = ?");
        $stmt->bind_param("sii", $currentTime, $selectedUserId, $eventid);
        $stmt->execute();

        $response["status"] = "success";
        $response["message"] = "User has successfully left the event.";
    }

    echo json_encode($response);
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $("form#confirmUserForm").on("submit", function(event) {
                event.preventDefault();

                var userId = $("input[name='userId']").val();
                var action = $("input[name='action']").val();

                $.ajax({
                    url: "",
                    method: "POST",
                    data: {
                        confirmUser: true,
                        userId: userId,
                        action: action
                    },
                    success: function(response) {
                        var data = JSON.parse(response);

                        if (data.status === "success") {
                            if (action === "join") {
                                var token = data.token;
                                var qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=https://accounts.dcism.org/accountRegistration/ingress.php?userid=' + userId + '&token=' + token;
                                $('#qrCodeModal img').attr('src', qrCodeUrl);
                                $('#qrCodeModal').modal('show');
                            }
                            alert(data.message);
                            location.reload();
                        } else {
                            alert(data.message);
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
                <div class="col-md-8 col-lg-6">
                    <div class="card bg-dark text-white">
                        <div class="card-body text-center">
                            <h2 class="fw-bold mb-4">Search User</h2>

                            <!-- Search Form -->
                            <form method="POST">
                                <div class="mb-4">
                                    <input type="text" name="searchName" class="form-control" placeholder="Enter First and Last Name" required>
                                </div>
                                <button class="btn btn-primary" type="submit">Search</button>
                            </form>

                            <!-- Error -->
                            <?php if ($searchError): ?>
                                <div class="alert alert-danger mt-3"><?php echo $searchError; ?></div>
                            <?php endif; ?>

                            <!-- Display User Details -->
                            <?php if ($userDetails): ?>
                                <div class="mt-4">
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($userDetails['fname'] . " " . $userDetails['lname']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($userDetails['email']); ?></p>

                                    <form id="confirmUserForm">
                                        <input type="hidden" name="userId" value="<?php echo $userDetails['uid']; ?>">
                                        <?php if ($userEventStatus === 0): ?>
                                            <input type="hidden" name="action" value="join">
                                            <button class="btn btn-success" type="submit">Join Event</button>
                                        <?php elseif ($userEventStatus === 1): ?>
                                            <input type="hidden" name="action" value="leave">
                                            <button class="btn btn-danger" type="submit">Leave Event</button>
                                        <?php elseif ($userEventStatus === 2): ?>
                                            <button class="btn btn-secondary" disabled>Already Left</button>
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

    <!-- QR Code Modal -->
    <div class="modal fade" id="qrCodeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Event QR Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <img src="" alt="QR Code" class="img-fluid">
                </div>
            </div>
        </div>
    </div>
</body>
</html>

