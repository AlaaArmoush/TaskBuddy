<?php
session_start();


$homeLink = 'index.php';
if (isset($_SESSION['user_id'], $_SESSION['is_tasker']) && $_SESSION['is_tasker'] == 1) {
    // Redirect taskers to their own profile template, passing their user_id
    $homeLink = 'TaskerTemplate.php?id=' . intval($_SESSION['user_id']);
}

// Include notification helper
require_once 'notifications_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // User is not logged in, redirect to sign in page
    header("Location: signIn.php");
    exit;
}

// Database connection
$db_connected = false;
$db_error_message = "";
$bookings = [];
$success_message = '';
$error_message = '';

try {
    $db = new mysqli("localhost", "root", "", "taskbuddy");
    if ($db->connect_error) {
        throw new Exception("Connection failed: " . $db->connect_error);
    }
    $db_connected = true;

    // Initialize notification counter (for nav menu)
    $notification_count = 0;
    $user_id = $_SESSION['user_id'];

    // Get notification count based on user type
    if (isset($_SESSION['is_tasker']) && $_SESSION['is_tasker'] == 1) {
        $notification_count = getUnreadNotificationCount($db, $user_id, 'tasker');
    } else {
        $notification_count = getUnreadNotificationCount($db, $user_id, 'client');
    }

    // Mark notifications as read when client views the status page
    markNotificationsAsRead($db, $user_id, 'booking');

} catch (Exception $e) {
    $db_error_message = $e->getMessage();
    error_log("Database Error: " . $db_error_message);
}

