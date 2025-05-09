<?php
// Initialize variables to track database connection status
$db_connected = false;
$db_error_message = "";
$categoriesResult = null;
$taskersResult = null;

// Function to safely escape output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Try to connect to the database with proper error handling
try {
    // Set a shorter timeout to prevent long page loading when DB server is down
    $db = @new mysqli("localhost", "root", "", "taskbuddy");

    // Check for connection errors
    if ($db->connect_error) {
        throw new Exception("Unable to connect to the database. Please try again later.");
    }

    // Connection successful - get categories and taskers
    $db_connected = true;

    // Get all categories
    $categoriesQuery = "SELECT * FROM categories ORDER BY display_order ASC";
    $categoriesResult = $db->query($categoriesQuery);

    if (!$categoriesResult) {
        throw new Exception("Error loading categories: " . $db->error);
    }

    // Get all taskers with their category details
    $taskersQuery = "
        SELECT 
            t.tasker_id,
            u.first_name,
            u.last_name,
            u.profile_image,
            t.hourly_rate,
            t.average_rating,
            t.total_reviews,
            t.service_description,
            c.name AS category_name,
            c.feature_image
        FROM 
            taskers t
        JOIN 
            users u ON t.user_id = u.user_id
        JOIN 
            categories c ON t.category_id = c.category_id
        ORDER BY 
            t.average_rating DESC
    ";
    $taskersResult = $db->query($taskersQuery);

    if (!$taskersResult) {
        throw new Exception("Error loading taskers: " . $db->error);
    }

    // Close connection
    $db->close();

} catch (Exception $e) {
    // Capture the error message
    $db_connected = false;
    $db_error_message = $e->getMessage();

    // Log error for administrators (optional)
    error_log("Database Error: " . $db_error_message);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services - TaskBuddy</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="services.css">
</head>
<body>
<!-- Navigation Bar -->
<section class="navigation-bar">
    <div class="container">
        <header class="d-flex flex-wrap justify-content-center py-3 mb-0">
            <a href="index.html" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto link-body-emphasis text-decoration-none">
                <span class="fs-3">Task<span class="buddy">Buddy</span></span>
            </a>

            <ul class="nav nav-pills">
                <li class="nav-item"><a href="services.php" class="nav-link active">Services</a></li>
                <li class="nav-item"><a href="signUp.php" class="nav-link">Sign Up</a></li>
                <li class="nav-item"><a href="signIn.html" class="nav-link">Sign In</a></li>
                <li class="nav-item"><a href="#" class="nav-link">Become a Tasker</a></li>
            </ul>
        </header>
    </div>
    <div class="border-container">
        <div class="border-line"></div>
    </div>
</section>

<!-- Hero Section - Inverted from landing page -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 order-md-2">
                <h1 class="hero-title">Discover Services Made Simple.</h1>
                <p class="hero-description">Browse our extensive collection of skilled taskers ready to help with your projects, errands, and everyday needs.</p>
                <div class="d-flex gap-3">
                    <a href="#tasker-cards" class="btn btn-light btn-lg">Explore Services</a>
                </div>
            </div>
            <div class="col-md-6 order-md-1">
                <img src="./images/services_main.jpg" alt="Services Hero Image" class="img-fluid hero-image">
            </div>
        </div>
    </div>
</section>

<?php if (!$db_connected): ?>
    <!-- Database Error Message -->
    <section class="database-error py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 text-center">
                    <div class="alert alert-warning py-4">
                        <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                        <h3>Temporary Service Interruption</h3>
                        <p class="mb-0">We're currently experiencing technical difficulties with our service listings. Our team is working to resolve this issue. Please check back soon!</p>
                        <?php if (!empty($db_error_message) && isset($_GET['debug']) && $_GET['debug'] === 'admin'): ?>
                            <div class="mt-3 text-start small text-muted">
                                <strong>Error details (admin only):</strong><br>
                                <?= h($db_error_message) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <a href="index.html" class="btn btn-primary mt-3">Return to Home Page</a>
                </div>
            </div>
        </div>
    </section>
<?php else: ?>
    <!-- Categories Section - Only show if database is connected -->
    <section class="categories-section">
        <div class="container">
            <h2 class="text-center mb-4">Explore by Category</h2>

            <div class="categories-scroll-container">
                <!-- Scroll indicators -->
                <button class="scroll-indicator scroll-left hidden">
                    <i class="fas fa-chevron-left"></i>
                </button>

                <div class="categories-container">
                    <!-- "All Services" button - always first -->
                    <button class="category-item text-center mx-2 active" data-category="all">
                        <div class="category-icon">
                            <i class="fas fa-th-large"></i>
                        </div>
                        <p class="mt-1 small">All Services</p>
                    </button>

                    <!-- Dynamic categories from database -->
                    <?php if ($categoriesResult && $categoriesResult->num_rows > 0): ?>
                        <?php while($category = $categoriesResult->fetch_assoc()): ?>
                            <button class="category-item text-center mx-2" data-category="<?= h($category['name']) ?>">
                                <div class="category-icon">
                                    <i class="<?= h($category['icon_class']) ?>"></i>
                                </div>
                                <p class="mt-1 small"><?= h($category['name']) ?></p>
                            </button>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>

                <button class="scroll-indicator scroll-right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </section>

    <!-- Service/Tasker Cards Section -->
    <section id="tasker-cards" class="cards-section">
        <div class="container">
            <h2 class="text-center mb-5">Available Taskers</h2>
            <div class="cards-container">
                <!-- Dynamic tasker cards from database -->
                <?php if ($taskersResult && $taskersResult->num_rows > 0): ?>
                    <?php while($tasker = $taskersResult->fetch_assoc()): ?>
                        <div class="tasker-card" data-service="<?= h($tasker['category_name']) ?>">
                            <div class="card shadow-sm h-100">
                                <!-- Feature image comes from the category table -->
                                <img src="<?= h($tasker['feature_image']) ?>" alt="<?= h($tasker['category_name']) ?> Service" class="card-img-top">
                                <div class="card-body">
                                    <div class="avatar-container">
                                        <img src="<?= h($tasker['profile_image']) ?>" class="avatar" alt="<?= h($tasker['first_name']) ?>'s Profile">
                                        <div class="rating-badge">
                                            <i class="fas fa-star"></i> <?= h(number_format($tasker['average_rating'], 1)) ?>
                                        </div>
                                    </div>
                                    <h5 class="card-title"><?= h($tasker['first_name']) ?> <?= h($tasker['last_name']) ?></h5>
                                    <div class="service-tag"><?= h($tasker['category_name']) ?></div>
                                    <p class="card-text"><?= h($tasker['service_description']) ?></p>
                                    <div class="price-info">
                                        <div class="price">$<?= h(number_format($tasker['hourly_rate'], 2)) ?> <span>/hour</span></div>
                                        <a href="booking.php?tasker_id=<?= h($tasker['tasker_id']) ?>" class="btn btn-primary book-now">Book Now</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <p>No taskers available at the moment. Please check back later.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- Footer -->
<footer class="pt-lg-8 pt-5 footer bg-white">
    <div class="container mt-lg-2">
        <div class="row">
            <div class="col-lg-4 col-md-6 col-12">
                <!-- about company -->
                <div class="mb-4">
                    <span class="fs-3">Task<span class="buddy">Buddy</span></span>
                    <div class="mt-4">
                        <p>TaskBuddy makes everyday tasks effortless by connecting you with trusted local helpers in minutes. From home repairs to errands, get it done.</p>
                    </div>
                </div>
            </div>
            <div class="offset-lg-1 col-lg-2 col-md-3 col-6">
                <div class="mb-4">
                    <!-- list -->
                    <h3 class="fw-bold mb-3">Company</h3>
                    <ul class="list-unstyled nav nav-footer flex-column nav-x-0">
                        <li><a href="#" class="nav-link">About</a></li>
                        <li><a href="#" class="nav-link">Pricing</a></li>
                        <li><a href="#" class="nav-link">Blog</a></li>
                        <li><a href="#" class="nav-link">Careers</a></li>
                        <li><a href="#" class="nav-link">Contact</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <div class="mb-4">
                    <!-- list -->
                    <h3 class="fw-bold mb-3">Support</h3>
                    <ul class="list-unstyled nav nav-footer flex-column nav-x-0">
                        <li><a href="#" class="nav-link">Help and Support</a></li>
                        <li><a href="#" class="nav-link">Get the app</a></li>
                        <li><a href="#" class="nav-link">FAQ's</a></li>
                        <li><a href="#" class="nav-link">Tutorial</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-3 col-md-12">
                <!-- contact info -->
                <div class="mb-4">
                    <h3 class="fw-bold mb-3">Get in touch</h3>
                    <p>P429 Asira Street, Nablus, Palestine</p>
                    <p class="mb-1">
                        Email:
                        <a href="#">taskbuddysupport@gmail.com</a>
                    </p>
                    <p>
                        Phone:
                        <span class="text-dark fw-semibold">(000) 123 456 789</span>
                    </p>
                </div>
            </div>
        </div>
        <div class="row align-items-center g-0 border-top py-2 mt-6">
            <!-- Desc -->
            <div class="col-md-10 col-12">
                <div class="d-lg-flex align-items-center">
                    <div class="me-4">
                            <span>
                                ©
                                <span id="copyright">
                                    <script>
                                        document.getElementById("copyright").appendChild(document.createTextNode(new Date().getFullYear()));
                                    </script>
                                </span>
                                TaskBuddy
                            </span>
                    </div>
                    <div>
                        <nav class="nav nav-footer">
                            <a class="nav-link ps-0" href="#">Privacy Policy</a>
                            <a class="nav-link px-2 px-md-3" href="#">Cookie Notice</a>
                            <a class="nav-link" href="#">Terms of Use</a>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script>
    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navigation-bar');
        if (window.scrollY > 5) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    <?php if ($db_connected): ?>
    // Category selection - only initialize if DB is connected
    const categoryItems = document.querySelectorAll('.category-item');
    const taskerCards = document.querySelectorAll('.tasker-card');

    categoryItems.forEach(item => {
        item.addEventListener('click', function() {
            // Remove active class from all items
            categoryItems.forEach(cat => cat.classList.remove('active'));

            // Add active class to clicked item
            this.classList.add('active');

            // Get category data attribute
            const category = this.getAttribute('data-category');

            // Filter cards
            if (category === 'all') {
                taskerCards.forEach(card => {
                    card.style.display = 'block';
                });
            } else {
                taskerCards.forEach(card => {
                    if (card.dataset.service === category) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }
        });
    });

    // Category horizontal scrolling functionality
    document.addEventListener('DOMContentLoaded', function() {
        const categoriesContainer = document.querySelector('.categories-container');
        const scrollLeftBtn = document.querySelector('.scroll-left');
        const scrollRightBtn = document.querySelector('.scroll-right');

        // Check if scrolling is needed
        function checkScroll() {
            if (!categoriesContainer) return;

            const isScrollable = categoriesContainer.scrollWidth > categoriesContainer.clientWidth;

            // Show/hide left button based on scroll position
            if (categoriesContainer.scrollLeft > 20) {
                scrollLeftBtn.classList.remove('hidden');
            } else {
                scrollLeftBtn.classList.add('hidden');
            }

            // Show/hide right button based on if we can scroll more to the right
            if (isScrollable &&
                categoriesContainer.scrollLeft < (categoriesContainer.scrollWidth - categoriesContainer.clientWidth - 20)) {
                scrollRightBtn.classList.remove('hidden');
            } else {
                scrollRightBtn.classList.add('hidden');
            }
        }

        // Scroll functions
        function scrollLeft() {
            categoriesContainer.scrollBy({
                left: -200,
                behavior: 'smooth'
            });
        }

        function scrollRight() {
            categoriesContainer.scrollBy({
                left: 200,
                behavior: 'smooth'
            });
        }

        // Add event listeners
        if (scrollLeftBtn) {
            scrollLeftBtn.addEventListener('click', scrollLeft);
        }

        if (scrollRightBtn) {
            scrollRightBtn.addEventListener('click', scrollRight);
        }

        if (categoriesContainer) {
            categoriesContainer.addEventListener('scroll', checkScroll);
            // Also check on resize
            window.addEventListener('resize', checkScroll);
            // Initial check
            setTimeout(checkScroll, 100); // Small delay to ensure content is fully loaded
        }

        // Allow keyboard navigation for accessibility
        document.addEventListener('keydown', function(e) {
            if (document.activeElement && document.activeElement.classList.contains('category-item')) {
                const items = Array.from(document.querySelectorAll('.category-item'));
                const currentIndex = items.indexOf(document.activeElement);

                if (e.key === 'ArrowRight' && currentIndex < items.length - 1) {
                    items[currentIndex + 1].focus();
                    e.preventDefault();
                } else if (e.key === 'ArrowLeft' && currentIndex > 0) {
                    items[currentIndex - 1].focus();
                    e.preventDefault();
                }
            }
        });
    });
    <?php endif; ?>
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
</body>
</html>