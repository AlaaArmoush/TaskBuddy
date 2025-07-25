<?php
session_start();

$homeLink = 'index.php';
if (isset($_SESSION['user_id'], $_SESSION['is_tasker']) && $_SESSION['is_tasker'] == 1) {
    // Redirect taskers to their own profile template, passing their user_id
    $homeLink = 'TaskerTemplate.php?id=' . intval($_SESSION['user_id']);
}

// Include notification helper
require_once 'notifications_helper.php';

// Check if user is logged in and is a tasker
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_tasker']) || $_SESSION['is_tasker'] != 1) {
    // User is not logged in or not a tasker, redirect to home page
    $_SESSION['login_required'] = "You need to be a tasker to access this page.";
    header("Location: index.php");
    exit;
}

// Database connection
$db_connected = false;
$db_error_message = "";
$pending_requests = [];
$ongoing_tasks = [];
$completed_tasks = [];
$reviews = [];
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

    // Mark notifications as read when tasker views the requests page
    markNotificationsAsRead($db, $user_id, 'booking');

} catch (Exception $e) {
    $db_error_message = $e->getMessage();
    error_log("Database Error: " . $db_error_message);
}

// Function to safely escape output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Process booking status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db_connected) {
    try {
        if (isset($_POST['action']) && isset($_POST['booking_id'])) {
            $booking_id = intval($_POST['booking_id']);
            $action = $_POST['action'];

            // Get the user_id and check if this tasker actually owns this booking
            $user_id = $_SESSION['user_id'];
            $check_query = $db->prepare("
                SELECT b.booking_id, b.client_id 
                FROM bookings b 
                JOIN taskers t ON b.tasker_id = t.tasker_id 
                WHERE b.booking_id = ? AND t.user_id = ?
            ");
            $check_query->bind_param("ii", $booking_id, $user_id);
            $check_query->execute();
            $check_result = $check_query->get_result();

            if ($check_result && $check_result->num_rows > 0) {
                $booking = $check_result->fetch_assoc();
                $client_id = $booking['client_id'];

                // Update the booking status based on the action
                if ($action === 'accept') {
                    $status = 'accepted';
                    $notification_message = "Your booking has been accepted by the tasker.";
                } elseif ($action === 'reject') {
                    $status = 'rejected';
                    $notification_message = "Your booking has been rejected by the tasker.";
                } elseif ($action === 'complete') {
                    $status = 'completed';
                    $notification_message = "Your task has been marked as completed by the tasker. Please leave a review!";
                } else {
                    throw new Exception("Invalid action.");
                }

                // Update the booking status
                $update_stmt = $db->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
                $update_stmt->bind_param("si", $status, $booking_id);

                if ($update_stmt->execute()) {
                    // Create a notification for the client
                    $notification_stmt = $db->prepare("INSERT INTO notifications (user_id, message, related_to, related_id) 
                                                     VALUES (?, ?, 'booking', ?)");
                    $notification_stmt->bind_param("isi", $client_id, $notification_message, $booking_id);
                    $notification_stmt->execute();

                    $success_message = "Booking status updated successfully.";
                } else {
                    $error_message = "Failed to update booking status: " . $db->error;
                }
            } else {
                $error_message = "You don't have permission to update this booking.";
            }
        }
    } catch (Exception $e) {
        $error_message = "Error processing action: " . $e->getMessage();
    }
}

// Fetch booking requests for this tasker
if ($db_connected) {
    $user_id = $_SESSION['user_id'];

    // First get the tasker_id
    $tasker_query = $db->prepare("SELECT tasker_id FROM taskers WHERE user_id = ?");
    $tasker_query->bind_param("i", $user_id);
    $tasker_query->execute();
    $tasker_result = $tasker_query->get_result();

    if ($tasker_result && $tasker_result->num_rows > 0) {
        $tasker_id = $tasker_result->fetch_assoc()['tasker_id'];

        // Query to get all bookings for this tasker with client info
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
                b.updated_at,
                u.first_name,
                u.last_name,
                u.profile_image
            FROM 
                bookings b
            JOIN 
                users u ON b.client_id = u.user_id
            WHERE 
                b.tasker_id = ?
            ORDER BY 
                b.created_at DESC
        ";

        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $tasker_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            while ($booking = $result->fetch_assoc()) {
                // Sort bookings by status
                if ($booking['status'] === 'pending') {
                    $pending_requests[] = $booking;
                } elseif ($booking['status'] === 'accepted') {
                    $ongoing_tasks[] = $booking;
                } elseif ($booking['status'] === 'completed') {
                    $completed_tasks[] = $booking;
                }
            }
        }

        // Get recent reviews for this tasker
        $reviews_query = "
            SELECT 
                r.review_id,
                r.rating,
                r.comment,
                r.created_at,
                u.first_name,
                u.last_name,
                u.profile_image
            FROM 
                reviews r
            JOIN 
                users u ON r.client_id = u.user_id
            WHERE 
                r.tasker_id = ?
            ORDER BY 
                r.created_at DESC
            LIMIT 5
        ";

        $reviews_stmt = $db->prepare($reviews_query);
        $reviews_stmt->bind_param("i", $tasker_id);
        $reviews_stmt->execute();
        $reviews_result = $reviews_stmt->get_result();

        if ($reviews_result) {
            while ($review = $reviews_result->fetch_assoc()) {
                $reviews[] = $review;
            }
        }
    }
}

