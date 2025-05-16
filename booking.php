<?php
session_start();

// Include notification helper
require_once 'notifications_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // User is not logged in, redirect to sign in page with a message
    $_SESSION['login_required'] = "You need to sign in before booking a tasker.";
    header("Location: signIn.php");
    exit;
}

// Database connection
$db_connected = false;
$db_error_message = "";
$tasker_data = null;
$success_message = '';
$error_message = '';

try {
    $db = new mysqli("localhost", "root", "", "taskbuddy");
    if ($db->connect_error) {
        throw new Exception("Connection failed: " . $db->connect_error);
    }
    $db_connected = true;

    // Initialize notification counter
    $notification_count = 0;
    $user_id = $_SESSION['user_id'];

    // Get notification count
    if (isset($_SESSION['is_tasker']) && $_SESSION['is_tasker'] == 1) {
        $notification_count = getUnreadNotificationCount($db, $user_id, 'tasker');
    } else {
        $notification_count = getUnreadNotificationCount($db, $user_id, 'client');
    }

} catch (Exception $e) {
    $db_error_message = $e->getMessage();
    error_log("Database Error: " . $db_error_message);
}

// Function to safely escape output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Get tasker ID from URL
if (isset($_GET['tasker_id'])) {
    $tasker_id = intval($_GET['tasker_id']);
} else {
    // No tasker ID provided, redirect to services page
    header("Location: services.php");
    exit;
}

// Process booking form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db_connected) {
    try {
        $client_id = $_SESSION['user_id'];
        $booking_date = $_POST['booking_date'];
        $time_slot = $_POST['time_slot'];
        $task_description = $_POST['task_description'];
        $address = $_POST['address'];
        $contact_info = $_POST['contact_info'];

        // Insert the booking into the database
        $stmt = $db->prepare("INSERT INTO bookings (client_id, tasker_id, booking_date, time_slot, task_description, address, contact_info) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssss", $client_id, $tasker_id, $booking_date, $time_slot, $task_description, $address, $contact_info);

        if ($stmt->execute()) {
            // Create a notification for the tasker
            $booking_id = $db->insert_id;
            $user_query = $db->prepare("SELECT u.user_id FROM taskers t JOIN users u ON t.user_id = u.user_id WHERE t.tasker_id = ?");
            $user_query->bind_param("i", $tasker_id);
            $user_query->execute();
            $user_result = $user_query->get_result();

            if ($user_result && $user_result->num_rows > 0) {
                $tasker_user_id = $user_result->fetch_assoc()['user_id'];

                // Create notification for tasker
                $notification_message = "You have a new booking request.";
                $notification_stmt = $db->prepare("INSERT INTO notifications (user_id, message, related_to, related_id) 
                                                VALUES (?, ?, 'booking', ?)");
                $notification_stmt->bind_param("isi", $tasker_user_id, $notification_message, $booking_id);
                $notification_stmt->execute();
            }

            // Success - redirect to status page
            $_SESSION['booking_success'] = "Your booking has been submitted successfully! The tasker will review your request.";
            header("Location: task_status.php");
            exit;
        } else {
            $error_message = "Failed to submit booking: " . $db->error;
        }
    } catch (Exception $e) {
        $error_message = "Error processing booking: " . $e->getMessage();
    }
}

// Fetch tasker info if database is connected
if ($db_connected) {
    $query = "
        SELECT 
            t.tasker_id,
            t.hourly_rate,
            t.service_description,
            u.first_name,
            u.last_name,
            u.profile_image,
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
        // Tasker not found
        header("Location: services.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Tasker - TaskBuddy</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="landing.css">
    <style>
        .booking-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        .tasker-card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .tasker-info {
            display: flex;
            align-items: center;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .tasker-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 20px;
            border: 3px solid #fff;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        .tasker-details h3 {
            margin-bottom: 5px;
            font-weight: 600;
        }
        .category-badge {
            background-color: #4e73df;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            display: inline-block;
        }
        .price-info {
            font-weight: 600;
            color: #28a745;
        }
        .booking-form {
            background-color: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .form-label {
            font-weight: 600;
        }
        .nav-link.active, .nav-link:hover {
            color: #4e73df !important;
        }
        .buddy {
            color: #4e73df;
        }
    </style>
</head>
<body>

<section class="navigation-bar">
    <div class="container">
        <header class="d-flex flex-wrap justify-content-center py-3 mb-0">
            <a href="index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto link-body-emphasis text-decoration-none">
                <svg class="bi me-2" width="40" height="32" aria-hidden="true"><use xlink:href="#bootstrap"></use></svg>
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

<div class="container booking-container">
    <h2 class="text-center mb-4">Book a Tasker</h2>

    <?php if (!$db_connected): ?>
        <div class="alert alert-warning" role="alert">
            <h4 class="alert-heading">Unable to connect to database</h4>
            <p>We're currently experiencing technical difficulties. Please try again later.</p>
        </div>
    <?php elseif (!$tasker_data): ?>
        <div class="alert alert-warning" role="alert">
            <h4 class="alert-heading">Tasker not found</h4>
            <p>The requested tasker profile does not exist or has been removed.</p>
            <a href="services.php" class="btn btn-primary mt-3">Browse Services</a>
        </div>
    <?php else: ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo h($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="tasker-card">
            <div class="tasker-info">
                <img src="<?php echo h($tasker_data['profile_image']); ?>" alt="<?php echo h($tasker_data['first_name']); ?>" class="tasker-avatar">
                <div class="tasker-details">
                    <h3><?php echo h($tasker_data['first_name'] . ' ' . $tasker_data['last_name']); ?></h3>
                    <div class="category-badge"><?php echo h($tasker_data['category_name']); ?></div>
                    <p class="price-info mt-2">$<?php echo h(number_format($tasker_data['hourly_rate'], 2)); ?> / hour</p>
                </div>
            </div>
        </div>

        <div class="booking-form">
            <form method="post" action="booking.php?tasker_id=<?php echo h($tasker_id); ?>">
                <div class="mb-4">
                    <label for="booking_date" class="form-label">Date</label>
                    <input type="date" class="form-control" id="booking_date" name="booking_date" required
                           min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="mb-4">
                    <label for="time_slot" class="form-label">Preferred Time</label>
                    <select class="form-control" id="time_slot" name="time_slot" required>
                        <option value="" disabled selected>Select a time</option>
                        <option value="Morning (8AM - 12PM)">Morning (8AM - 12PM)</option>
                        <option value="Afternoon (12PM - 4PM)">Afternoon (12PM - 4PM)</option>
                        <option value="Evening (4PM - 8PM)">Evening (4PM - 8PM)</option>
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
                    <a href="TaskerTemplate.php?id=<?php echo h($tasker_id); ?>" class="btn btn-secondary btn-lg">Cancel</a>
                    <button type="submit" class="btn btn-primary btn-lg">Submit Booking Request</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
<script>
    // Set minimum date to today
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('booking_date').setAttribute('min', today);
    });
</script>
</body>
</html>