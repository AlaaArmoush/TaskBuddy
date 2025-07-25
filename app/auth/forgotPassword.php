<?php
// Start session
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on user type
    if ($_SESSION['is_tasker']) {
        header("Location: TaskerTemplate.php?id=" . $_SESSION['user_id']);
    } else {
        header("Location: services.php");
    }
    exit();
}

// Initialize variables
$email = "";
$successMessage = "";
$errorMessage = "";
$formSubmitted = false;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $formSubmitted = true;

    // Validate email
    if (isset($_POST['email']) && !empty($_POST['email'])) {
        $email = $_POST['email'];

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Please enter a valid email address";
        } else {
            // Redirect to process page
            header("Location: processForgotPassword.php?email=" . urlencode($email));
            exit();
        }
    } else {
        $errorMessage = "Please enter your email address";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/signIn.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <title>Forgot Password - TaskBuddy</title>
    <style>
        .forgot-password-container {
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
        }
        .buddy {
            color: #2D7C7C;
        }
    </style>
</head>
<body>
<div class="row vh-100 g-0">
    <div class="col-lg-12">
        <div class="row align-items-center justify-content-center h-100 g-0 px-4 ps-sm-0 ">
            <div class="col col-sm-6 col-lg-7 col-xl-6">
                <a href="index.php" style="text-decoration: none;">
                    <span class="fs-3 d-flex justify-content-center mb-4">Task<span class="buddy">Buddy</span></span>
                </a>
                <div class="text-center mb-5">
                    <h3 class="fw-bold">Forgot Password</h3>
                    <p class="text-muted">Enter your email address and we'll send you a link to reset your password</p>
                </div>

                <?php if(!empty($errorMessage)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>

                <?php if(!empty($successMessage)): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>

                <div class="position-relative">
                    <hr class="text-secondary">
                </div>

                <!--form-->
                <form action="forgotPassword.php" method="post">
                    <div class="input-group mb-3">
                        <span class="input-group-text">
                            <i class="bx bx-envelope"></i>
                        </span>
                        <input type="email" name="email" class="form-control form-control-lg fs-6" placeholder="Email Address" required
                               value="<?php echo $formSubmitted ? htmlspecialchars($email) : ''; ?>">
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100">Reset Password</button>
                </form>
                <!--form-->

                <div class="text-center mt-3">
                    <small>Remember your password? <a href="public/signIn.php">Sign In</a></small>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
</body>
</html>