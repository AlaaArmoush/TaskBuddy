<?php
$firstName = "";
$lastName = "";
$email = "";
$errorMessage = "";
$formSubmitted = false;

// Check if user is already logged in
session_start();
if (isset($_SESSION['user_id'])) {
    // Redirect based on user type
    if ($_SESSION['is_tasker']) {
        header("Location: TaskerTemplate.php?id=" . $_SESSION['user_id']);
    } else {
        header("Location: services.php");
    }
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $formSubmitted = true;

    if(isset($_POST['fullName']) && isset($_POST['userEmail']) && isset($_POST['userPass']) && isset($_POST['userPassConfirm']) &&
        !empty($_POST['fullName']) && !empty($_POST['userEmail']) && !empty($_POST['userPass']) && !empty($_POST['userPassConfirm'])) {

        $fullName = $_POST['fullName'];
        $nameParts = explode(" ", $fullName);
        $firstName = $nameParts[0];
        $lastName = isset($nameParts[1]) ? $nameParts[1] : "";
        $email = $_POST['userEmail'];
        $pass = $_POST['userPass'];
        $passConfirm = $_POST['userPassConfirm'];

        // Get user type (customer or tasker)
        $userType = isset($_POST['userType']) ? $_POST['userType'] : 'customer';
        $isTasker = ($userType === 'tasker') ? 1 : 0;

        $isValid = true;

        // Validate password match
        if($pass !== $passConfirm) {
            $errorMessage = "Passwords do not match";
            $isValid = false;
        }

        // Only proceed if passwords match
        if($isValid) {
            try {
                $db = new mysqli("localhost", "root", "", "taskbuddy");

                if ($db->connect_error) {
                    throw new Exception("Connection failed: " . $db->connect_error);
                }

                // Check if email already exists
                $checkEmail = $db->prepare("SELECT email FROM users WHERE email = ?");
                $checkEmail->bind_param("s", $email);
                $checkEmail->execute();
                $result = $checkEmail->get_result();

                if($result->num_rows > 0) {
                    $errorMessage = "Email address is already registered";
                    $isValid = false;
                } else {
                    // Hash password only when we're sure we'll use it
                    $hashedPass = password_hash($pass, PASSWORD_DEFAULT);

                    // Insert the user with the is_tasker flag
                    $stmt = $db->prepare("INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `email`, `password`, `created_at`, `is_tasker`) VALUES (NULL, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)");
                    $stmt->bind_param("ssssi", $firstName, $lastName, $email, $hashedPass, $isTasker);
                    $stmt->execute();

                    // Get the inserted user_id
                    $userId = $db->insert_id;

                    // If the user is a tasker, create a tasker record with default values
                    if ($isTasker) {
                        // Default values for a new tasker
                        $defaultHourlyRate = 25.00;
                        $defaultCategoryId = 1; // First category in your system
                        $defaultBio = "I'm a professional tasker ready to help with your needs.";
                        $defaultServiceDesc = "Professional services with attention to detail and customer satisfaction.";

                        $taskerStmt = $db->prepare("INSERT INTO `taskers` (`tasker_id`, `user_id`, `bio`, `hourly_rate`, `category_id`, `service_description`) VALUES (NULL, ?, ?, ?, ?, ?)");
                        $taskerStmt->bind_param("isdis", $userId, $defaultBio, $defaultHourlyRate, $defaultCategoryId, $defaultServiceDesc);
                        $taskerStmt->execute();
                    }

                    // Start session and set user data
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['first_name'] = $firstName;
                    $_SESSION['last_name'] = $lastName;
                    $_SESSION['email'] = $email;
                    $_SESSION['is_tasker'] = $isTasker;

                    // Redirect based on user type
                    if ($isTasker) {
                        header("Location: /templates/TaskerTemplate.php?id=" . $userId);
                    } else {
                        header("Location: /services.php");
                    }
                    exit();
                }
                $db->close();
            } catch (Exception $e) {
                $errorMessage = "Database error: Please try again later.";
                $isValid = false;
            }
        }
    } else {
        $errorMessage = "All fields are required";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/css/signIn.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <title>Sign Up</title>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Client-side validation
            const form = document.getElementById('signupForm');
            form.addEventListener('submit', function(event) {
                const password = document.getElementById('userPass').value;
                const confirmPassword = document.getElementById('userPassConfirm').value;
                const errorDiv = document.getElementById('validationError');

                if (password !== confirmPassword) {
                    errorDiv.textContent = "Passwords do not match";
                    errorDiv.style.display = "block";
                    event.preventDefault();
                } else {
                    errorDiv.style.display = "none";
                }
            });

            // User type selection
            const userTypeOptions = document.querySelectorAll('.user-type-option');
            const userTypeInput = document.getElementById('userTypeInput');

            userTypeOptions.forEach(option => {
                option.addEventListener('click', function() {
                    userTypeOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');
                    userTypeInput.value = this.getAttribute('data-type');
                });
            });

        });
    </script>
