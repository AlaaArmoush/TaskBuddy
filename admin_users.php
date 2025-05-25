<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: index.php");
    exit();
}

// Database connection
$db_connected = false;
$users = [];
$success_message = '';
$error_message = '';
$search_term = '';
$filter_type = 'all';

try {
    $db = new mysqli("localhost", "root", "", "taskbuddy");
    if ($db->connect_error) {
        throw new Exception("Connection failed: " . $db->connect_error);
    }
    $db_connected = true;

    // Handle user actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Toggle user status (active/inactive)
        if (isset($_POST['toggle_status'])) {
            $user_id = intval($_POST['user_id']);
            $new_status = intval($_POST['new_status']);

            // For this example, we'll use a simple approach
            // In production, you might want to add an 'is_active' column
            $success_message = "User status updated successfully!";
        }

        // Make user admin
        if (isset($_POST['make_admin'])) {
            $user_id = intval($_POST['user_id']);

            $stmt = $db->prepare("UPDATE users SET is_admin = 1 WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);

            if ($stmt->execute()) {
                $success_message = "User promoted to admin successfully!";

                // Log admin action
                $log_stmt = $db->prepare("INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'PROMOTE_ADMIN', ?)");
                $desc = "Promoted user ID " . $user_id . " to admin";
                $log_stmt->bind_param("is", $_SESSION['user_id'], $desc);
                $log_stmt->execute();
            } else {
                $error_message = "Error promoting user: " . $db->error;
            }
        }

        // Remove admin privileges
        if (isset($_POST['remove_admin'])) {
            $user_id = intval($_POST['user_id']);

            // Don't allow removing self as admin
            if ($user_id == $_SESSION['user_id']) {
                $error_message = "You cannot remove your own admin privileges!";
            } else {
                $stmt = $db->prepare("UPDATE users SET is_admin = 0 WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);

                if ($stmt->execute()) {
                    $success_message = "Admin privileges removed successfully!";

                    // Log admin action
                    $log_stmt = $db->prepare("INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'REMOVE_ADMIN', ?)");
                    $desc = "Removed admin privileges from user ID " . $user_id;
                    $log_stmt->bind_param("is", $_SESSION['user_id'], $desc);
                    $log_stmt->execute();
                } else {
                    $error_message = "Error removing admin privileges: " . $db->error;
                }
            }
        }

        // Delete user
        if (isset($_POST['delete_user'])) {
            $user_id = intval($_POST['user_id']);

            // Don't allow deleting self
            if ($user_id == $_SESSION['user_id']) {
                $error_message = "You cannot delete your own account!";
            } else {
                $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);

                if ($stmt->execute()) {
                    $success_message = "User deleted successfully!";

                    // Log admin action
                    $log_stmt = $db->prepare("INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'DELETE_USER', ?)");
                    $desc = "Deleted user ID " . $user_id;
                    $log_stmt->bind_param("is", $_SESSION['user_id'], $desc);
                    $log_stmt->execute();
                } else {
                    $error_message = "Error deleting user: " . $db->error;
                }
            }
        }
    }

    // Handle search and filters
    if (isset($_GET['search'])) {
        $search_term = trim($_GET['search']);
    }
    if (isset($_GET['filter'])) {
        $filter_type = $_GET['filter'];
    }

    // Build query
    $query = "
        SELECT u.*, 
               t.tasker_id, t.hourly_rate, t.average_rating, t.total_reviews,
               c.name as category_name,
               (SELECT COUNT(*) FROM bookings WHERE client_id = u.user_id) as client_bookings,
               (SELECT COUNT(*) FROM bookings b2 JOIN taskers t2 ON b2.tasker_id = t2.tasker_id WHERE t2.user_id = u.user_id) as tasker_bookings
        FROM users u
        LEFT JOIN taskers t ON u.user_id = t.user_id
        LEFT JOIN categories c ON t.category_id = c.category_id
        WHERE 1=1
    ";

    $params = [];
    $types = "";

    // Apply search filter
    if (!empty($search_term)) {
        $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $search_param = "%" . $search_term . "%";
        $params[] = &$search_param;
        $params[] = &$search_param;
        $params[] = &$search_param;
        $types .= "sss";
    }

    // Apply type filter
    if ($filter_type === 'taskers') {
        $query .= " AND u.is_tasker = 1";
    } elseif ($filter_type === 'clients') {
        $query .= " AND u.is_tasker = 0 AND u.is_admin = 0";
    } elseif ($filter_type === 'admins') {
        $query .= " AND u.is_admin = 1";
    }

    $query .= " ORDER BY u.created_at DESC";

    // Execute query
    if (!empty($params)) {
        $stmt = $db->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $db->query($query);
    }

    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Function to safely escape output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Function to get user type badge
function getUserTypeBadge($user) {
    if ($user['is_admin'] == 1) {
        return '<span class="badge bg-danger">Admin</span>';
    } elseif ($user['is_tasker'] == 1) {
        return '<span class="badge bg-primary">Tasker</span>';
    } else {
        return '<span class="badge bg-secondary">Client</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - TaskBuddy Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="landing.css">
    <style>
        .users-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .search-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            border: 1px solid rgba(217, 197, 169, 0.2);
        }

        .filter-buttons{
            margin-left: 180px;

        }
        .filter-buttons .btn {
            margin-right: 10px;
            border-radius: 25px;
            padding: 8px 20px;
        }

        .filter-buttons .btn.active {
            background: linear-gradient(45deg, #5a3e20 0%, #8b6b4c 100%);
            color: white;
            border: none;
        }

        .user-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(217, 197, 169, 0.2);
        }

        .user-table th {
            background-color: #f7f3ed;
            color: #5a3e20;
            font-weight: 600;
            border-bottom: 2px solid #d9c5a9;
            padding: 15px;
        }

        .user-table td {
            vertical-align: middle;
            padding: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-stats {
            font-size: 0.85rem;
            color: #666;
        }

        .action-buttons .btn {
            padding: 5px 10px;
            margin: 2px;
            font-size: 14px;
            border-radius: 5px;
        }

        .stats-info {
            background-color: #f7f3ed;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .stats-info .stat-item {
            text-align: center;
            padding: 10px;
        }

        .stats-info .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #5a3e20;
        }

        .stats-info .stat-label {
            color: #666;
            font-size: 0.9rem;
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
                <li class="nav-item"><a href="admin_users.php" class="nav-link active">Users</a></li>
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

<div class="users-container">
    <h2 class="text-center mb-4">User Management</h2>

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

        <!-- User Statistics -->
        <div class="stats-info">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count(array_filter($users, function($u) { return $u['is_admin'] == 0; })); ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count(array_filter($users, function($u) { return $u['is_tasker'] == 1; })); ?></div>
                        <div class="stat-label">Taskers</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count(array_filter($users, function($u) { return $u['is_tasker'] == 0 && $u['is_admin'] == 0; })); ?></div>
                        <div class="stat-label">Clients</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count(array_filter($users, function($u) { return $u['is_admin'] == 1; })); ?></div>
                        <div class="stat-label">Admins</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="search-section">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search by name or email..." value="<?php echo h($search_term); ?>">
                        <button class="btn btn-primary" type="submit" style="border-radius: 25px; margin-left:10px;"
                        >
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="filter-buttons">
                        <a href="?filter=all" class="btn <?php echo $filter_type === 'all' ? 'active' : 'btn-outline-secondary'; ?>">All Users</a>
                        <a href="?filter=taskers" class="btn <?php echo $filter_type === 'taskers' ? 'active' : 'btn-outline-secondary'; ?>">Taskers</a>
                        <a href="?filter=clients" class="btn <?php echo $filter_type === 'clients' ? 'active' : 'btn-outline-secondary'; ?>">Clients</a>
                        <a href="?filter=admins" class="btn <?php echo $filter_type === 'admins' ? 'active' : 'btn-outline-secondary'; ?>">Admins</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="table-responsive user-table">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Type</th>
                    <th>Joined</th>
                    <th>Stats</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach($users as $user): ?>
                    <tr>
                        <td>
                            <div class="user-info">
                                <img src="<?php echo h($user['profile_image']); ?>" alt="Avatar" class="user-avatar">
                                <div>
                                    <strong><?php echo h($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                    <?php if ($user['is_tasker'] == 1): ?>
                                        <br><small class="text-muted"><?php echo h($user['category_name'] ?? 'No category'); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?php echo h($user['email']); ?></td>
                        <td><?php echo getUserTypeBadge($user); ?></td>
                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <div class="user-stats">
                                <?php if ($user['is_tasker'] == 1): ?>
                                    <div>Rate: $<?php echo h(number_format($user['hourly_rate'] ?? 0, 2)); ?>/hr</div>
                                    <div>Rating: <?php echo h(number_format($user['average_rating'] ?? 0, 1)); ?> ⭐</div>
                                    <div>Tasks: <?php echo h($user['tasker_bookings']); ?></div>
                                <?php else: ?>
                                    <div>Bookings: <?php echo h($user['client_bookings']); ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if ($user['is_tasker'] == 1): ?>
                                    <a href="TaskerTemplate.php?id=<?php echo h($user['user_id']); ?>" class="btn btn-sm btn-info" target="_blank">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if ($user['is_admin'] == 0 && $user['user_id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo h($user['user_id']); ?>">
                                        <button type="submit" name="make_admin" class="btn btn-sm btn-warning" onclick="return confirm('Make this user an admin?')">
                                            <i class="bi bi-shield-check"></i>
                                        </button>
                                    </form>
                                <?php elseif ($user['is_admin'] == 1 && $user['user_id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo h($user['user_id']); ?>">
                                        <button type="submit" name="remove_admin" class="btn btn-sm btn-secondary" onclick="return confirm('Remove admin privileges?')">
                                            <i class="bi bi-shield-x"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo h($user['user_id']); ?>">
                                        <button type="submit" name="delete_user" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
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
</body>
</html>