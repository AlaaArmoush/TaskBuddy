<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: index.php");
    exit();
}

// Database connection
$db_connected = false;
$bookings = [];
$success_message = '';
$error_message = '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

try {
    $db = new mysqli("localhost", "root", "", "taskbuddy");
    if ($db->connect_error) {
        throw new Exception("Connection failed: " . $db->connect_error);
    }
    $db_connected = true;

    // Handle booking actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['update_status'])) {
            $booking_id = intval($_POST['booking_id']);
            $new_status = $_POST['new_status'];

            $stmt = $db->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
            $stmt->bind_param("si", $new_status, $booking_id);

            if ($stmt->execute()) {
                $success_message = "Booking status updated successfully!";

                // Log admin action
                $log_stmt = $db->prepare("INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'UPDATE_BOOKING', ?)");
                $desc = "Updated booking ID " . $booking_id . " status to " . $new_status;
                $log_stmt->bind_param("is", $_SESSION['user_id'], $desc);
                $log_stmt->execute();
            } else {
                $error_message = "Error updating booking: " . $db->error;
            }
        }

        if (isset($_POST['delete_booking'])) {
            $booking_id = intval($_POST['booking_id']);

            $stmt = $db->prepare("DELETE FROM bookings WHERE booking_id = ?");
            $stmt->bind_param("i", $booking_id);

            if ($stmt->execute()) {
                $success_message = "Booking deleted successfully!";

                // Log admin action
                $log_stmt = $db->prepare("INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'DELETE_BOOKING', ?)");
                $desc = "Deleted booking ID " . $booking_id;
                $log_stmt->bind_param("is", $_SESSION['user_id'], $desc);
                $log_stmt->execute();
            } else {
                $error_message = "Error deleting booking: " . $db->error;
            }
        }
    }

    // Build query for bookings
    $query = "
        SELECT b.*,
               u_client.first_name as client_fname, u_client.last_name as client_lname,
               u_client.email as client_email, u_client.profile_image as client_image,
               u_tasker.first_name as tasker_fname, u_tasker.last_name as tasker_lname,
               u_tasker.email as tasker_email, u_tasker.profile_image as tasker_image,
               t.hourly_rate, c.name as category_name
        FROM bookings b
        JOIN users u_client ON b.client_id = u_client.user_id
        JOIN taskers t ON b.tasker_id = t.tasker_id
        JOIN users u_tasker ON t.user_id = u_tasker.user_id
        JOIN categories c ON t.category_id = c.category_id
        WHERE b.booking_date BETWEEN ? AND ?
    ";

    $params = [$date_from, $date_to];
    $types = "ss";

    // Apply status filter
    if ($filter_status !== 'all') {
        $query .= " AND b.status = ?";
        $params[] = $filter_status;
        $types .= "s";
    }

    $query .= " ORDER BY b.created_at DESC";

    // Execute query
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }

    // Get booking statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM bookings
        WHERE booking_date BETWEEN ? AND ?
    ";

    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->bind_param("ss", $date_from, $date_to);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Function to safely escape output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending': return 'bg-warning text-dark';
        case 'accepted': return 'bg-primary';
        case 'completed': return 'bg-success';
        case 'rejected': return 'bg-danger';
        case 'cancelled': return 'bg-secondary';
        default: return 'bg-secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings Management - TaskBuddy Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="landing.css">
    <style>
        .bookings-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            border: 1px solid rgba(217, 197, 169, 0.2);
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .stat-card:hover, .stat-card.active {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(45, 124, 124, 0.15);
            border-color: #2D7C7C;
        }

        .stat-card h4 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #5a3e20;
            margin-bottom: 5px;
        }

        .stat-card p {
            font-size: 0.9rem;
            color: #666;
            margin: 0;
        }

        .booking-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(217, 197, 169, 0.2);
        }

        .booking-table th {
            background-color: #f7f3ed;
            color: #5a3e20;
            font-weight: 600;
            border-bottom: 2px solid #d9c5a9;
            padding: 12px;
            font-size: 0.9rem;
        }

        .booking-table td {
            vertical-align: middle;
            padding: 12px;
            font-size: 0.9rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }

        .booking-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 10px;
        }

        .action-dropdown {
            min-width: 150px;
        }

        .export-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-bottom: 20px;
        }

        .export-buttons .btn {
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<section class="navigation-bar">
    <div class="container">
        <header class="d-flex flex-wrap justify-content-center py-3 mb-0">
            <a href="admin_dashboard.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto link-body-emphasis text-decoration-none">
                <span class="fs-3">Task<span class="buddy">Buddy</span> Admin</span>
            </a>
            <ul class="nav nav-pills">
                <li class="nav-item"><a href="admin_dashboard.php" class="nav-link">Dashboard</a></li>
                <li class="nav-item"><a href="admin_users.php" class="nav-link">Users</a></li>
                <li class="nav-item"><a href="admin_categories.php" class="nav-link">Categories</a></li>
                <li class="nav-item"><a href="admin_bookings.php" class="nav-link active">Bookings</a></li>
                <li class="nav-item"><a href="logout.php" class="nav-link">Sign Out</a></li>
            </ul>
        </header>
    </div>
    <div class="border-container">
        <div class="border-line"></div>
    </div>
</section>

<div class="bookings-container">
    <h2 class="text-center mb-4">Bookings Management</h2>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo h($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo h($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!$db_connected): ?>
        <div class="alert alert-danger">Database connection failed. Please check your connection.</div>
    <?php else: ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo h($date_from); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo h($date_to); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary d-block w-100">
                        <i class="bi bi-funnel"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <a href="?status=all&date_from=<?php echo h($date_from); ?>&date_to=<?php echo h($date_to); ?>" class="text-decoration-none">
                <div class="stat-card <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
                    <h4><?php echo h($stats['total']); ?></h4>
                    <p>Total Bookings</p>
                </div>
            </a>
            <a href="?status=pending&date_from=<?php echo h($date_from); ?>&date_to=<?php echo h($date_to); ?>" class="text-decoration-none">
                <div class="stat-card <?php echo $filter_status === 'pending' ? 'active' : ''; ?>">
                    <h4><?php echo h($stats['pending']); ?></h4>
                    <p>Pending</p>
                </div>
            </a>
            <a href="?status=accepted&date_from=<?php echo h($date_from); ?>&date_to=<?php echo h($date_to); ?>" class="text-decoration-none">
                <div class="stat-card <?php echo $filter_status === 'accepted' ? 'active' : ''; ?>">
                    <h4><?php echo h($stats['accepted']); ?></h4>
                    <p>Accepted</p>
                </div>
            </a>
            <a href="?status=completed&date_from=<?php echo h($date_from); ?>&date_to=<?php echo h($date_to); ?>" class="text-decoration-none">
                <div class="stat-card <?php echo $filter_status === 'completed' ? 'active' : ''; ?>">
                    <h4><?php echo h($stats['completed']); ?></h4>
                    <p>Completed</p>
                </div>
            </a>
            <a href="?status=cancelled&date_from=<?php echo h($date_from); ?>&date_to=<?php echo h($date_to); ?>" class="text-decoration-none">
                <div class="stat-card <?php echo $filter_status === 'cancelled' ? 'active' : ''; ?>">
                    <h4><?php echo h($stats['cancelled']); ?></h4>
                    <p>Cancelled</p>
                </div>
            </a>
            <a href="?status=rejected&date_from=<?php echo h($date_from); ?>&date_to=<?php echo h($date_to); ?>" class="text-decoration-none">
                <div class="stat-card <?php echo $filter_status === 'rejected' ? 'active' : ''; ?>">
                    <h4><?php echo h($stats['rejected']); ?></h4>
                    <p>Rejected</p>
                </div>
            </a>
        </div>

        <!-- Export Buttons -->
        <div class="export-buttons">
            <button class="btn btn-secondary" onclick="exportToCSV()">
                <i class="bi bi-file-earmark-csv"></i> Export CSV
            </button>
        </div>

        <!-- Bookings Table -->
        <div class="table-responsive booking-table">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Date/Time</th>
                    <th>Client</th>
                    <th>Tasker</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Amount</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach($bookings as $booking): ?>
                    <tr>
                        <td>#<?php echo h($booking['booking_id']); ?></td>
                        <td>
                            <div><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></div>
                            <small class="text-muted"><?php echo h($booking['time_slot']); ?></small>
                        </td>
                        <td>
                            <div class="user-info">
                                <img src="<?php echo h($booking['client_image']); ?>" alt="Client" class="user-avatar">
                                <div>
                                    <div><?php echo h($booking['client_fname'] . ' ' . $booking['client_lname']); ?></div>
                                    <small class="text-muted"><?php echo h($booking['client_email']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="user-info">
                                <img src="<?php echo h($booking['tasker_image']); ?>" alt="Tasker" class="user-avatar">
                                <div>
                                    <div><?php echo h($booking['tasker_fname'] . ' ' . $booking['tasker_lname']); ?></div>
                                    <small class="text-muted"><?php echo h($booking['tasker_email']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?php echo h($booking['category_name']); ?></td>
                        <td>
                                <span class="badge <?php echo getStatusBadgeClass($booking['status']); ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                        </td>
                        <td>$<?php echo h(number_format($booking['hourly_rate'] * 2, 2)); ?></td>

                        <td>
                            <a
                                style="text-decoration: none;"
                                href="#"
                                class="view-details-btn"
                                data-id="<?php echo h($booking['booking_id']); ?>"
                                onclick="toggleDetails(<?php echo h($booking['booking_id']); ?>); return false;">
                                <i class="bi bi-eye"></i> View Details
                            </a>
                        </td>



                    </tr>
                    <tr id="details-<?php echo h($booking['booking_id']); ?>" style="display: none;">
                        <td colspan="8">
                            <div class="booking-details">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Task Description:</h6>
                                        <p><?php echo h($booking['task_description']); ?></p>
                                    </div>
                                    <div class="col-md-3">
                                        <h6>Address:</h6>
                                        <p><?php echo h($booking['address']); ?></p>
                                    </div>
                                    <div class="col-md-3">
                                        <h6>Contact:</h6>
                                        <p><?php echo h($booking['contact_info']); ?></p>
                                        <p class="text-muted">Created: <?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function viewDetails(bookingId) {
        const detailsRow = document.getElementById('details-' + bookingId);
        if (detailsRow.style.display === 'none') {
            detailsRow.style.display = 'table-row';
        } else {
            detailsRow.style.display = 'none';
        }
    }

    function exportToCSV() {
        // Simple CSV export functionality
        const table = document.querySelector('.booking-table table');
        let csv = [];

        // Get headers
        const headers = [];
        table.querySelectorAll('thead th').forEach(th => {
            headers.push(th.textContent.trim());
        });
        csv.push(headers.join(','));

        // Get data
        table.querySelectorAll('tbody tr').forEach(tr => {
            if (!tr.id.startsWith('details-')) {
                const row = [];
                tr.querySelectorAll('td').forEach((td, index) => {
                    if (index < headers.length - 1) { // Skip actions column
                        let text = td.textContent.trim().replace(/,/g, ';');
                        row.push('"' + text + '"');
                    }
                });
                csv.push(row.join(','));
            }
        });

        // Download
        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.setAttribute('hidden', '');
        a.setAttribute('href', url);
        a.setAttribute('download', 'bookings_' + new Date().toISOString().split('T')[0] + '.csv');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
</script>

<script>
    function toggleDetails(id) {
        // find the details row
        const detailsRow = document.getElementById(`details-${id}`);
        // find the trigger link
        const trigger   = document.querySelector(`.view-details-btn[data-id='${id}']`);

        // toggle visibility
        if (!detailsRow || !trigger) return;
        const isHidden = detailsRow.style.display === 'none' || !detailsRow.style.display;

        if (isHidden) {
            detailsRow.style.display = 'table-row';
            trigger.innerHTML     = '<i class="bi bi-eye-slash"></i> Hide Details';
        } else {
            detailsRow.style.display = 'none';
            trigger.innerHTML     = '<i class="bi bi-eye"></i> View Details';
        }
    }
</script>

</body>
</html>