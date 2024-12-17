<?php


require "db.php";

$error = "";


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $eventKey = $_POST['eventKey'];

    $query = "SELECT eventid FROM events WHERE eventkey = '$eventKey'";
    $result = $conn->query($query);

    if ($result) {
        if ($row = $result->fetch_assoc()) {
            $_SESSION['eventid'] = $row['eventid'];
            $_SESSION['eventKey'] = $eventKey;
            header("Location: event/");
            exit();
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
    <title>Registration</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="./style.css" rel="stylesheet">
</head>

<body>
    <section class="vh-100 gradient-custom">
        <div class="container py-5 h-100">
            <div class="row d-flex justify-content-center align-items-center h-100">
                <div class="col-12 col-md-8 col-lg-6 col-xl-5">
                    <div class="card bg-dark text-white" style="border-radius: 1rem;">
                        <div class="card-body p-5 text-center">
                            <div class="mb-md-5 mt-md-4 pb-5">
                                <h2 class="fw-bold mb-2 text-uppercase">Event Key</h2>
                                <p class="text-white-50 mb-5">Please enter your event key.</p>
                                <form action="" method="POST">
                                    <div data-mdb-input-init class="form-outline form-white mb-4">
                                        <input type="text" id="eventKey" name="eventKey" autocomplete="off" class="form-control form-control-lg" />
                                        <label class="form-label" for="eventKey">Event Key</label>
                                    </div>
                                    <?php if ($error) { ?>
                                        <div class="alert alert-danger" role="alert">
                                            <?php echo $error; ?>
                                        </div>
                                    <?php } ?>
                                    <button data-mdb-button-init data-mdb-ripple-init class="btn btn-outline-light btn-lg px-5" type="submit">Enter</button>
                                </form>

                            </div>
                            <div>
                                <p class="mb-0">Log in as an administrator? <a href="login.php"
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
