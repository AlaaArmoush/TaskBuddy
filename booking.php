<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: signIn.php?redirect=booking.php" . (isset($_GET['tasker_id']) ? "?tasker_id=" . $_GET['tasker_id'] : ""));
    exit();
}

// Function to safely escape output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Initialize variables
$tasker_data = null;
$error_message = '';
$success_message = '';
$client_id = $_SESSION['user_id'];

// Check if tasker_id is provided
if (!isset($_GET['tasker_id'])) {
    $error_message = "No tasker selected. Please choose a tasker first.";
} else {
    $tasker_id = intval($_GET['tasker_id']);

    // Connect to database
    try {
        $db = new mysqli("localhost", "root", "", "taskbuddy");

        if ($db->connect_error) {
            throw new Exception("Connection failed: " . $db->connect_error);
        }

        // Check if the tasker exists and get their details
        $query = "
            SELECT 
                t.tasker_id,
                u.first_name,
                u.last_name,
                u.profile_image,
                t.hourly_rate,
                c.name AS category_name
            FROM 
                taskers t
            JOIN 
                users u ON t.user_id = u.user_id
            JOIN 
                categories c ON t.category_id = c.category_id
            WHERE 
                t.tasker_id = ?
        ";

        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $tasker_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $tasker_data = $result->fetch_assoc();
        } else {
            $error_message = "Tasker not found.";
        }

        // Process form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
            // Validate form data
            $booking_date = $_POST['booking_date'] ?? '';
            $time_slot = $_POST['time_slot'] ?? '';
            $task_description = $_POST['task_description'] ?? '';
            $address = $_POST['address'] ?? '';
            $contact_info = $_POST['contact_info'] ?? '';

            if (empty($booking_date) || empty($time_slot) || empty($task_description) || empty($address) || empty($contact_info)) {
                $error_message = "All fields are required.";
            } else {
                // Check if the date is in the future
                $current_date = date('Y-m-d');
                if ($booking_date < $current_date) {
                    $error_message = "Booking date must be in the future.";
                } else {
                    // Insert booking into database
                    $insert_query = "
                        INSERT INTO bookings 
                        (client_id, tasker_id, booking_date, time_slot, task_description, address, contact_info)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ";

                    $insert_stmt = $db->prepare($insert_query);
                    $insert_stmt->bind_param("iisssss", $client_id, $tasker_id, $booking_date, $time_slot, $task_description, $address, $contact_info);

                    if ($insert_stmt->execute()) {
                        $booking_id = $db->insert_id;

                        // Get tasker's user_id
                        $tasker_user_query = "SELECT user_id FROM taskers WHERE tasker_id = ?";
                        $tasker_user_stmt = $db->prepare($tasker_user_query);
                        $tasker_user_stmt->bind_param("i", $tasker_id);
                        $tasker_user_stmt->execute();
                        $tasker_user_result = $tasker_user_stmt->get_result();
                        $tasker_user_id = $tasker_user_result->fetch_assoc()['user_id'];

                        // Create notification for tasker
                        $notification_message = "New booking request from " . $_SESSION['first_name'] . " " . $_SESSION['last_name'];
                        $notification_query = "
                            INSERT INTO notifications 
                            (user_id, message, related_to, related_id)
                            VALUES (?, ?, 'booking', ?)
                        ";

                        $notification_stmt = $db->prepare($notification_query);
                        $notification_stmt->bind_param("isi", $tasker_user_id, $notification_message, $booking_id);
                        $notification_stmt->execute();

                        // Create notification for client
                        $client_notification = "Your booking request for " . $tasker_data['first_name'] . " " . $tasker_data['last_name'] . " is pending approval";
                        $client_notification_query = "
                            INSERT INTO notifications 
                            (user_id, message, related_to, related_id)
                            VALUES (?, ?, 'booking', ?)
                        ";

                        $client_notification_stmt = $db->prepare($client_notification_query);
                        $client_notification_stmt->bind_param("isi", $client_id, $client_notification, $booking_id);
                        $client_notification_stmt->execute();

                        $success_message = "Booking request submitted successfully! You will be notified when the tasker responds.";

                        // Redirect to task status page after a delay
                        header("refresh:3;url=task_status.php");
                    } else {
                        $error_message = "Failed to submit booking: " . $db->error;
                    }
                }
            }
        }

        $db->close();

    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="landing.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Tasker - TaskBuddy</title>
    <style>
        .tasker-info {
            background-color: #f7f3ed;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .tasker-info .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .booking-card {
            border-radius: 1.5rem;
            border: none;
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .booking-card .card-body {
            padding: 2rem;
        }

        .booking-form label {
            font-weight: 600;
            color: #5a3e20;
            margin-bottom: 0.5rem;
        }

        .booking-form .form-control {
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            border-color: #e0e0e0;
            background-color: #f9f9f9;
        }

        .booking-form .form-control:focus {
            border-color: #2D7C7C;
            box-shadow: 0 0 0 0.25rem rgba(45, 124, 124, 0.25);
        }

        .btn-book-now {
            background: linear-gradient(45deg, #5a3e20 0%, #8b6b4c 100%);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .btn-book-now:hover {
            background: linear-gradient(45deg, #2D7C7C 0%, #48a3a3 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(45, 124, 124, 0.3);
        }

        .price-badge {
            background-color: #2D7C7C;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.5rem;
        }

        .alert {
            border-radius: 0.75rem;
            padding: 1rem 1.5rem;
        }

        .navigation-bar {
            position: sticky;
            top: 0;
            z-index: 100;
            background-color: white;
        }
    </style>
</head>

<body>
<!-- Navigation Bar -->
<section class="navigation-bar">
    <div class="container">
        <header class="d-flex flex-wrap justify-content-center py-3 mb-0">
            <a href="index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto link-body-emphasis text-decoration-none">
                <span class="fs-3">Task<span class="buddy">Buddy</span></span>
            </a>

            <ul class="nav nav-pills">
                <li class="nav-item"><a href="services.php" class="nav-link">Services</a></li>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <li class="nav-item"><a href="signUp.php" class="nav-link">Sign Up</a></li>
                    <li class="nav-item"><a href="signIn.php" class="nav-link">Sign In</a></li>
                    <li class="nav-item"><a href="BecomeATasker.html" class="nav-link">Become a Tasker</a></li>
                <?php else: ?>
                    <li class="nav-item"><a href="task_status.php" class="nav-link">Tasks Updates & Status</a></li>
                    <li class="nav-item"><a href="logout.php" class="nav-link">Sign Out</a></li>
                <?php endif; ?>
            </ul>
        </header>
    </div>
    <div class="border-container">
        <div class="border-line"></div>
    </div>
</section>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger mb-4">
                    <?php echo h($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success mb-4">
                    <?php echo h($success_message); ?>
                    <div class="mt-2">
                        <small>Redirecting to your task status page...</small>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tasker_data && empty($success_message)): ?>
                <!-- Tasker Information -->
                <div class="tasker-info d-flex align-items-center">
                    <img src="<?php echo h($tasker_data['profile_image']); ?>" alt="<?php echo h($tasker_data['first_name']); ?>" class="avatar me-4">
                    <div>
                        <h4 class="mb-1"><?php echo h($tasker_data['first_name'] . ' ' . $tasker_data['last_name']); ?></h4>
                        <div class="text-muted mb-2"><?php echo h($tasker_data['category_name']); ?></div>
                        <div class="price-badge">$<?php echo h(number_format($tasker_data['hourly_rate'], 2)); ?> / hour</div>
                    </div>
                </div>

                <!-- Booking Form -->
                <div class="card booking-card">
                    <div class="card-body">
                        <h2 class="text-center mb-4">Book This Tasker</h2>
                        <form class="booking-form" method="post" action="">
                            <div class="mb-4">
                                <label for="booking_date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="booking_date" name="booking_date" required
                                       min="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <div class="mb-4">
                                <label for="time_slot" class="form-label">Preferred Time</label>
                                <select class="form-control" id="time_slot" name="time_slot" required>
                                    <option value="" disabled selected>Select a time</option>
                                    <option>Morning (8AM - 12PM)</option>
                                    <option>Afternoon (12PM - 4PM)</option>
                                    <option>Evening (4PM - 8PM)</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label for="task_description" class="form-label">Task Description</label>
                                <textarea class="form-control" id="task_description" name="task_description" rows="4"
                                          placeholder="Please describe what you need help with..." required></textarea>
                            </div>

                            <div class="mb-4">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address"
                                       placeholder="Enter your full address" required>
                            </div>

                            <div class="mb-4">
                                <label for="contact_info" class="form-label">Contact Information</label>
                                <input type="text" class="form-control" id="contact_info" name="contact_info"
                                       placeholder="Phone number or email" required>
                            </div>

                            <div class="text-center mt-5">
                                <button type="submit" name="submit_booking" class="btn btn-primary btn-lg btn-book-now">Book Now</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
</body>
</html>