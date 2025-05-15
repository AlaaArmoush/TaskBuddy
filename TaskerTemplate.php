<?php
session_start();

$homeLink = 'index.php';
if (isset($_SESSION['user_id'], $_SESSION['is_tasker']) && $_SESSION['is_tasker'] == 1) {
    $homeLink = 'TaskerTemplate.php?id=' . intval($_SESSION['user_id']);
}

require_once 'notifications_helper.php';

$db_connected = false;
$db_error_message = "";
try {
    $db = new mysqli("localhost", "root", "", "taskbuddy");
    if ($db->connect_error) {
        throw new Exception("Connection failed: " . $db->connect_error);
    }
    $db_connected = true;

    // Initialize notification counter
    $notification_count = 0;

    // If user is logged in, count unread notifications
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];

        // Get notification count based on user type
        if (isset($_SESSION['is_tasker']) && $_SESSION['is_tasker'] == 1) {
            $notification_count = getUnreadNotificationCount($db, $user_id, 'tasker');
        } else {
            $notification_count = getUnreadNotificationCount($db, $user_id, 'client');
        }
    }

} catch (Exception $e) {
    $db_error_message = $e->getMessage();
    error_log("Database Error: " . $db_error_message);
}

// Function to safely escape output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Initialize variables
$tasker_data = null;
$reviews = null;
$category_name = '';
$isOwner = false;
$edit_mode = false;
$success_message = '';
$error_message = '';
$categories = null;
$portfolio_images = [];
$location = 'Nablus, Palestine';

// Check if we're in edit mode
if (isset($_GET['edit']) && $_GET['edit'] == 'true' && isset($_SESSION['user_id'])) {
    $edit_mode = true;
}

// Create uploads directory if it doesn't exist
$portfolio_upload_dir = 'uploads/portfolio/';
$profile_upload_dir = 'uploads/profiles/';

if (!file_exists($portfolio_upload_dir)) {
    mkdir($portfolio_upload_dir, 0777, true);
}

if (!file_exists($profile_upload_dir)) {
    mkdir($profile_upload_dir, 0777, true);
}

// Process form submission for profile editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    if ($db_connected) {
        try {
            $user_id = $_SESSION['user_id'];

            // Regular profile update
            if (isset($_POST['update_profile'])) {
                $bio = $_POST['bio'] ?? '';
                $hourly_rate = floatval($_POST['hourly_rate'] ?? 0);
                $category_id = intval($_POST['category_id'] ?? 1);
                $service_description = $_POST['service_description'] ?? '';
                $location = $_POST['location'] ?? 'Nablus, Palestine';

                // Update the tasker record
                $updateStmt = $db->prepare("UPDATE taskers SET bio = ?, hourly_rate = ?, category_id = ?, service_description = ?, location = ? WHERE user_id = ?");
                $updateStmt->bind_param("sdissi", $bio, $hourly_rate, $category_id, $service_description, $location, $user_id);
                $result = $updateStmt->execute();

                if ($result) {
                    $success_message = "Profile updated successfully!";
                    // Don't redirect yet, we may need to process file uploads
                } else {
                    $error_message = "Failed to update profile: " . $db->error;
                }
            }

            // Profile image upload
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['profile_image']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (in_array($ext, $allowed)) {
                    $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                    $destination = $profile_upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $destination)) {
                        // Update profile image in the users table
                        $updateImageStmt = $db->prepare("UPDATE users SET profile_image = ? WHERE user_id = ?");
                        $image_path = $destination;
                        $updateImageStmt->bind_param("si", $image_path, $user_id);
                        $updateImageStmt->execute();

                        $success_message .= " Profile image updated successfully!";
                    } else {
                        $error_message .= " Failed to upload profile image.";
                    }
                } else {
                    $error_message .= " Invalid file format for profile image.";
                }
            }

            // Portfolio images upload
            if (isset($_FILES['portfolio_images'])) {
                $file_count = count($_FILES['portfolio_images']['name']);
                $tasker_id_query = $db->prepare("SELECT tasker_id FROM taskers WHERE user_id = ?");
                $tasker_id_query->bind_param("i", $user_id);
                $tasker_id_query->execute();
                $tasker_id_result = $tasker_id_query->get_result();

                if ($tasker_id_result && $tasker_id_result->num_rows > 0) {
                    $tasker_id = $tasker_id_result->fetch_assoc()['tasker_id'];

                    for ($i = 0; $i < $file_count; $i++) {
                        if ($_FILES['portfolio_images']['error'][$i] == 0) {
                            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                            $filename = $_FILES['portfolio_images']['name'][$i];
                            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                            if (in_array($ext, $allowed)) {
                                $new_filename = 'portfolio_' . $tasker_id . '_' . time() . '_' . $i . '.' . $ext;
                                $destination = $portfolio_upload_dir . $new_filename;

                                if (move_uploaded_file($_FILES['portfolio_images']['tmp_name'][$i], $destination)) {
                                    // Add to portfolio_images table
                                    $caption = "Portfolio image " . ($i + 1);
                                    $insertImageStmt = $db->prepare("INSERT INTO portfolio_images (tasker_id, image_path, caption) VALUES (?, ?, ?)");
                                    $insertImageStmt->bind_param("iss", $tasker_id, $destination, $caption);
                                    $insertImageStmt->execute();

                                    $success_message .= " Portfolio image " . ($i + 1) . " uploaded successfully!";
                                } else {
                                    $error_message .= " Failed to upload portfolio image " . ($i + 1) . ".";
                                }
                            } else {
                                $error_message .= " Invalid file format for portfolio image " . ($i + 1) . ".";
                            }
                        }
                    }
                }
            }

            // Redirect to prevent form resubmission
            if (empty($error_message)) {
                header("Location: TaskerTemplate.php?id=$user_id&success=true");
                exit;
            }

        } catch (Exception $e) {
            $error_message = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Get tasker ID from URL or session
$tasker_id = null;
if (isset($_GET['id'])) {
    $tasker_id = intval($_GET['id']);
} elseif (isset($_SESSION['user_id']) && isset($_SESSION['is_tasker']) && $_SESSION['is_tasker']) {
    $tasker_id = $_SESSION['user_id'];
}

// Check if the logged-in user is the owner of this profile
if (isset($_SESSION['user_id']) && $tasker_id == $_SESSION['user_id']) {
    $isOwner = true;
}

// If we have a success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 'true') {
    $success_message = "Profile updated successfully!";
}

