<?php
// Start session
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    if (!empty($_SESSION['is_admin'])) {
        header("Location: admin_dashboard.php");
    } elseif (!empty($_SESSION['is_tasker'])) {
        header("Location: TaskerTemplate.php?id=" . $_SESSION['user_id']);
    } else {
        header("Location: services.php");
    }
    exit();
}


// Initialize variables
$email = "";
$password = "";
$errorMessage = "";
$formSubmitted = false;

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $formSubmitted = true;

    // Validate form data
    if (isset($_POST['userEmail']) && isset($_POST['userPass']) &&
        !empty($_POST['userEmail']) && !empty($_POST['userPass'])) {

        $email = $_POST['userEmail'];
        $password = $_POST['userPass'];

        try {
            // Connect to database
            $db = new mysqli("localhost", "root", "", "taskbuddy");

            if ($db->connect_error) {
                throw new Exception("Connection failed: " . $db->connect_error);
            }

            // Prepare query to get user by email
            $stmt = $db->prepare("SELECT user_id, first_name, last_name, email, password, is_tasker, is_admin FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Password is correct, create session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['is_tasker'] = $user['is_tasker'];
                    $_SESSION['is_admin'] = $user['is_admin'] ?? 0;

                    // Check if there's a redirect parameter
                    if (isset($_GET['redirect'])) {
                        header("Location: " . $_GET['redirect']);
                    } else {
                        // Default redirect based on user type
                        if ($user['is_admin'] == 1) {
                            header("Location: admin_dashboard.php");
                        } elseif ($user['is_tasker']) {
                            header("Location: TaskerTemplate.php?id=" . $user['user_id']);
                        } else {
                            header("Location: services.php");
                        }
                    }
                    exit();
                } else {
                    // Password is incorrect
                    $errorMessage = "Invalid email or password";
                }
            } else {
                // User not found
                $errorMessage = "Invalid email or password";
            }

            $db->close();
        } catch (Exception $e) {
            $errorMessage = "Database error: Please try again later.";
        }
    } else {
        $errorMessage = "Please fill in all fields";
    }
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
    <title>Sign In - TaskBuddy</title>
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
                    <h3 class="fw-bold">Sign In</h3>
                </div>

                <?php if(!empty($errorMessage)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>

                <div class="position-relative">
                    <hr class="text-secondary">
                </div>

                <!--form-->
                <form action="signIn.php" method="post">
                    <div class="input-group mb-3">
                            <span class="input-group-text">
                                <i class="bx bx-envelope"></i>
                            </span>
                        <input type="email" name="userEmail" class="form-control form-control-lg fs-6" placeholder="Email Address" required
                               value="<?php echo $formSubmitted ? htmlspecialchars($email) : ''; ?>">
                    </div>

                    <div class="input-group mb-3">
                            <span class="input-group-text">
                                <i class="bx bx-lock"></i>
                            </span>
                        <input type="password" id="passwordInput" name="userPass" class="form-control form-control-lg fs-6" placeholder="Password" required>
                    </div>



                    <div class="input-group mb-3 d-flex justify-content-between">
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="showPassword" onclick="togglePassword()">
                            <label class="form-check-label" for="showPassword">
                                Show Password
                            </label>
                        </div>

                        <div>
                            <small><a href="forgotPassword.php">Forgot Password?</a></small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100">Sign In</button>
                </form>
                <!--form-->

                <div class="text-center mt-3">
                    <small>Don't have an account? <a href="signUp.php">Sign Up</a></small>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function togglePassword() {
        const passwordInput = document.getElementById("passwordInput");
        passwordInput.type = passwordInput.type === "password" ? "text" : "password";
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
</body>
</html>