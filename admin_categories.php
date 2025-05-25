<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: index.php");
    exit();
}

// Database connection
$db_connected = false;
$categories = [];
$success_message = '';
$error_message = '';

try {
    $db = new mysqli("localhost", "root", "", "taskbuddy");
    if ($db->connect_error) {
        throw new Exception("Connection failed: " . $db->connect_error);
    }
    $db_connected = true;

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add new category
        if (isset($_POST['add_category'])) {
            $name = trim($_POST['category_name']);
            $icon_class = trim($_POST['icon_class']);
            $feature_image = trim($_POST['feature_image']);
            $display_order = intval($_POST['display_order']);

            if (empty($name) || empty($icon_class) || empty($feature_image)) {
                $error_message = "All fields are required.";
            } else {
                $stmt = $db->prepare("INSERT INTO categories (name, icon_class, feature_image, display_order) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssi", $name, $icon_class, $feature_image, $display_order);

                if ($stmt->execute()) {
                    $success_message = "Category added successfully!";

                    // Log admin action
                    $log_stmt = $db->prepare("INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'CREATE_CATEGORY', ?)");
                    $desc = "Created category: " . $name;
                    $log_stmt->bind_param("is", $_SESSION['user_id'], $desc);
                    $log_stmt->execute();
                } else {
                    $error_message = "Error adding category: " . $db->error;
                }
            }
        }

        // Update category
        if (isset($_POST['update_category'])) {
            $category_id = intval($_POST['category_id']);
            $name = trim($_POST['category_name']);
            $icon_class = trim($_POST['icon_class']);
            $feature_image = trim($_POST['feature_image']);
            $display_order = intval($_POST['display_order']);

            $stmt = $db->prepare("UPDATE categories SET name = ?, icon_class = ?, feature_image = ?, display_order = ? WHERE category_id = ?");
            $stmt->bind_param("sssii", $name, $icon_class, $feature_image, $display_order, $category_id);

            if ($stmt->execute()) {
                $success_message = "Category updated successfully!";

                // Log admin action
                $log_stmt = $db->prepare("INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'UPDATE_CATEGORY', ?)");
                $desc = "Updated category ID: " . $category_id;
                $log_stmt->bind_param("is", $_SESSION['user_id'], $desc);
                $log_stmt->execute();
            } else {
                $error_message = "Error updating category: " . $db->error;
            }
        }

        // Delete category
        if (isset($_POST['delete_category'])) {
            $category_id = intval($_POST['category_id']);

            // Check if there are taskers in this category
            $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM taskers WHERE category_id = ?");
            $check_stmt->bind_param("i", $category_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $count = $result->fetch_assoc()['count'];

            if ($count > 0) {
                $error_message = "Cannot delete category with active taskers. Please reassign taskers first.";
            } else {
                $stmt = $db->prepare("DELETE FROM categories WHERE category_id = ?");
                $stmt->bind_param("i", $category_id);

                if ($stmt->execute()) {
                    $success_message = "Category deleted successfully!";

                    // Log admin action
                    $log_stmt = $db->prepare("INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'DELETE_CATEGORY', ?)");
                    $desc = "Deleted category ID: " . $category_id;
                    $log_stmt->bind_param("is", $_SESSION['user_id'], $desc);
                    $log_stmt->execute();
                } else {
                    $error_message = "Error deleting category: " . $db->error;
                }
            }
        }
    }

    // Fetch all categories
    $result = $db->query("
        SELECT c.*, COUNT(t.tasker_id) as tasker_count 
        FROM categories c 
        LEFT JOIN taskers t ON c.category_id = t.category_id 
        GROUP BY c.category_id 
        ORDER BY c.display_order ASC, c.name ASC
    ");

    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Function to safely escape output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - TaskBuddy Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="landing.css">
    <style>
        .category-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .category-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border: 1px solid rgba(217, 197, 169, 0.2);
            transition: all 0.3s ease;
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(45, 124, 124, 0.1);
        }

        .category-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f7f3ed;
            border-radius: 12px;
            font-size: 24px;
            color: #5a3e20;
        }

        .category-image {
            width: 100px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .add-category-btn {
            background: linear-gradient(45deg, #5a3e20 0%, #8b6b4c 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .add-category-btn:hover {
            background: linear-gradient(45deg, #2D7C7C 0%, #48a3a3 100%);
            transform: translateY(-3px);
        }

        .modal-header {
            background-color: #f7f3ed;
            border-bottom: 2px solid #d9c5a9;
        }

        .form-label {
            font-weight: 600;
            color: #5a3e20;
        }

        .icon-preview {
            display: inline-block;
            margin-left: 10px;
            font-size: 24px;
            color: #5a3e20;
        }

        .btn-action {
            padding: 5px 10px;
            margin: 0 2px;
            border-radius: 5px;
            font-size: 14px;
        }

        .tasker-count {
            background-color: #2D7C7C;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
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
                <li class="nav-item"><a href="admin_categories.php" class="nav-link active">Categories</a></li>
                <li class="nav-item"><a href="admin_bookings.php" class="nav-link">Bookings</a></li>
                <li class="nav-item"><a href="logout.php" class="nav-link">Sign Out</a></li>
            </ul>
        </header>
    </div>
    <div class="border-container">
        <div class="border-line"></div>
    </div>
</section>

<div class="category-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Category Management</h2>
        <button class="add-category-btn" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="bi bi-plus-circle me-2"></i>Add New Category
        </button>
    </div>

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
        <div class="row">
            <?php foreach($categories as $category): ?>
                <div class="col-md-6">
                    <div class="category-card">
                        <div class="d-flex align-items-center">
                            <div class="category-icon me-3">
                                <i class="<?php echo h($category['icon_class']); ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="mb-1"><?php echo h($category['name']); ?></h5>
                                <p class="text-muted mb-0">Order: <?php echo h($category['display_order']); ?></p>
                                <span class="tasker-count"><?php echo h($category['tasker_count']); ?> taskers</span>
                            </div>
                            <img src="<?php echo h($category['feature_image']); ?>" alt="<?php echo h($category['name']); ?>" class="category-image">
                        </div>
                        <div class="mt-3 text-end">
                            <button class="btn btn-sm btn-primary btn-action" onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <?php if ($category['tasker_count'] == 0): ?>
                                <button class="btn btn-sm btn-danger btn-action" onclick="deleteCategory(<?php echo h($category['category_id']); ?>, '<?php echo h($category['name']); ?>')">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary btn-action" disabled title="Cannot delete category with active taskers">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="categoryName" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="categoryName" name="category_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="iconClass" class="form-label">Icon Class (FontAwesome)</label>
                        <input type="text" class="form-control" id="iconClass" name="icon_class" placeholder="fas fa-broom" required>
                        <small class="text-muted">Example: fas fa-broom, fas fa-hammer, fas fa-dog</small>
                        <span class="icon-preview" id="iconPreview"></span>
                    </div>
                    <div class="mb-3">
                        <label for="featureImage" class="form-label">Feature Image URL</label>
                        <input type="url" class="form-control" id="featureImage" name="feature_image" placeholder="https://..." required>
                        <small class="text-muted">Use Unsplash or other image URLs</small>
                    </div>
                    <div class="mb-3">
                        <label for="displayOrder" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="displayOrder" name="display_order" value="0" min="0">
                        <small class="text-muted">Lower numbers appear first</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" id="editCategoryId" name="category_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editCategoryName" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="editCategoryName" name="category_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editIconClass" class="form-label">Icon Class (FontAwesome)</label>
                        <input type="text" class="form-control" id="editIconClass" name="icon_class" required>
                        <span class="icon-preview" id="editIconPreview"></span>
                    </div>
                    <div class="mb-3">
                        <label for="editFeatureImage" class="form-label">Feature Image URL</label>
                        <input type="url" class="form-control" id="editFeatureImage" name="feature_image" required>
                    </div>
                    <div class="mb-3">
                        <label for="editDisplayOrder" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="editDisplayOrder" name="display_order" min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_category" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Form -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="category_id" id="deleteCategoryId">
    <input type="hidden" name="delete_category" value="1">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Preview icon when typing
    document.getElementById('iconClass').addEventListener('input', function() {
        document.getElementById('iconPreview').innerHTML = '<i class="' + this.value + '"></i>';
    });

    document.getElementById('editIconClass').addEventListener('input', function() {
        document.getElementById('editIconPreview').innerHTML = '<i class="' + this.value + '"></i>';
    });

    // Edit category
    function editCategory(category) {
        document.getElementById('editCategoryId').value = category.category_id;
        document.getElementById('editCategoryName').value = category.name;
        document.getElementById('editIconClass').value = category.icon_class;
        document.getElementById('editFeatureImage').value = category.feature_image;
        document.getElementById('editDisplayOrder').value = category.display_order;
        document.getElementById('editIconPreview').innerHTML = '<i class="' + category.icon_class + '"></i>';

        new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
    }

    // Delete category
    function deleteCategory(id, name) {
        if (confirm('Are you sure you want to delete the category "' + name + '"?')) {
            document.getElementById('deleteCategoryId').value = id;
            document.getElementById('deleteForm').submit();
        }
    }
</script>
</body>
</html>