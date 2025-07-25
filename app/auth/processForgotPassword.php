<?php
// Start session
session_start();

// Initialize success and error messages
$successMessage = "";
$errorMessage = "";

// Check if the form was submitted
if (isset($_GET['email']) && !empty($_GET['email'])) {
    $email = filter_var($_GET['email'], FILTER_SANITIZE_EMAIL);

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Invalid email format";
    } else {
        try {
            // Connect to database
            $db = new mysqli("localhost", "root", "", "taskbuddy");

            if ($db->connect_error) {
                throw new Exception("Connection failed: " . $db->connect_error);
            }

            // Check if email exists in database
            $stmt = $db->prepare("SELECT user_id, first_name FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                // Don't reveal that the email doesn't exist for security reasons
                $successMessage = "If your email is registered, you will receive a password reset link shortly.";
            } else {
                // Email exists, generate reset token
                $user = $result->fetch_assoc();
                $user_id = $user['user_id'];
                $first_name = $user['first_name'];

                // Generate token
                $token = bin2hex(random_bytes(16));
                $token_hash = hash("sha256", $token);

                // Set expiry time (10 minutes from now)
                $expiry = date("Y-m-d H:i:s", time() + 60 * 10);

                // Update user with token and expiry
                $update_stmt = $db->prepare("UPDATE users SET reset_token_hash = ?, reset_token_expires_at = ? WHERE user_id = ?");
                $update_stmt->bind_param("ssi", $token_hash, $expiry, $user_id);
                $update_stmt->execute();

                if ($update_stmt->affected_rows) {
                    // Send email
                    $mail = require __DIR__ . "/mailer.php";

                    $mail->addAddress($email, $first_name);
                    $mail->Subject = "TaskBuddy: Reset Your Password";

                    // Get the website domain dynamically
                    function getBaseUrl() {
                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'];

                        // Get the directory path of the script
                        $script_dir = dirname($_SERVER['SCRIPT_NAME']);

                        // Correct for root directory (prevents double slash)
                        $base_path = $script_dir === '/' ? '' : $script_dir;

                        return "$protocol://$host$base_path";
                    }

                    $base_url = getBaseUrl();
                    $reset_link = "$base_url/resetPassword.php?token=$token";

                    // Create email body with TaskBuddy styling
                    $mail->Body = <<<HTML
                    <div style="font-family: 'Nunito', Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px;">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <h2 style="color: #333;">Task<span style="color: #2D7C7C;">Buddy</span></h2>
                        </div>
                        <div style="padding: 20px; background-color: #f7f7f7; border-radius: 5px;">
                            <h3>Hello {$first_name},</h3>
                            <p>We received a request to reset your password for your TaskBuddy account.</p>
                            <p>To reset your password, please click the button below:</p>
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="{$reset_link}" style="background-color: #2D7C7C; color: white; padding: 12px 25px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">Reset Password</a>
                            </div>
                            <p>This link will expire in 10 minutes.</p>
                            <p>If you didn't request a password reset, you can safely ignore this email.</p>
                        </div>
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0; font-size: 12px; color: #777; text-align: center;">
                            <p>© TaskBuddy. All rights reserved.</p>
                        </div>
                    </div>
                    HTML;

                    // Plain text version
                    $mail->AltBody = <<<TEXT
                    Hello {$first_name},
                    
                    We received a request to reset your password for your TaskBuddy account.
                    
                    To reset your password, please visit this link: {$reset_link}
                    
                    This link will expire in 10 minutes.
                    
                    If you didn't request a password reset, you can safely ignore this email.
                    
                    TaskBuddy
                    TEXT;

                    try {
                        $mail->send();
                        $successMessage = "If your email is registered, you will receive a password reset link shortly.";
                    } catch (Exception $e) {
                        error_log("Email sending failed: " . $mail->ErrorInfo);
                        $errorMessage = "There was a problem sending the email. Please try again later.";
                    }
                } else {
                    $errorMessage = "Error updating reset token. Please try again.";
                }
            }

            $db->close();
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            $errorMessage = "An error occurred. Please try again later.";
        }
    }
} else {
    // Redirect to forgot password page if accessed directly
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
    <title>Password Reset Request - TaskBuddy</title>
    <style>
        .response-container {
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
        <div class="row align-items-center justify-content-center h-100 g-0 px-4 ps-sm-0">
            <div class="col col-sm-6 col-lg-7 col-xl-6">
                <a href="index.php" style="text-decoration: none;">
                    <span class="fs-3 d-flex justify-content-center mb-4">Task<span class="buddy">Buddy</span></span>
                </a>

                <div class="text-center mb-4">
                    <h3 class="fw-bold">Password Reset Request</h3>
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
                            <i class='bx bx-envelope bx-lg' style="color: #2D7C7C;"></i>
                        </div>
                        <p>If your email address exists in our database, you will receive a password recovery link at your email address shortly.</p>
                        <p class="mb-0">Please check your email inbox and spam folder.</p>
                    </div>

                    <div class="text-center mt-4">
                        <a href="public/signIn.php" class="btn btn-primary">Return to Sign In</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
</body>
</html>