// Function to safely escape output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Process cancel booking action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db_connected) {
    if (isset($_POST['cancel_booking']) && isset($_POST['booking_id'])) {
        $booking_id = intval($_POST['booking_id']);
        $user_id = $_SESSION['user_id'];

        // Verify this booking belongs to the current user
        $check_query = $db->prepare("SELECT booking_id, tasker_id FROM bookings WHERE booking_id = ? AND client_id = ? AND status NOT IN ('completed', 'cancelled')");
        $check_query->bind_param("ii", $booking_id, $user_id);
        $check_query->execute();
        $check_result = $check_query->get_result();

        if ($check_result && $check_result->num_rows > 0) {
            $booking = $check_result->fetch_assoc();
            $tasker_id = $booking['tasker_id'];

            // Update the booking status
            $update_stmt = $db->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ?");
            $update_stmt->bind_param("i", $booking_id);

            if ($update_stmt->execute()) {
                // Get the tasker's user_id for notification
                $tasker_query = $db->prepare("SELECT user_id FROM taskers WHERE tasker_id = ?");
                $tasker_query->bind_param("i", $tasker_id);
                $tasker_query->execute();
                $tasker_result = $tasker_query->get_result();

                if ($tasker_result && $tasker_result->num_rows > 0) {
                    $tasker_user_id = $tasker_result->fetch_assoc()['user_id'];

                    // Create notification for tasker
                    $notification_message = "A booking has been cancelled by the client.";
                    $notification_stmt = $db->prepare("INSERT INTO notifications (user_id, message, related_to, related_id) 
                                                     VALUES (?, ?, 'booking', ?)");
                    $notification_stmt->bind_param("isi", $tasker_user_id, $notification_message, $booking_id);
                    $notification_stmt->execute();
                }

                $success_message = "Booking cancelled successfully.";
            }
        }
    }

    // Process review submission
    if (isset($_POST['submit_review'])) {
        try {
            $booking_id = intval($_POST['booking_id']);
            $tasker_id = intval($_POST['tasker_id']);
            $rating = intval($_POST['rating']);
            $comment = trim($_POST['comment'] ?? '');
            $client_id = $_SESSION['user_id'];

            // Validate the rating
            if ($rating < 1 || $rating > 5) {
                throw new Exception("Rating must be between 1 and 5.");
            }

            // Start transaction
            $db->begin_transaction();

            // Insert the review
            $insert_review = $db->prepare("INSERT INTO reviews (tasker_id, client_id, rating, comment) VALUES (?, ?, ?, ?)");
            $insert_review->bind_param("iiis", $tasker_id, $client_id, $rating, $comment);

            if (!$insert_review->execute()) {
                throw new Exception("Failed to submit review: " . $db->error);
            }

            // Update tasker's average rating and total reviews
            // First, get current stats
            $tasker_query = $db->prepare("SELECT average_rating, total_reviews FROM taskers WHERE tasker_id = ?");
            $tasker_query->bind_param("i", $tasker_id);
            $tasker_query->execute();
            $tasker_result = $tasker_query->get_result();

            if (!$tasker_result || $tasker_result->num_rows === 0) {
                throw new Exception("Tasker not found.");
            }

            $tasker_data = $tasker_result->fetch_assoc();
            $current_avg = floatval($tasker_data['average_rating']);
            $total_reviews = intval($tasker_data['total_reviews']);

            // Calculate new average rating
            $new_total = $total_reviews + 1;
            $new_avg = (($current_avg * $total_reviews) + $rating) / $new_total;

            // Update tasker stats
            $update_tasker = $db->prepare("UPDATE taskers SET average_rating = ?, total_reviews = ? WHERE tasker_id = ?");
            $update_tasker->bind_param("dii", $new_avg, $new_total, $tasker_id);

            if (!$update_tasker->execute()) {
                throw new Exception("Failed to update tasker rating: " . $db->error);
            }

            // Delete the booking
            $delete_booking = $db->prepare("DELETE FROM bookings WHERE booking_id = ?");
            $delete_booking->bind_param("i", $booking_id);

            if (!$delete_booking->execute()) {
                throw new Exception("Failed to delete booking: " . $db->error);
            }

            // Get tasker's user_id for notification
            $tasker_user_query = $db->prepare("SELECT user_id FROM taskers WHERE tasker_id = ?");
            $tasker_user_query->bind_param("i", $tasker_id);
            $tasker_user_query->execute();
            $tasker_user_result = $tasker_user_query->get_result();

            if ($tasker_user_result && $tasker_user_result->num_rows > 0) {
                $tasker_user_id = $tasker_user_result->fetch_assoc()['user_id'];

                // Create notification for tasker about the review
                $notification_message = "A client has left you a " . $rating . "-star review.";
                $notification_stmt = $db->prepare("INSERT INTO notifications (user_id, message, related_to, related_id) 
                                                 VALUES (?, ?, 'review', ?)");
                $review_id = $db->insert_id; // Get the ID of the inserted review
                $notification_stmt->bind_param("isi", $tasker_user_id, $notification_message, $review_id);
                $notification_stmt->execute();
            }

            // Commit transaction
            $db->commit();

            $success_message = "Thank you! Your review has been submitted successfully.";
        } catch (Exception $e) {
            // Roll back transaction on error
            $db->rollback();
            $error_message = $e->getMessage();
        }
    }
}

// If we have a booking success message from redirect
if (isset($_SESSION['booking_success'])) {
    $success_message = $_SESSION['booking_success'];
    unset($_SESSION['booking_success']);
}

// Fetch bookings for this user
if ($db_connected) {
    $user_id = $_SESSION['user_id'];

    // Query to get all bookings for this user with tasker info
    $query = "
        SELECT 
            b.booking_id,
            b.booking_date,
            b.time_slot,
            b.task_description,
            b.address,
            b.contact_info,
            b.status,
            b.created_at,
            t.tasker_id,
            t.hourly_rate,
            u.user_id AS tasker_user_id,
            u.first_name,
            u.last_name,
            u.profile_image,
            c.name AS category_name
        FROM 
            bookings b
        JOIN 
            taskers t ON b.tasker_id = t.tasker_id
        JOIN 
            users u ON t.user_id = u.user_id
        JOIN 
            categories c ON t.category_id = c.category_id
        WHERE 
            b.client_id = ?
        ORDER BY 
            b.created_at DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($booking = $result->fetch_assoc()) {
            $bookings[] = $booking;
        }
    }
}

// Helper function to format date
function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending':
            return 'bg-warning text-dark';
        case 'accepted':
            return 'bg-primary';
        case 'completed':
            return 'bg-success';
        case 'rejected':
            return 'bg-danger';
        case 'cancelled':
            return 'bg-secondary';
        default:
            return 'bg-secondary';
    }
}

