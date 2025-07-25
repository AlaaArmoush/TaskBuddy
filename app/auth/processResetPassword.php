<?php
// Start session
session_start();

// Initialize messages
$errorMessage = "";
$successMessage = "";

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate required fields
    if (!isset($_POST['token']) || empty($_POST['token']) || 
        !isset($_POST['password']) || empty($_POST['password']) || 
        !isset($_POST['password_confirmation']) || empty($_POST['password_confirmation'])) {
        
        header("Location: forgotPassword.php");
        exit();
    }

    // Get form data
    $token = $_POST['token'];
    $password = $_POST['password'];
    $password_confirmation = $_POST['password_confirmation'];
    $token_hash = hash("sha256", $token);

    // Validate password
    if (strlen($password) < 8) {
        $errorMessage = "Password must be at least 8 characters long";
    } elseif (!preg_match("/[a-zA-Z]/", $password)) {
        $errorMessage = "Password must contain at least one letter";
    } elseif (!preg_match("/[0-9]/", $password)) {
        $errorMessage = "Password must contain at least one number";
    } elseif ($password !== $password_confirmation) {
        $errorMessage = "Passwords do not match";
    } else {
        try {
            // Connect to database
            $db = new mysqli("localhost", "root", "", "taskbuddy");

            if ($db->connect_error) {
                throw new Exception("Connection failed: " . $db->connect_error);
            }

            // Check if token exists and is valid
            $stmt = $db->prepare("SELECT user_id, email, reset_token_expires_at FROM users WHERE reset_token_hash = ?");
            $stmt->bind_param("s", $token_hash);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $errorMessage = "Invalid or expired token. Please request a new password reset link.";
            } else {
                $user = $result->fetch_assoc();

                // Check if token has expired
                if (strtotime($user["reset_token_expires_at"]) <= time()) {
                    $errorMessage = "Password reset link has expired. Please request a new one.";
                } else {
                    // Hash the new password
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);

                    // Update the user's password and clear the reset token
                    $update_stmt = $db->prepare("UPDATE users SET 
                                                password = ?, 
                                                reset_token_hash = NULL, 
                                                reset_token_expires_at = NULL 
                                                WHERE user_id = ?");
                    $update_stmt->bind_param("si", $password_hash, $user['user_id']);
                    $update_stmt->execute();

                    if ($update_stmt->affected_rows) {
                        $successMessage = "Password has been reset successfully.";
                    } else {
                        $errorMessage = "Failed to update password. Please try again.";
                    }
                }
            }

            $db->close();
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            $errorMessage = "An error occurred. Please try again later.";
        }
    }
} else {
    // Redirect if accessed directly
    header("Location: forgotPassword.php");
    exit();
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
    <title>Password Reset Result - TaskBuddy</title>
    <style>
        .buddy {
            color: #2D7C7C;
        }
    </style>
</head>
<body>
<div class="row vh-100 g-0">
    <div class="col-lg-12">
        <div class="row align-items-center justify-content-center h-100 g-0 px-4 ps-sm-0">
            <div class="col col-sm-6 col-lg-7 col-xl-6">
                <a href="index.php" style="text-decoration: none;">
                    <span class="fs-3 d-flex justify-content-center mb-4">Task<span class="buddy">Buddy</span></span>
                </a>

                <div class="text-center mb-4">
                    <h3 class="fw-bold">Password Reset</h3>
                </div>

                <?php if(!empty($errorMessage)): ?>
                    <div class="alert alert-danger text-center" role="alert">
                        <i class='bx bx-error-circle me-2'></i>
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                    <div class="text-center mt-4">
                        <a href="forgotPassword.php" class="btn btn-primary">Try Again</a>
                    </div>
                <?php endif; ?>

                <?php if(!empty($successMessage)): ?>
                    <div class="alert alert-success text-center" role="alert">
                        <i class='bx bx-check-circle me-2'></i>
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>

                    <div class="card mt-4 p-4 text-center">
                        <div class="mb-4">
                            <i class='bx bx-check-shield bx-lg' style="color: #2D7C7C;"></i>
                        </div>
                        <h4>Password Reset Successful!</h4>
                        <p>Your password has been updated successfully.</p>
                        <p class="mb-0">You can now log in with your new password.</p>
                    </div>

                    <div class="text-center mt-4">
                        <a href="public/signIn.php" class="btn btn-primary">Sign In</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
</body>
</html>
Claude