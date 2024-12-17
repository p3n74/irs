<?php

require "db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $eventKey = $_POST['eventKey'];

    // Fetch event start and end dates
    $query = "SELECT eventid, startdate, enddate FROM events WHERE eventkey = '$eventKey'";
    $result = $conn->query($query);

    if ($result) {
        if ($row = $result->fetch_assoc()) {
            $currentTime = date("Y-m-d H:i:s"); // Current time
            $startdate = $row['startdate'];
            $enddate = $row['enddate'];

            // Check if current time is within start and end dates
            if ($currentTime >= $startdate && $currentTime <= $enddate) {
                $_SESSION['eventid'] = $row['eventid'];
                $_SESSION['eventKey'] = $eventKey;
                header("Location: event/");
                exit();
            } else {
                $error = "This event is not accessible at this time. Please check the event schedule.";
            }
        } else {
            $error = "Invalid event key. Please try again.";
        }
    } else {
        $error = "Error executing query: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registration</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .gradient-custom {
            background: linear-gradient(to bottom right, #6a11cb, #2575fc);
        }
        .card {
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .error-msg {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 0.5rem;
            padding: 10px;
            margin-top: 10px;
            font-size: 0.9rem;
        }
        .form-label {
            font-size: 1rem;
            font-weight: bold;
            color: #fff;
        }
        .btn-submit {
            background-color: #007bff;
            border: none;
            transition: 0.3s;
        }
        .btn-submit:hover {
            background-color: #0056b3;
        }
    </style>
</head>

<body>
    <section class="vh-100 gradient-custom">
        <div class="container py-5 h-100">
            <div class="row d-flex justify-content-center align-items-center h-100">
                <div class="col-12 col-md-8 col-lg-6 col-xl-5">
                    <div class="card bg-dark text-white" style="border-radius: 1rem;">
                        <div class="card-body p-5 text-center">
                            <div class="mb-md-5 mt-md-4 pb-5">
                                <h2 class="fw-bold mb-4 text-uppercase">Event Registration</h2>
                                <p class="text-white-50 mb-4">Enter your event key to continue.</p>
                                <form action="" method="POST">
                                    <div class="form-outline form-white mb-4">
                                        <input type="password" id="eventKey" name="eventKey" autocomplete="off"
                                            class="form-control form-control-lg" required />
                                        <label class="form-label" for="eventKey">Event Key</label>
                                    </div>
                                    <?php if ($error) { ?>
                                        <div class="error-msg">
                                            <i class="fas fa-exclamation-circle me-2"></i>
                                            <?php echo htmlspecialchars($error); ?>
                                        </div>
                                    <?php } ?>
                                    <button class="btn btn-submit btn-lg px-5 mt-3" type="submit">Enter</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>

