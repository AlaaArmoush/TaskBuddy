<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: /signIn.php");
    exit();
}

// Database connection
$db_connected = false;
$stats = [];
$recent_bookings = [];
$top_taskers = [];
$revenue_data = [];

try {
    $db = new mysqli("localhost", "root", "", "taskbuddy");
    if ($db->connect_error) {
        throw new Exception("Connection failed: " . $db->connect_error);
    }
    $db_connected = true;

    // Get overall statistics
    // Total users
    $result = $db->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 0");
    $stats['total_users'] = $result->fetch_assoc()['total'];

    // Total taskers
    $result = $db->query("SELECT COUNT(*) as total FROM users WHERE is_tasker = 1");
    $stats['total_taskers'] = $result->fetch_assoc()['total'];

    // Total clients
    $result = $db->query("SELECT COUNT(*) as total FROM users WHERE is_tasker = 0 AND is_admin = 0");
    $stats['total_clients'] = $result->fetch_assoc()['total'];

    // Total bookings
    $result = $db->query("SELECT COUNT(*) as total FROM bookings");
    $stats['total_bookings'] = $result->fetch_assoc()['total'];

    // Completed bookings
    $result = $db->query("SELECT COUNT(*) as total FROM bookings WHERE status = 'completed'");
    $stats['completed_bookings'] = $result->fetch_assoc()['total'];

    // Pending bookings
    $result = $db->query("SELECT COUNT(*) as total FROM bookings WHERE status = 'pending'");
    $stats['pending_bookings'] = $result->fetch_assoc()['total'];

    // Total categories
    $result = $db->query("SELECT COUNT(*) as total FROM categories");
    $stats['total_categories'] = $result->fetch_assoc()['total'];

    // Average rating across all taskers
    $result = $db->query("SELECT AVG(average_rating) as avg_rating FROM taskers WHERE total_reviews > 0");
    $stats['avg_rating'] = number_format($result->fetch_assoc()['avg_rating'], 2);

    // Estimated revenue (sum of completed bookings * hourly rates)
    $result = $db->query("
        SELECT SUM(t.hourly_rate * 2) as revenue 
        FROM bookings b 
        JOIN taskers t ON b.tasker_id = t.tasker_id 
        WHERE b.status = 'completed'
    ");
    $stats['estimated_revenue'] = number_format($result->fetch_assoc()['revenue'] ?? 0, 2);

    // Recent bookings
    $result = $db->query("
        SELECT b.*, 
               u_client.first_name as client_fname, u_client.last_name as client_lname,
               u_tasker.first_name as tasker_fname, u_tasker.last_name as tasker_lname,
               t.hourly_rate
        FROM bookings b
        JOIN users u_client ON b.client_id = u_client.user_id
        JOIN taskers t ON b.tasker_id = t.tasker_id
        JOIN users u_tasker ON t.user_id = u_tasker.user_id
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    while ($row = $result->fetch_assoc()) {
        $recent_bookings[] = $row;
    }

    // Top taskers by rating
    $result = $db->query("
        SELECT t.*, u.first_name, u.last_name, u.profile_image, c.name as category_name
        FROM taskers t
        JOIN users u ON t.user_id = u.user_id
        JOIN categories c ON t.category_id = c.category_id
        WHERE t.total_reviews > 0
        ORDER BY t.average_rating DESC, t.total_reviews DESC
        LIMIT 5
    ");
    while ($row = $result->fetch_assoc()) {
        $top_taskers[] = $row;
    }

    // Monthly bookings for chart
    $result = $db->query("
        SELECT 
    DATE_FORMAT(booking_date, '%Y-%m') as month,
    COUNT(*) as booking_count,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count
FROM bookings
WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
GROUP BY month
ORDER BY month

    ");
    while ($row = $result->fetch_assoc()) {
        $revenue_data[] = $row;
    }

    // Category distribution
    $result = $db->query("
        SELECT c.name, COUNT(t.tasker_id) as tasker_count
        FROM categories c
        LEFT JOIN taskers t ON c.category_id = t.category_id
        GROUP BY c.category_id
        ORDER BY tasker_count DESC
    ");
    $category_data = [];
    while ($row = $result->fetch_assoc()) {
        $category_data[] = $row;
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Function to safely escape output
function h($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Function to get status badge class
function getStatusBadgeClass($status)
{
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

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TaskBuddy</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/css/landing.css">
    <style>
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid rgba(217, 197, 169, 0.2);
            margin-bottom: 20px;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(45, 124, 124, 0.1);
        }

        .stats-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #5a3e20;
            margin-bottom: 0;
        }

        .stats-card p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0;
        }

        .stats-card i {
            font-size: 2.5rem;
            color: #2D7C7C;
            opacity: 0.8;
        }

        .section-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            border: 1px solid rgba(217, 197, 169, 0.2);
        }

        .section-card.recent-bookings {
            min-height: 676px;
        }

        .section-card h4 {
            color: #5a3e20;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(45, 124, 124, 0.2);
        }

        .dashboard-table {
            font-size: 0.9rem;
        }

        .dashboard-table th {
            background-color: #f7f3ed;
            color: #5a3e20;
            font-weight: 600;
            border-bottom: 2px solid #d9c5a9;
        }

        .dashboard-table td {
            vertical-align: middle;
            padding: 12px 8px;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }

        .section-card canvas {
            max-height: 300px !important;
            height: 300px !important;
        }

        .top-tasker-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .top-tasker-item:hover {
            background-color: #f7f3ed;
        }

        .top-tasker-item img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
        }

        .top-tasker-item .rating {
            color: #FF8035;
            font-weight: 600;
        }

        .quick-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .quick-actions .btn {
            flex: 1;
            padding: 15px 20px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .admin-nav {
            background-color: #f7f3ed;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .admin-nav .nav-link {
            color: #5a3e20;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 10px;
            margin-right: 10px;
            transition: all 0.3s ease;
        }

        .admin-nav .nav-link:hover {
            background-color: rgba(45, 124, 124, 0.1);
            color: #2D7C7C;
        }

        .admin-nav .nav-link.active {
            background: linear-gradient(45deg, #5a3e20 0%, #8b6b4c 100%);
            color: white;
        }
    </style>
</head>

<body>

    <!-- Navigation Bar -->
    <section class="navigation-bar">
    <div class="container">
        <header class="d-flex flex-wrap justify-content-center py-3 mb-0">
            <a href="admin_dashboard.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto link-body-emphasis text-decoration-none">
                <span class="fs-3">
                    Task<span class="buddy">Buddy</span> Admin
                    <?php if (isset($_SESSION['first_name'])): ?>
                        - <?php echo h($_SESSION['first_name']); ?>
                    <?php endif; ?>
                </span>
            </a>
            <ul class="nav nav-pills">
                <li class="nav-item"><a href="admin_dashboard.php" class="nav-link active">Dashboard</a></li>
                <li class="nav-item"><a href="admin_users.php" class="nav-link">Users</a></li>
                <li class="nav-item"><a href="admin_categories.php" class="nav-link">Categories</a></li>
                <li class="nav-item"><a href="admin_bookings.php" class="nav-link">Bookings</a></li>
                <li class="nav-item"><a href="logout.php" class="nav-link">Sign Out</a></li>
            </ul>
        </header>
    </div>
    <div class="border-container">
        <div class="border-line"></div>
    </div>
</section>

    <div class="dashboard-container">
        <h2 class="text-center mb-4">Admin Dashboard</h2>

        <?php if (!$db_connected): ?>
            <div class="alert alert-danger">Database connection failed. Please check your connection.</div>
        <?php else: ?>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="admin_categories.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Add Category
                </a>
                <a href="admin_users.php" class="btn btn-secondary">
                    <i class="bi bi-people me-2"></i>Manage Users
                </a>
                <a href="admin_bookings.php" class="btn btn-light">
                    <i class="bi bi-calendar-check me-2"></i>View Bookings
                </a>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo h($stats['total_users']); ?></h3>
                                <p>Total Users</p>
                            </div>
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo h($stats['total_taskers']); ?></h3>
                                <p>Active Taskers</p>
                            </div>
                            <i class="bi bi-person-badge-fill"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo h($stats['total_bookings']); ?></h3>
                                <p>Total Bookings</p>
                            </div>
                            <i class="bi bi-calendar-check-fill"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3>$<?php echo h($stats['estimated_revenue']); ?></h3>
                                <p>Est. Revenue</p>
                            </div>
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo h($stats['completed_bookings']); ?></h3>
                                <p>Completed Tasks</p>
                            </div>
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo h($stats['pending_bookings']); ?></h3>
                                <p>Pending Tasks</p>
                            </div>
                            <i class="bi bi-clock-fill"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo h($stats['total_categories']); ?></h3>
                                <p>Categories</p>
                            </div>
                            <i class="bi bi-grid-fill"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo h($stats['avg_rating']); ?> <i class="bi bi-star-fill"
                                        style="font-size: 1.5rem; color: #FF8035;"></i></h3>
                                <p>Avg. Rating</p>
                            </div>
                            <i class="bi bi-star-half"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Bookings -->
                <div class="col-md-8">
                    <div class="section-card recent-bookings">
                        <h4>Recent Bookings</h4>
                        <div class="table-responsive">
                            <table class="table dashboard-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Client</th>
                                        <th>Tasker</th>
                                        <th>Status</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_bookings as $booking): ?>
                                        <tr>
                                            <td><?php echo date('M j', strtotime($booking['booking_date'])); ?></td>
                                            <td><?php echo h($booking['client_fname'] . ' ' . $booking['client_lname']); ?></td>
                                            <td><?php echo h($booking['tasker_fname'] . ' ' . $booking['tasker_lname']); ?></td>
                                            <td>
                                                <span class="badge <?php echo getStatusBadgeClass($booking['status']); ?>">
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </span>
                                            </td>
                                            <td>$<?php echo h(number_format($booking['hourly_rate'] * 2, 2)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Top Taskers -->
                <div class="col-md-4">
                    <div class="section-card">
                        <h4>Top Rated Taskers</h4>
                        <?php foreach ($top_taskers as $tasker): ?>
                            <div class="top-tasker-item">
                                <img src="<?php echo h($tasker['profile_image']); ?>"
                                    alt="<?php echo h($tasker['first_name']); ?>">
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?php echo h($tasker['first_name'] . ' ' . $tasker['last_name']); ?>
                                    </div>
                                    <div class="text-muted small"><?php echo h($tasker['category_name']); ?></div>
                                    <div class="rating">
                                        <i class="bi bi-star-fill"></i> <?php echo h($tasker['average_rating']); ?>
                                        <span class="text-muted">(<?php echo h($tasker['total_reviews']); ?> reviews)</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="row">
                <div class="col-md-8">
                    <div class="section-card">
                        <h4>Booking Trends</h4>
                        <div class="chart-container">
                            <canvas id="bookingChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="section-card">
                        <h4>Category Distribution</h4>
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Booking Trends Chart
        const bookingCtx = document.getElementById('bookingChart').getContext('2d');
        const bookingChart = new Chart(bookingCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($revenue_data, 'month')); ?>,
                datasets: [{
                    label: 'Total Bookings',
                    data: <?php echo json_encode(array_column($revenue_data, 'booking_count')); ?>,
                    borderColor: '#2D7C7C',
                    backgroundColor: 'rgba(45, 124, 124, 0.1)',
                    tension: 0.3
                }, {
                    label: 'Completed',
                    data: <?php echo json_encode(array_column($revenue_data, 'completed_count')); ?>,
                    borderColor: '#5a3e20',
                    backgroundColor: 'rgba(90, 62, 32, 0.1)',
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Category Distribution Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($category_data, 'name')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($category_data, 'tasker_count')); ?>,
                    backgroundColor: [
                        '#2D7C7C',
                        '#5a3e20',
                        '#FF8035',
                        '#d9c5a9',
                        '#48a3a3',
                        '#8b6b4c',
                        '#276e6e',
                        '#3d9898',
                        '#FF6B6B',
                        '#4ECDC4',
                        '#45B7D1',
                        '#96CEB4',
                        '#FFEAA7',
                        '#DDA0DD',
                        '#98D8C8',
                        '#F7DC6F',
                        '#BB8FCE',
                        '#85C1E9',
                        '#F8C471',
                        '#82E0AA'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>

</html>