// Helper function to get status display text
function getStatusDisplayText($status) {
    switch ($status) {
        case 'pending':
            return 'Pending';
        case 'accepted':
            return 'Accepted';
        case 'completed':
            return 'Completed';
        case 'rejected':
            return 'Declined';
        case 'cancelled':
            return 'Cancelled';
        default:
            return ucfirst($status);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - TaskBuddy</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="landing.css">
    <style>
        .tasks-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 20px;
        }
        .task-card {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.35s ease;
            border: none;
        }
        .task-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(45, 124, 124, 0.15);
        }
        .task-header {
            background-color: #f7f3ed;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .tasker-info {
            display: flex;
            align-items: center;
        }
        .tasker-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 3px solid #fff;
            box-shadow: 0 0 0 4px rgba(45, 124, 124, 0.2);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .status-pending {
            background-color: #FF8035;
            color: white;
        }
        .status-accepted {
            background: linear-gradient(45deg, #2D7C7C 0%, #48a3a3 100%);
            color: white;
        }
        .status-completed {
            background: linear-gradient(45deg, #5a3e20 0%, #8b6b4c 100%);
            color: white;
        }
        .status-rejected {
            background-color: #e74a3b;
            color: white;
        }
        .task-body {
            padding: 20px;
        }
        .task-info {
            margin-bottom: 15px;
        }
        .task-info strong {
            font-weight: 600;
            color: #2D7C7C;
        }
        .task-actions {
            display: flex;
            justify-content: flex-end;
            padding: 15px 20px;
            border-top: 1px solid rgba(90, 62, 32, 0.1);
            gap: 10px;
        }
        .task-actions .btn {
            margin-left: 10px;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        .empty-state i {
            font-size: 50px;
            margin-bottom: 15px;
            display: block;
            color: #d1d3e2;
        }
        .section-title {
            position: relative;
            padding-bottom: 20px;
        }
        .section-title:after {
            content: "";
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            bottom: 0;
            height: 4px;
            width: 180px;
            background: linear-gradient(to right, #5a3e20, #2D7C7C);
            border-radius: 2px;
        }
        #toTopBtn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 99;
            border: none;
            outline: none;
            background-color: #2D7C7C;
            color: white;
            cursor: pointer;
            padding: 10px 15px;
            border-radius: 50px;
            font-size: 18px;
            transition: all 0.3s ease;
        }
        #toTopBtn:hover {
            background-color: #5a3e20;
        }

        /* Rating stars styling */
        .rating-stars {
            display: flex;
            flex-direction: row-reverse;
            justify-content: center;
        }

        .rating-stars input {
            display: none;
        }

        .rating-stars label {
            cursor: pointer;
            font-size: 30px;
            color: #ccc;
            padding: 0 5px;
        }

        .rating-stars label:hover,
        .rating-stars label:hover ~ label,
        .rating-stars input:checked ~ label {
            color: #FFD700;
        }

        .review-btn {
            background-color: #8b6b4c;
            color: white;
            border: none;
        }

        .review-btn:hover {
            background-color: #5a3e20;
            color: white;
        }

        .rating-error {
            color: #dc3545;
            font-size: 14px;
            display: none;
            margin-top: 5px;
        }

        /* Custom modal overlay styling */
        .custom-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            overflow: auto;
            cursor: default;
        }

        .custom-modal-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100%;
            padding: 40px 0;
        }

        .custom-modal-content {
            position: relative;
            width: 90%;
            max-width: 500px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            cursor: default;
            animation: fadeInUp 0.3s;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .custom-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }

        .custom-modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .custom-modal-close {
            border: none;
            background: transparent;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            margin: 0;
            line-height: 1;
            color: #6c757d;
        }

        .custom-modal-close:hover {
            color: #000;
        }

        .custom-modal-body {
            padding: 20px;
        }

        .custom-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 15px 20px;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>

<section class="navigation-bar">
    <div class="container">
        <header class="d-flex flex-wrap justify-content-center py-3 mb-0">
            <a href="<?php echo htmlspecialchars($homeLink, ENT_QUOTES, 'UTF-8'); ?>"
               class="d-flex align-items-center mb-3 mb-md-0 me-md-auto link-body-emphasis text-decoration-none">
                <svg class="bi me-2" width="40" height="32" aria-hidden="true"><use xlink:href="#bootstrap"></use></svg>
                <span class="fs-3">Task<span class="buddy">Buddy</span></span>
            </a>


            <ul class="nav nav-pills">
                <li class="nav-item"><a href="task_status.php" class="nav-link active">Tasks Updates & Status</a></li>
                <li class="nav-item"><a href="services.php" class="nav-link">Services</a></li>

                <li class="nav-item"><a href="logout.php" class="nav-link">Sign Out</a></li>
            </ul>
        </header>
    </div>
    <div class="border-container">
        <div class="border-line"></div>
    </div>