// Helper function to format date
function formatDate($date) {
    return date('F j, Y', strtotime($date));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests - TaskBuddy</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/landing.css">
    <style>
        .requests-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 20px;
        }
        .request-card {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.35s ease;
            border: none;
        }
        .request-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(45, 124, 124, 0.15);
        }
        .request-header {
            background-color: #f7f3ed;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .client-info {
            display: flex;
            align-items: center;
        }
        .client-avatar {
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
        .request-body {
            padding: 20px;
        }
        .request-info {
            margin-bottom: 15px;
        }
        .request-info strong {
            font-weight: 600;
            color: #2D7C7C;
        }
        .request-actions {
            display: flex;
            justify-content: flex-end;
            padding: 15px 20px;
            border-top: 1px solid rgba(90, 62, 32, 0.1);
        }
        .request-actions .btn {
            margin-left: 10px;
        }
        .tabs-container {
            margin-bottom: 20px;
        }
        .nav-tabs {
            border-bottom: 1px solid rgba(90, 62, 32, 0.2);
        }
        .nav-tabs .nav-link {
            font-weight: 600;
            color: #5a3e20;
            padding: 10px 20px;
            border: none;
            position: relative;
            transition: all 0.3s ease;
        }
        .nav-tabs .nav-link.active {
            color: #2D7C7C;
            border: none;
            background-color: transparent;
        }
        .nav-tabs .nav-link.active::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(to right, #2D7C7C, #48a3a3);
            border-radius: 2px;
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
        .tab-content {
            background-color: white;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            padding: 20px;
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

        /* Review styles */
        .review-item {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 15px;
            padding: 15px;
            transition: all 0.3s ease;
        }

        .review-item:hover {
            box-shadow: 0 8px 20px rgba(45, 124, 124, 0.1);
        }

        .review-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .reviewer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 12px;
        }

        .review-stars {
            color: #FFD700;
            margin-left: auto;
        }

        .review-date {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }

        .review-comment {
            color: #555;
            font-style: italic;
            margin-top: 10px;
        }

        .review-note {
            background-color: #f8f9fa;
            border-left: 4px solid #2D7C7C;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
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
                <li class="nav-item"><a href="tasker_requests.php" class="nav-link active">Requests</a></li>
                <li class="nav-item"><a href="TaskerTemplate.php?id=<?php echo h($_SESSION['user_id']); ?>" class="nav-link">My Profile</a></li>
                <li class="nav-item"><a href="logout.php" class="nav-link">Sign Out</a></li>
            </ul>
        </header>
    </div>
    <div class="border-container">
        <div class="border-line"></div>
    </div>
</section>

<div class="container requests-container">
    <h2 class="text-center mb-4 section-title">My Task Requests</h2>

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

        <div class="tabs-container">
            <ul class="nav nav-tabs" id="requestTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending"
                            type="button" role="tab" aria-controls="pending" aria-selected="true">
                        Pending Requests <span class="badge bg-warning text-dark"><?php echo count($pending_requests); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="ongoing-tab" data-bs-toggle="tab" data-bs-target="#ongoing"
                            type="button" role="tab" aria-controls="ongoing" aria-selected="false">
                        Ongoing Tasks <span class="badge bg-primary"><?php echo count($ongoing_tasks); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed"
                            type="button" role="tab" aria-controls="completed" aria-selected="false">
                        Completed Tasks <span class="badge bg-success"><?php echo count($completed_tasks); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews"
                            type="button" role="tab" aria-controls="reviews" aria-selected="false">
                        My Reviews <span class="badge bg-secondary"><?php echo count($reviews); ?></span>
                    </button>
                </li>
            </ul>
            <div class="tab-content" id="requestTabsContent">
                <!-- Pending Requests Tab -->
                <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                    <?php if (count($pending_requests) > 0): ?>
                        <?php foreach($pending_requests as $request): ?>
                            <div class="request-card">
                                <div class="request-header">
                                    <div class="client-info">
                                        <img src="<?php echo h($request['profile_image']); ?>" alt="Client" class="client-avatar">
                                        <div>
                                            <h5 class="mb-0"><?php echo h($request['first_name'] . ' ' . $request['last_name']); ?></h5>
                                            <small class="text-muted">Requested: <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></small>
                                        </div>
                                    </div>
                                    <span class="status-badge status-pending">Pending</span>
                                </div>
                                <div class="request-body">
                                    <div class="request-info">
                                        <p><strong>Date:</strong> <?php echo formatDate($request['booking_date']); ?></p>
                                        <p><strong>Time:</strong> <?php echo h($request['time_slot']); ?></p>
                                        <p><strong>Address:</strong> <?php echo h($request['address']); ?></p>
                                        <p><strong>Contact:</strong> <?php echo h($request['contact_info']); ?></p>
                                    </div>
                                    <div class="task-description">
                                        <strong>Task Description:</strong>
                                        <p><?php echo h($request['task_description']); ?></p>
                                    </div>
                                </div>
                                <div class="request-actions">
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo h($request['booking_id']); ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-light" onclick="return confirm('Are you sure you want to reject this booking?')">
                                            Decline
                                        </button>
                                    </form>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo h($request['booking_id']); ?>">
                                        <input type="hidden" name="action" value="accept">
                                        <button type="submit" class="btn btn-primary">Accept</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <h5>No pending requests</h5>
                            <p>You don't have any pending booking requests at the moment.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Ongoing Tasks Tab -->
                <div class="tab-pane fade" id="ongoing" role="tabpanel" aria-labelledby="ongoing-tab">
                    <?php if (count($ongoing_tasks) > 0): ?>
                        <?php foreach($ongoing_tasks as $task): ?>
                            <div class="request-card">
                                <div class="request-header">
                                    <div class="client-info">
                                        <img src="<?php echo h($task['profile_image']); ?>" alt="Client" class="client-avatar">
                                        <div>
                                            <h5 class="mb-0"><?php echo h($task['first_name'] . ' ' . $task['last_name']); ?></h5>
                                            <small class="text-muted">Accepted: <?php echo date('M j, Y', strtotime($task['updated_at'] ?? $task['created_at'])); ?></small>
                                        </div>
                                    </div>
                                    <span class="status-badge status-accepted">Ongoing</span>
                                </div>
                                <div class="request-body">
                                    <div class="request-info">
                                        <p><strong>Date:</strong> <?php echo formatDate($task['booking_date']); ?></p>
                                        <p><strong>Time:</strong> <?php echo h($task['time_slot']); ?></p>
                                        <p><strong>Address:</strong> <?php echo h($task['address']); ?></p>
                                        <p><strong>Contact:</strong> <?php echo h($task['contact_info']); ?></p>
                                    </div>
                                    <div class="task-description">
                                        <strong>Task Description:</strong>
                                        <p><?php echo h($task['task_description']); ?></p>
                                    </div>
                                </div>
                                <div class="request-actions">
                                    <form method="post">
                                        <input type="hidden" name="booking_id" value="<?php echo h($task['booking_id']); ?>">
                                        <input type="hidden" name="action" value="complete">
                                        <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to mark this task as completed? This will prompt the client to leave a review.')">
                                            Mark as Completed
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-calendar-check"></i>
                            <h5>No ongoing tasks</h5>
                            <p>You don't have any ongoing tasks at the moment.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Completed Tasks Tab -->
                <div class="tab-pane fade" id="completed" role="tabpanel" aria-labelledby="completed-tab">
                    <?php if (count($completed_tasks) > 0): ?>
                        <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            Completed tasks will be removed once the client submits a review.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>

                        <?php foreach($completed_tasks as $task): ?>
                            <div class="request-card">
                                <div class="request-header">
                                    <div class="client-info">
                                        <img src="<?php echo h($task['profile_image']); ?>" alt="Client" class="client-avatar">
                                        <div>
                                            <h5 class="mb-0"><?php echo h($task['first_name'] . ' ' . $task['last_name']); ?></h5>
                                            <small class="text-muted">Completed: <?php echo date('M j, Y', strtotime($task['updated_at'] ?? $task['created_at'])); ?></small>
                                        </div>
                                    </div>
                                    <span class="status-badge status-completed">Completed</span>
                                </div>
                                <div class="request-body">
                                    <div class="request-info">
                                        <p><strong>Date:</strong> <?php echo formatDate($task['booking_date']); ?></p>
                                        <p><strong>Time:</strong> <?php echo h($task['time_slot']); ?></p>
                                        <p><strong>Address:</strong> <?php echo h($task['address']); ?></p>
                                    </div>
                                    <div class="task-description">
                                        <strong>Task Description:</strong>
                                        <p><?php echo h($task['task_description']); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-trophy"></i>
                            <h5>No completed tasks</h5>
                            <p>You haven't completed any tasks yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Reviews Tab -->
                <div class="tab-pane fade" id="reviews" role="tabpanel" aria-labelledby="reviews-tab">
                    <?php if (count($reviews) > 0): ?>
                        <h5 class="mb-4">Recent Reviews</h5>
                        <?php foreach($reviews as $review): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <img src="<?php echo h($review['profile_image']); ?>" alt="Reviewer" class="reviewer-avatar">
                                    <div>
                                        <div class="fw-bold"><?php echo h($review['first_name'] . ' ' . $review['last_name']); ?></div>
                                        <div class="review-date"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></div>
                                    </div>
                                    <div class="review-stars">
                                        <?php for ($i = 0; $i < 5; $i++): ?>
                                            <?php if ($i < $review['rating']): ?>
                                                <i class="bi bi-star-fill"></i>
                                            <?php else: ?>
                                                <i class="bi bi-star"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <?php if (!empty($review['comment'])): ?>
                                    <div class="review-comment">
                                        "<?php echo h($review['comment']); ?>"
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="review-note">
                            <p class="mb-0"><strong>Note:</strong> Your current average rating is calculated from all your reviews. Good reviews help you get more booking requests!</p>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-star"></i>
                            <h5>No reviews yet</h5>
                            <p>You haven't received any reviews yet. Complete tasks to get reviews from clients.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
</body>
</html>