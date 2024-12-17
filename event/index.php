<?php

require "../db.php";

// Check if eventid is set in the session
if (isset($_SESSION['eventid'])) {
    $eventid = $_SESSION['eventid'];
} else {
    // Redirect back if eventid is not set
    header("Location: ../");
    exit();
}

echo "Event ID: " . $eventid;

$url = "https://your-website.com/event.php?eventid=" . urlencode($eventid);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan</title>
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
                            <div class="mb-md-3 mt-md-4 pb-xs-5">
                                <h2 class="fw-bold mb-2 text-uppercase">Scan</h2>
                                <p class="text-white-50 mb-3">Scan here to register!</p>
                                <div class="row no-gutters">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=<?php echo urlencode($url); ?>&format=svg"
                                        alt="QR Code" style="height: auto; width: 400px; margin-left: auto; margin-right: auto; padding: 30px; background-color: white;">
                                </div>
                            </div>
                            <div>
                                <p class="mb-0">Do you want to enter another event key? <a href=".."
                                        class="text-white-50 fw-bold">Click Here</a>
                                </p>
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