// Fetch all categories for the dropdown (only needed in edit mode)
if ($db_connected && $edit_mode) {
    $categories_query = "SELECT category_id, name FROM categories ORDER BY name";
    $categories_result = $db->query($categories_query);
    if ($categories_result) {
        $categories = $categories_result->fetch_all(MYSQLI_ASSOC);
    }
}

// Fetch tasker data if we have an ID and database connection
if ($db_connected && $tasker_id) {
    // Query to get tasker data with user info and category
    $query = "
        SELECT 
            t.tasker_id,
            t.user_id,
            t.bio,
            t.hourly_rate,
            t.average_rating,
            t.total_reviews,
            t.service_description,
            t.location,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.profile_image,
            c.name AS category_name,
            c.category_id
        FROM 
            taskers t
        JOIN 
            users u ON t.user_id = u.user_id
        JOIN 
            categories c ON t.category_id = c.category_id
        WHERE 
            t.user_id = ?
    ";

    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $tasker_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $tasker_data = $result->fetch_assoc();
        $category_name = $tasker_data['category_name'];
        $location = $tasker_data['location'];

        // Query to get portfolio images for this tasker
        $portfolio_query = "
            SELECT 
                image_id,
                image_path,
                caption,
                upload_date
            FROM 
                portfolio_images
            WHERE 
                tasker_id = ?
            ORDER BY 
                upload_date DESC
        ";

        $portfolio_stmt = $db->prepare($portfolio_query);
        $portfolio_stmt->bind_param("i", $tasker_data['tasker_id']);
        $portfolio_stmt->execute();
        $portfolio_result = $portfolio_stmt->get_result();

        if ($portfolio_result && $portfolio_result->num_rows > 0) {
            $portfolio_images = $portfolio_result->fetch_all(MYSQLI_ASSOC);
        }

        // Query to get reviews for this tasker
        $review_query = "
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
                r.tasker_id = ? AND 
                r.comment IS NOT NULL AND 
                TRIM(r.comment) != ''
            ORDER BY 
                r.created_at DESC
        ";

        $review_stmt = $db->prepare($review_query);
        $review_stmt->bind_param("i", $tasker_data['tasker_id']);
        $review_stmt->execute();
        $review_result = $review_stmt->get_result();

        if ($review_result) {
            $reviews = $review_result->fetch_all(MYSQLI_ASSOC);
        }
    } else {
        // Tasker not found
        header("Location: services.php");
        exit;
    }
}

// Function to generate star rating HTML
function generateStarRating($rating) {
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);

    $html = '';
    for ($i = 0; $i < $fullStars; $i++) {
        $html .= '★';
    }
    if ($halfStar) {
        $html .= '★'; // Unicode doesn't have a half star, so we use a full star
    }
    for ($i = 0; $i < $emptyStars; $i++) {
        $html .= '☆';
    }
    return $html;
}