</head>
<body>
<div class="row min-vh-100 g-0 align-items-center">
    <div class="col-lg-12">
        <div class="row align-items-center justify-content-center h-100 g-0 px-4 ps-sm-0 ">
            <div class="col col-sm-6 col-lg-7 col-xl-6">
                <a href="index.php" style="text-decoration: none;"> <span class="fs-3 d-flex justify-content-center mb-4">Task<span class="buddy">Buddy</span></span>
                </a>
                <div class="text-center mb-5">
                    <h3 class="fw-bold">Create Account</h3>
                </div>

                <?php if(!empty($errorMessage)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $errorMessage; ?>
                    </div>
                <?php endif; ?>

                <div id="validationError" class="alert alert-danger" style="display: none;"></div>

                <div class="user-type-container">
                    <div class="user-type-option active" data-type="customer">
                        <i class='bx bx-list-check'></i>
                        <h5>Get Tasks Done</h5>
                        <p>Hire skilled taskers to help with your to-do list</p>
                    </div>
                    <div class="user-type-option" data-type="tasker">
                        <i class='bx bx-wallet'></i>
                        <h5>Earn Money</h5>
                        <p>Use your skills to complete tasks and get paid</p>
                    </div>
                </div>

                <div class="position-relative">
                    <hr class="text-secondary">
                </div>

                <!--form-->
                <form id="signupForm" action="signUp.php" method="post">
                    <!-- Hidden input for user type -->
                    <input type="hidden" name="userType" id="userTypeInput" value="customer">

                    <div class="input-group mb-3">
                            <span class="input-group-text">
                                <i class="bx bx-user"></i>
                            </span>
                        <input type="text" name="fullName" class="form-control form-control-lg fs-6" placeholder="Full Name" required
                               value="<?php echo $formSubmitted ? htmlspecialchars($_POST['fullName'] ?? '') : ''; ?>">
                    </div>

                    <div class="input-group mb-3">
                            <span class="input-group-text">
                                <i class="bx bx-envelope"></i>
                            </span>
                        <input type="email" name="userEmail" class="form-control form-control-lg fs-6" placeholder="Email Address" required
                               value="<?php echo $formSubmitted ? htmlspecialchars($_POST['userEmail'] ?? '') : ''; ?>">
                    </div>

                    <div class="input-group mb-3">
                            <span class="input-group-text">
                                <i class="bx bx-lock"></i>
                            </span>
                        <input type="password" id="userPass" name="userPass" class="form-control form-control-lg fs-6" placeholder="Password" required>
                    </div>

                    <div class="input-group mb-3">
                            <span class="input-group-text">
                                <i class="bx bx-lock-alt"></i>
                            </span>
                        <input type="password" id="userPassConfirm" name="userPassConfirm" class="form-control form-control-lg fs-6" placeholder="Confirm Password" required>
                    </div>

                    <div class="input-group mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="termsCheck" required
                                <?php echo ($formSubmitted && isset($_POST['termsCheck'])) ? 'checked' : ''; ?>>
                            <label for="termsCheck" class="form-check-label text-secondary">
                                <small>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></small>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100">Create Account</button>
                </form>
                <!--form-->

                <div class="text-center mt-3">
                    <small>Already have an account? <a href="public/signIn.php">Sign In</a></small>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
</body>
</html>