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
$tokenValid = false;
$errorMessage = "";
$tokenExpired = false;
$user = null;

// Check if token is provided
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $token_hash = hash("sha256", $token);

    try {
        // Connect to database
        $db = new mysqli("localhost", "root", "", "taskbuddy");

        if ($db->connect_error) {
            throw new Exception("Connection failed: " . $db->connect_error);
        }

        // Check if token exists and is valid
        $stmt = $db->prepare("SELECT user_id, first_name, last_name, email, reset_token_expires_at FROM users WHERE reset_token_hash = ?");
        $stmt->bind_param("s", $token_hash);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Check if token has expired
            if (strtotime($user["reset_token_expires_at"]) <= time()) {
                $tokenExpired = true;
                $errorMessage = "This password reset link has expired. Please request a new one.";
            } else {
                $tokenValid = true;
            }
        } else {
            $errorMessage = "Invalid reset link. Please request a new one.";
        }

        $db->close();
    } catch (Exception $e) {
        $errorMessage = "Database error: Please try again later.";
    }
} else {
    $errorMessage = "Reset token not provided.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="signIn.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <title>Reset Password - TaskBuddy</title>
    <style>
        .buddy {
            color: #2D7C7C;
        }
        .password-feedback {
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        .password-requirement {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
            color: #6c757d;
        }
        .password-requirement i {
            margin-right: 0.5rem;
        }
        .requirement-met {
            color: #198754;
        }
        .requirement-not-met {
            color: #dc3545;
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
                    <h3 class="fw-bold">Reset Your Password</h3>
                    <?php if ($tokenValid && $user): ?>
                        <p class="text-muted">Hello <?php echo htmlspecialchars($user['first_name']); ?>, create a new password for your account.</p>
                    <?php endif; ?>
                </div>

                <?php if(!empty($errorMessage)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class='bx bx-error-circle me-2'></i>
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>

                    <div class="text-center mt-4">
                        <a href="forgotPassword.php" class="btn btn-primary">Request New Reset Link</a>
                    </div>
                <?php endif; ?>

                <?php if($tokenValid && $user): ?>
                    <div class="position-relative">
                        <hr class="text-secondary">
                    </div>

                    <!--form-->
                    <form id="resetPasswordForm" action="processResetPassword.php" method="post">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">

                        <div class="input-group mb-3">
                            <span class="input-group-text">
                                <i class="bx bx-lock"></i>
                            </span>
                            <input type="password" id="password" name="password" class="form-control form-control-lg fs-6" placeholder="New Password" required>
                        </div>

                        <div class="password-feedback mb-3">
                            <div class="password-requirement" id="length-requirement">
                                <i class='bx bx-x-circle requirement-not-met'></i>
                                <span>At least 8 characters</span>
                            </div>
                            <div class="password-requirement" id="letter-requirement">
                                <i class='bx bx-x-circle requirement-not-met'></i>
                                <span>Contains at least one letter</span>
                            </div>
                            <div class="password-requirement" id="number-requirement">
                                <i class='bx bx-x-circle requirement-not-met'></i>
                                <span>Contains at least one number</span>
                            </div>
                        </div>

                        <div class="input-group mb-3">
                            <span class="input-group-text">
                                <i class="bx bx-lock-alt"></i>
                            </span>
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control form-control-lg fs-6" placeholder="Confirm New Password" required>
                        </div>

                        <div id="password-match-feedback" class="invalid-feedback" style="display: none;">
                            Passwords do not match
                        </div>

                        <div class="input-group mb-3 d-flex justify-content-between">
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" id="showPassword" onclick="togglePasswordVisibility()">
                                <label class="form-check-label" for="showPassword">
                                    Show Password
                                </label>
                            </div>
                        </div>

                        <button type="submit" id="resetButton" class="btn btn-primary btn-lg w-100" disabled>Reset Password</button>
                    </form>
                    <!--form-->
                <?php endif; ?>

                <div class="text-center mt-3">
                    <small>Remember your password? <a href="signIn.php">Sign In</a></small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function togglePasswordVisibility() {
        const passwordInput = document.getElementById("password");
        const confirmInput = document.getElementById("password_confirmation");

        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            if (confirmInput) confirmInput.type = "text";
        } else {
            passwordInput.type = "password";
            if (confirmInput) confirmInput.type = "password";
        }
    }

    // Password validation
    document.addEventListener('DOMContentLoaded', function() {
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('password_confirmation');
        const resetButton = document.getElementById('resetButton');
        const matchFeedback = document.getElementById('password-match-feedback');

        const lengthReq = document.getElementById('length-requirement');
        const letterReq = document.getElementById('letter-requirement');
        const numberReq = document.getElementById('number-requirement');

        function updateRequirements() {
            const password = passwordInput.value;

            // Length requirement
            if (password.length >= 8) {
                lengthReq.querySelector('i').className = 'bx bx-check-circle requirement-met';
                lengthReq.className = 'password-requirement requirement-met';
            } else {
                lengthReq.querySelector('i').className = 'bx bx-x-circle requirement-not-met';
                lengthReq.className = 'password-requirement requirement-not-met';
            }

            // Letter requirement
            if (/[a-zA-Z]/.test(password)) {
                letterReq.querySelector('i').className = 'bx bx-check-circle requirement-met';
                letterReq.className = 'password-requirement requirement-met';
            } else {
                letterReq.querySelector('i').className = 'bx bx-x-circle requirement-not-met';
                letterReq.className = 'password-requirement requirement-not-met';
            }

            // Number requirement
            if (/[0-9]/.test(password)) {
                numberReq.querySelector('i').className = 'bx bx-check-circle requirement-met';
                numberReq.className = 'password-requirement requirement-met';
            } else {
                numberReq.querySelector('i').className = 'bx bx-x-circle requirement-not-met';
                numberReq.className = 'password-requirement requirement-not-met';
            }

            validateForm();
        }

        function validateForm() {
            const password = passwordInput.value;
            const confirmPassword = confirmInput.value;

            const lengthValid = password.length >= 8;
            const letterValid = /[a-zA-Z]/.test(password);
            const numberValid = /[0-9]/.test(password);
            const passwordsMatch = password === confirmPassword && confirmPassword !== '';

            if (passwordsMatch) {
                matchFeedback.style.display = 'none';
                confirmInput.classList.remove('is-invalid');
            } else if (confirmPassword !== '') {
                matchFeedback.style.display = 'block';
                confirmInput.classList.add('is-invalid');
            }

            // Enable button only if all requirements are met
            resetButton.disabled = !(lengthValid && letterValid && numberValid && passwordsMatch);
        }

        passwordInput.addEventListener('keyup', updateRequirements);
        confirmInput.addEventListener('keyup', validateForm);
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
</body>
</html>