</section>

<div class="container tasks-container">
    <h2 class="text-center mb-4 section-title">My Task Status</h2>

    <?php if (!$db_connected): ?>
        <div class="alert alert-warning" role="alert">
            <h4 class="alert-heading">Unable to connect to database</h4>
            <p>We're currently experiencing technical difficulties. Please try again later.</p>
            <?php if (!empty($db_error_message)): ?>
                <hr>
                <p class="mb-0 small text-muted"><?php echo h($db_error_message); ?></p>
            <?php endif; ?>
        </div>
    <?php else: ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo h($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo h($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (count($bookings) > 0): ?>
            <?php foreach($bookings as $booking): ?>
                <div class="task-card">
                    <div class="task-header">
                        <div class="tasker-info">
                            <img src="<?php echo h($booking['profile_image']); ?>" alt="Tasker" class="tasker-avatar">
                            <div>
                                <h5 class="mb-0"><?php echo h($booking['first_name'] . ' ' . $booking['last_name']); ?></h5>
                                <small class="text-muted"><?php echo h($booking['category_name']); ?> · $<?php echo h(number_format($booking['hourly_rate'], 2)); ?>/hr</small>
                            </div>
                        </div>
                        <span class="status-badge <?php echo getStatusBadgeClass($booking['status']); ?>"><?php echo getStatusDisplayText($booking['status']); ?></span>
                    </div>
                    <div class="task-body">
                        <div class="task-info">
                            <p><strong>Date:</strong> <?php echo formatDate($booking['booking_date']); ?></p>
                            <p><strong>Time:</strong> <?php echo h($booking['time_slot']); ?></p>
                            <p><strong>Address:</strong> <?php echo h($booking['address']); ?></p>
                            <p><strong>Booked on:</strong> <?php echo date('M j, Y', strtotime($booking['created_at'])); ?></p>
                        </div>
                        <div class="task-description">
                            <strong>Task Description:</strong>
                            <p><?php echo h($booking['task_description']); ?></p>
                        </div>
                    </div>

                    <?php if ($booking['status'] === 'pending' || $booking['status'] === 'accepted'): ?>
                        <div class="task-actions">
                            <form method="post" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                <input type="hidden" name="booking_id" value="<?php echo h($booking['booking_id']); ?>">
                                <input type="hidden" name="cancel_booking" value="1">
                                <button type="submit" class="btn btn-light">Cancel Booking</button>
                            </form>
                            <a href="TaskerTemplate.php?id=<?php echo h($booking['tasker_user_id']); ?>" class="btn btn-primary">View Tasker Profile</a>
                        </div>
                    <?php elseif ($booking['status'] === 'completed'): ?>
                        <div class="task-actions">
                            <button type="button" class="btn review-btn" onclick="openCustomModal(<?php echo h($booking['booking_id']); ?>)">
                                <i class="bi bi-star-fill me-1"></i> Leave a Review
                            </button>
                            <a href="TaskerTemplate.php?id=<?php echo h($booking['tasker_user_id']); ?>" class="btn btn-primary">View Tasker Profile</a>
                        </div>
                    <?php else: ?>
                        <div class="task-actions">
                            <a href="TaskerTemplate.php?id=<?php echo h($booking['tasker_user_id']); ?>" class="btn btn-primary">View Tasker Profile</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-clipboard-check"></i>
                <h4>No tasks yet</h4>
                <p>You haven't booked any tasks yet. Browse services to find a tasker.</p>
                <a href="services.php" class="btn btn-primary mt-3">Browse Services</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Custom Modal for Reviews -->
<?php foreach($bookings as $booking): ?>
    <?php if ($booking['status'] === 'completed'): ?>
        <div id="customModal<?php echo h($booking['booking_id']); ?>" class="custom-modal-overlay">
            <div class="custom-modal-container">
                <div class="custom-modal-content">
                    <div class="custom-modal-header">
                        <h5 class="custom-modal-title">Review <?php echo h($booking['first_name'] . ' ' . $booking['last_name']); ?></h5>
                        <button type="button" class="custom-modal-close" onclick="closeCustomModal(<?php echo h($booking['booking_id']); ?>)">×</button>
                    </div>
                    <form method="post" id="reviewForm<?php echo h($booking['booking_id']); ?>" onsubmit="return validateCustomRating(<?php echo h($booking['booking_id']); ?>)">
                        <div class="custom-modal-body">
                            <input type="hidden" name="booking_id" value="<?php echo h($booking['booking_id']); ?>">
                            <input type="hidden" name="tasker_id" value="<?php echo h($booking['tasker_id']); ?>">

                            <div class="mb-4 text-center">
                                <h6>How would you rate <?php echo h($booking['first_name']); ?>'s service?</h6>
                                <div class="rating-stars my-3">
                                    <input type="radio" id="star5-<?php echo h($booking['booking_id']); ?>" name="rating" value="5">
                                    <label for="star5-<?php echo h($booking['booking_id']); ?>"><i class="bi bi-star-fill"></i></label>

                                    <input type="radio" id="star4-<?php echo h($booking['booking_id']); ?>" name="rating" value="4">
                                    <label for="star4-<?php echo h($booking['booking_id']); ?>"><i class="bi bi-star-fill"></i></label>

                                    <input type="radio" id="star3-<?php echo h($booking['booking_id']); ?>" name="rating" value="3">
                                    <label for="star3-<?php echo h($booking['booking_id']); ?>"><i class="bi bi-star-fill"></i></label>

                                    <input type="radio" id="star2-<?php echo h($booking['booking_id']); ?>" name="rating" value="2">
                                    <label for="star2-<?php echo h($booking['booking_id']); ?>"><i class="bi bi-star-fill"></i></label>

                                    <input type="radio" id="star1-<?php echo h($booking['booking_id']); ?>" name="rating" value="1">
                                    <label for="star1-<?php echo h($booking['booking_id']); ?>"><i class="bi bi-star-fill"></i></label>
                                </div>
                                <div class="rating-error" id="ratingError<?php echo h($booking['booking_id']); ?>">Please select a rating</div>
                            </div>

                            <div class="mb-3">
                                <label for="comment<?php echo h($booking['booking_id']); ?>" class="form-label">Comment (Optional)</label>
                                <textarea class="form-control" id="comment<?php echo h($booking['booking_id']); ?>" name="comment" rows="4" placeholder="Share your experience with this tasker..."></textarea>
                            </div>
                        </div>
                        <div class="custom-modal-footer">
                            <button type="button" class="btn btn-light" onclick="closeCustomModal(<?php echo h($booking['booking_id']); ?>)">Cancel</button>
                            <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<script>
    // Functions to control custom modal
    function openCustomModal(bookingId) {
        const modal = document.getElementById(`customModal${bookingId}`);
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    }

    function closeCustomModal(bookingId) {
        const modal = document.getElementById(`customModal${bookingId}`);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    // Validate rating selection before form submission
    function validateCustomRating(bookingId) {
        const ratingInputs = document.querySelectorAll(`input[name="rating"]:checked`);
        const errorElement = document.getElementById(`ratingError${bookingId}`);

        if (ratingInputs.length === 0) {
            errorElement.style.display = 'block';
            return false;
        }

        errorElement.style.display = 'none';
        return true;
    }

    // Close modal when clicking outside the content
    document.addEventListener('DOMContentLoaded', function() {
        const modals = document.querySelectorAll('.custom-modal-overlay');

        modals.forEach(modal => {
            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    const bookingId = modal.id.replace('customModal', '');
                    closeCustomModal(bookingId);
                }
            });
        });

        // Handle escape key to close modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const visibleModal = document.querySelector('.custom-modal-overlay[style="display: block;"]');
                if (visibleModal) {
                    const bookingId = visibleModal.id.replace('customModal', '');
                    closeCustomModal(bookingId);
                }
            }
        });
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
<script src="sharedScripts.js"></script>
</body>
</html>