// Function to format date for "time ago" display
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return "Just now";
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . " minute" . ($minutes > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . " month" . ($months > 1 ? "s" : "") . " ago";
    } else {
        $years = floor($diff / 31536000);
        return $years . " year" . ($years > 1 ? "s" : "") . " ago";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasker Profile<?php echo $tasker_data ? ' - ' . h($tasker_data['first_name'] . ' ' . $tasker_data['last_name']) : ''; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="TaskerTemplate.css" rel="stylesheet">

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
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <li class="nav-item"><a href="services.php" class="nav-link">Services</a></li>
                    <li class="nav-item"><a href="signUp.php" class="nav-link">Sign Up</a></li>
                    <li class="nav-item"><a href="signIn.php" class="nav-link">Sign In</a></li>

                <?php else: ?>
                    <?php if (isset($_SESSION['is_tasker']) && $_SESSION['is_tasker'] == 1): ?>

                        <li class="nav-item nav-notification">
                            <a href="tasker_requests.php" class="nav-link">
                                Requests
                                <?php if ($notification_count > 0): ?>
                                    <span class="badge rounded-pill bg-danger notification-badge">
                            <?php echo $notification_count; ?>
                        </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item"><a href="TaskerTemplate.php?id=<?php echo h($_SESSION['user_id']); ?>" class="nav-link active">My Profile</a></li>

                        <li class="nav-item"><a href="logout.php" class="nav-link">Sign Out</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a href="services.php" class="nav-link">Services</a></li>
                        <li class="nav-item">
                            <a href="task_status.php" class="nav-link position-relative">
                                Tasks Updates &amp; Status
                            </a>
                        </li>
                        <li class="nav-item"><a href="logout.php" class="nav-link">Sign Out</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </header>
    </div>
    <div class="border-container">
        <div class="border-line"></div>
    </div>
</section>

<?php if (!$db_connected): ?>
    <div class="container mt-5">
        <div class="alert alert-warning" role="alert">
            <h4 class="alert-heading">Unable to connect to database</h4>
            <p>We're currently experiencing technical difficulties. Please try again later.</p>
            <?php if (!empty($db_error_message)): ?>
                <hr>
                <p class="mb-0 small text-muted"><?php echo h($db_error_message); ?></p>
            <?php endif; ?>
        </div>
    </div>
<?php elseif (!$tasker_data): ?>
    <div class="container mt-5">
        <div class="alert alert-warning" role="alert">
            <h4 class="alert-heading">Tasker not found</h4>
            <p>The requested tasker profile does not exist or has been removed.</p>
        </div>
    </div>
<?php else: ?>

    <div class="container tasker-profile mt-5">

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

        <?php if ($isOwner && $edit_mode): ?>
            <!-- Edit Profile Form -->
            <div class="edit-form">
                <h2 class="mb-4">Edit Your Profile</h2>
                <form action="TaskerTemplate.php" method="post" enctype="multipart/form-data">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="profileImage" class="form-label">Profile Image</label>
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo h($tasker_data['profile_image']); ?>" alt="Current Profile Image" class="rounded-circle me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                    <div class="file-input-wrapper">
                                        <button class="btn btn-outline-secondary" type="button">Choose New Image</button>
                                        <input type="file" name="profile_image" id="profileImage" accept="image/*" onchange="previewImage(this, 'profilePreview')">
                                    </div>
                                </div>
                                <img id="profilePreview" class="image-preview" src="#" alt="Profile Preview">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" name="location" id="location" class="form-control" value="<?php echo h($location); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category" class="form-label">Service Category</label>
                                <select name="category_id" id="category" class="form-control">
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo h($category['category_id']); ?>" <?php echo ($category['category_id'] == $tasker_data['category_id']) ? 'selected' : ''; ?>>
                                            <?php echo h($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="hourlyRate" class="form-label">Hourly Rate ($)</label>
                                <input type="number" step="0.01" min="1" name="hourly_rate" id="hourlyRate" class="form-control" value="<?php echo h($tasker_data['hourly_rate']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="bio" class="form-label">Bio</label>
                        <textarea name="bio" id="bio" class="form-control" rows="3" required><?php echo h($tasker_data['bio']); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="serviceDescription" class="form-label">Service Description</label>
                        <textarea name="service_description" id="serviceDescription" class="form-control" rows="5" required><?php echo h($tasker_data['service_description']); ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Portfolio Images</label>
                        <p class="text-muted small">Add images to showcase your work (maximum 5 images, each under 5MB)</p>
                        <div class="file-input-wrapper mb-2">
                            <button class="btn btn-outline-secondary" type="button">Select Portfolio Images</button>
                            <input type="file" name="portfolio_images[]" accept="image/*" multiple onchange="previewPortfolioImages(this)">
                        </div>
                        <div id="portfolioPreviewContainer" class="row mt-3"></div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="TaskerTemplate.php?id=<?php echo h($tasker_id); ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Normal Profile View -->
            <div class="row">
                <div class="col-md-4">
                    <div class="tasker-header text-center">
                        <div class="avatar-container mb-4">
                            <img src="<?php echo h($tasker_data['profile_image']); ?>" alt="<?php echo h($tasker_data['first_name']); ?>'s Profile" class="rounded-avatar">
                            <span class="verification-badge">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                    <path d="m9 12 2 2 4-4"></path>
                                </svg>
                            </span>
                        </div>
                        <h1 class="tasker-name"><?php echo h($tasker_data['first_name'] . ' ' . $tasker_data['last_name']); ?></h1>
                        <div class="badge text-white">
                            <?php echo h($category_name); ?>
                        </div>
                        <div class="location-info">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo h($location); ?>
                        </div>

                        <div class="bio-section mb-4">
                            <p><?php echo h($tasker_data['bio']); ?></p>
                        </div>
                    </div>

                    <div class="tasker-stats mt-4">
                        <div class="row text-center">
                            <div class="col-md-4 offset-md-2">
                                <h4 class="stat-number"><?php echo h(number_format($tasker_data['average_rating'], 1)); ?></h4>
                                <p class="stat-label">Overall Rating</p>
                            </div>
                            <div class="col-md-4">
                                <h4 class="stat-number"><?php echo h($tasker_data['total_reviews']); ?></h4>
                                <p class="stat-label">Tasks Completed</p>
                            </div>
                        </div>
                    </div>

                    <div class="tasker-actions mt-4">
                        <?php if ($isOwner): ?>
                            <a href="TaskerTemplate.php?id=<?php echo h($tasker_id); ?>&edit=true" class="btn btn-primary w-100 mb-3">Edit Profile</a>

                            <a href="tasker_inbox.php"  class="btn btn-light w-100 mb-3 position-relative">
                                Messages
                                <i class="fas fa-comment-dots me-2"></i>
                                <?php
                                if ($db_connected) {
                                    $current_user_id = $_SESSION['user_id'];
                                    $unread_query = "
                                                    SELECT COUNT(*) AS unread_count
                                                    FROM chat_messages m
                                                    JOIN conversations c ON m.conversation_id = c.conversation_id
                                                    WHERE m.is_read = 0
                                                      AND m.sender_id != ?
                                                      AND (c.user1_id = ? OR c.user2_id = ?)
                                                  ";
                                    $stmt = $db->prepare($unread_query);
                                    $stmt->bind_param('iii', $current_user_id, $current_user_id, $current_user_id);
                                    $stmt->execute();
                                    $cnt = $stmt->get_result()->fetch_assoc()['unread_count'];
                                    if ($cnt > 0) {
                                        echo '<span class="position-absolute top-50 translate-middle-y badge rounded-pill bg-danger">'
                                            . $cnt .
                                            '</span>';
                                    }
                                }
                                ?>
                            </a>
                        <?php elseif (isset($_SESSION['user_id'])): ?>
                            <a href="booking.php?tasker_id=<?php echo h($tasker_data['tasker_id']); ?>" class="btn btn-primary w-100 mb-3">Book Now</a>
                            <button type="button" class="btn btn-light w-100" data-bs-toggle="modal" data-bs-target="#chatModal">
                                <i class="fas fa-comment-dots me-2"></i> Message
                            </button>
                        <?php else: ?>
                            <a href="signIn.php?redirect=TaskerTemplate.php?id=<?php echo h($tasker_id); ?>" class="btn btn-primary w-100 mb-3">Sign In to Book</a>
                            <a href="signUp.php" class="btn btn-light w-100">Sign Up</a>
                        <?php endif; ?>
                    </div>

                </div>

                <div class="col-md-8">
                    <div class="service-description mb-5">
                        <h2>Service Description</h2>
                        <p><?php echo h($tasker_data['service_description']); ?></p>
                        <div class="price-info mt-4">
                            <h3 class="mb-0">$<?php echo h(number_format($tasker_data['hourly_rate'], 2)); ?> <small class="text-muted">/hour</small></h3>
                        </div>
                    </div>

                    <div class="tasker-portfolio mb-5">
                        <h2>Portfolio</h2>
                        <div class="row g-3">
                            <?php if (count($portfolio_images) > 0): ?>
                                <?php foreach($portfolio_images as $image): ?>
                                    <div class="col-md-4">
                                        <img src="<?php echo h($image['image_path']); ?>" alt="<?php echo h($image['caption']); ?>" class="img-fluid portfolio-image"
                                             onclick="showImageModal('<?php echo h($image['image_path']); ?>', '<?php echo h($image['caption']); ?>')">
                                    </div>
                                <?php endforeach; ?>
                            <?php elseif ($isOwner): ?>
                                <!-- No portfolio images but profile owner -->
                                <div class="col-12">
                                    <div class="portfolio-placeholder">
                                        <i class="fas fa-images"></i>
                                        <p>You haven't added any portfolio images yet</p>
                                        <a href="TaskerTemplate.php?id=<?php echo h($tasker_id); ?>&edit=true" class="btn btn-outline-primary">Add Portfolio Images</a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- No portfolio images for non-owner -->
                                <div class="col-12">
                                    <p class="text-muted">This tasker hasn't added any portfolio images yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tasker-reviews" style="margin-bottom: 25px">
                        <h2>Reviews</h2>
                        <div class="review-container">
                            <?php if ($reviews && count($reviews) > 0): ?>
                                <?php foreach($reviews as $index => $review): ?>
                                    <?php $hidden = $index >= 2 ? 'hidden-review' : ''; ?>
                                    <div class="review mb-4 <?php echo $hidden; ?>">
                                        <div class="review-header d-flex align-items-center mb-2">
                                            <img src="<?php echo h($review['profile_image']); ?>" alt="<?php echo h($review['first_name']); ?>'s Profile" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;">
                                            <div>
                                                <h5 class="mb-0"><?php echo h($review['first_name'] . ' ' . $review['last_name']); ?></h5>
                                                <div class="star-rating text-warning">
                                                    <?php echo generateStarRating($review['rating']); ?>
                                                </div>
                                            </div>
                                            <small class="ms-auto text-muted"><?php echo timeAgo($review['created_at']); ?></small>
                                        </div>
                                        <p class="review-text"><?php echo h($review['comment']); ?></p>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (count($reviews) > 2): ?>
                                    <button id="seeReviewsBtn" class="btn btn-light w-100">See all reviews</button>
                                <?php endif; ?>
                            <?php else: ?>
                                <p>No reviews yet. Be the first to book and review this tasker!</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalTitle">Portfolio Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="Portfolio Image" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // JavaScript to toggle visibility of hidden reviews
    document.addEventListener('DOMContentLoaded', function() {
        const seeReviewsBtn = document.getElementById('seeReviewsBtn');
        if (seeReviewsBtn) {
            const hiddenReviews = document.querySelectorAll('.hidden-review');

            seeReviewsBtn.addEventListener('click', function() {
                hiddenReviews.forEach(review => {
                    if (review.classList.contains('hidden-review')) {
                        review.classList.remove('hidden-review');
                    } else {
                        review.classList.add('hidden-review');
                    }
                });

                // Toggle button text
                if (this.textContent === 'See all reviews') {
                    this.textContent = 'Show less';
                } else {
                    this.textContent = 'See all reviews';
                }
            });
        }
    });

    // Image preview functions
    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();

            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }

            reader.readAsDataURL(input.files[0]);
        }
    }

    function previewPortfolioImages(input) {
        const previewContainer = document.getElementById('portfolioPreviewContainer');
        previewContainer.innerHTML = '';

        if (input.files) {
            const fileCount = Math.min(input.files.length, 5); // Limit to 5 images

            for (let i = 0; i < fileCount; i++) {
                const reader = new FileReader();

                reader.onload = function(e) {
                    const col = document.createElement('div');
                    col.className = 'col-md-4 mb-3';

                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'img-fluid portfolio-image';
                    img.style.height = '150px';

                    col.appendChild(img);
                    previewContainer.appendChild(col);
                }

                reader.readAsDataURL(input.files[i]);
            }
        }
    }

    // Function to show modal with larger image
    function showImageModal(src, caption) {
        const modalImage = document.getElementById('modalImage');
        const modalTitle = document.getElementById('imageModalTitle');

        modalImage.src = src;
        modalTitle.textContent = caption || 'Portfolio Image';

        const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
        imageModal.show();
    }
</script>

<?php
if (isset($_SESSION['user_id']) && !$isOwner) {
    include('simple_chat.php');
}
?>

</body>
</html>