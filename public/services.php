<?php
$db_connected = false;
$db_error_message = "";
$categoriesResult = null;
$taskersResult = null;

session_start();

// Include notification helper
require_once __DIR__ . '/../app/helpers/notifications_helper.php';

// Initialize notification counter
$notification_count = 0;

function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

try {
    $db = new mysqli("localhost", "root", "", "taskbuddy");

    if ($db->connect_error) {
        throw new Exception("Unable to connect to the database. Please try again later.");
    }

    $db_connected = true;

    // Get notification count if user is logged in
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];

        // Get notification count based on user type
        if (isset($_SESSION['is_tasker']) && $_SESSION['is_tasker'] == 1) {
            $notification_count = getUnreadNotificationCount($db, $user_id, 'tasker');
        } else {
            $notification_count = getUnreadNotificationCount($db, $user_id, 'client');
        }
    }

    $categoriesQuery = "SELECT * FROM categories ORDER BY display_order ASC";
    $categoriesResult = $db->query($categoriesQuery);

    if (!$categoriesResult) {
        throw new Exception("Error loading categories: " . $db->error);
    }

    $taskersQuery = "
        SELECT 
            t.tasker_id,
            t.user_id,
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

} catch (Exception $e) {
    $db_connected = false;
    $db_error_message = $e->getMessage();
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/css/services.css?v=1.6">

    <style>
        /* Animation Classes - Directly in HTML to ensure they load */
        .animate-hidden {
            opacity: 0;
            filter: blur(5px);
            transform: translateX(-100px);
            transition: all 1s ease;
        }

        .animate-show {
            opacity: 1;
            filter: blur(0);
            transform: translateX(0);
        }

        /* Right-to-left animation */
        .animate-hidden-right {
            opacity: 0;
            filter: blur(5px);
            transform: translateX(100px);
            transition: all 1s ease;
        }

        .animate-hidden-right.animate-show {
            opacity: 1;
            filter: blur(0);
            transform: translateX(0);
        }

        /* Bottom-to-top animation */
        .animate-hidden-bottom {
            opacity: 0;
            filter: blur(5px);
            transform: translateY(50px);
            transition: all 1s ease;
        }

        .animate-hidden-bottom.animate-show {
            opacity: 1;
            filter: blur(0);
            transform: translateY(0);
        }

        .stagger-list .animate-hidden:nth-child(1) { transition-delay: 0ms; }
        .stagger-list .animate-hidden:nth-child(2) { transition-delay: 100ms; }
        .stagger-list .animate-hidden:nth-child(3) { transition-delay: 200ms; }
        .stagger-list .animate-hidden:nth-child(4) { transition-delay: 300ms; }
        .stagger-list .animate-hidden:nth-child(5) { transition-delay: 400ms; }
        .stagger-list .animate-hidden:nth-child(6) { transition-delay: 500ms; }
        .stagger-list .animate-hidden:nth-child(7) { transition-delay: 600ms; }
        .stagger-list .animate-hidden:nth-child(8) { transition-delay: 700ms; }
        .stagger-list .animate-hidden:nth-child(9) { transition-delay: 800ms; }
        .stagger-list .animate-hidden:nth-child(10) { transition-delay: 900ms; }

    </style>
</head>
<body>
<button onclick="toTop()" id="toTopBtn" title="Go to top"> <i class="bi bi-arrow-up-circle"></i> </button>


<!-- Navigation Bar -->
<section class="navigation-bar">
    <div class="container">
        <header class="d-flex flex-wrap justify-content-center py-3 mb-0">
            <a href="index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto link-body-emphasis text-decoration-none">
                <svg class="bi me-2" width="40" height="32" aria-hidden="true"><use xlink:href="#bootstrap"></use></svg>
                <span class="fs-3">Task<span class="buddy">Buddy</span></span>
            </a>

            <ul class="nav nav-pills">
                <li class="nav-item"><a href="public/services.php" class="nav-link active">Services</a></li>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <li class="nav-item"><a href="public/signUp.php" class="nav-link">Sign Up</a></li>
                    <li class="nav-item"><a href="public/signIn.php" class="nav-link">Sign In</a></li>
                    <li class="nav-item"><a href="public/BecomeATasker.html" class="nav-link">Become a Tasker</a></li>
                <?php else: ?>
                    <a href="task_status.php" class="nav-link position-relative">
                        Tasks Updates & Status
                        <?php if (isset($notification_count) && $notification_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?php echo $notification_count; ?>
                                        <span class="visually-hidden">unread notifications</span>
                                    </span>
                        <?php endif; ?>
                    </a>
                    <li class="nav-item"><a href="logout.php" class="nav-link">Sign Out</a></li>
                <?php endif; ?>
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
            <div class="col-md-6 order-md-2 animate-hidden-right">
                <h1 class="hero-title">Discover Services Made Simple.</h1>
                <p class="hero-description">Browse our extensive collection of skilled taskers ready to help with your projects, errands, and everyday needs.</p>
                <div class="d-flex gap-3">
                    <a href="#categories-section" class="btn btn-light btn-lg">Explore Services</a>
                </div>
            </div>
            <div class="col-md-6 order-md-1 animate-hidden">
                <img src="../images/services_main.jpg" alt="Services Hero Image" class="img-fluid hero-image">
            </div>
        </div>
    </div>
</section>

<?php if (!$db_connected): ?>
    <!-- Database Error Message -->
    <section class="database-error py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 text-center animate-hidden-bottom">
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
                    <a href="index.php" class="btn btn-primary mt-3">Return to Home Page</a>
                </div>
            </div>
        </div>
    </section>
<?php else: ?>
    <!-- Categories Section - Only show categories with icons -->
    <section class="categories-section">
        <div class="container">
            <h2 class="text-center mb-4 animate-hidden">Explore by Category</h2>

            <div class="categories-scroll-container animate-hidden-bottom">
                <!-- Scroll indicators -->
                <button class="scroll-indicator scroll-left hidden">
                    <i class="fas fa-chevron-left"></i>
                </button>

                <div class="categories-container stagger-list">
                    <!-- "All Services" button - always first -->
                    <button class="category-item text-center mx-2 active animate-hidden" data-category="all">
                        <div class="category-icon">
                            <i class="fas fa-th-large"></i>
                        </div>
                        <p class="mt-1 small">All Services</p>
                    </button>

                    <!-- Dynamic categories from database - only show if icon_class is not empty -->
                    <?php if ($categoriesResult && $categoriesResult->num_rows > 0): ?>
                        <?php while($category = $categoriesResult->fetch_assoc()): ?>
                            <?php if (!empty(trim($category['icon_class']))): ?>
                                <button class="category-item text-center mx-2 animate-hidden" data-category="<?= h($category['name']) ?>">
                                    <div class="category-icon">
                                        <i class="<?= h($category['icon_class']) ?>"></i>
                                    </div>
                                    <p class="mt-1 small"><?= h($category['name']) ?></p>
                                </button>
                            <?php endif; ?>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>

                <button class="scroll-indicator scroll-right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <!-- Search Bar -->
            <div class="search-container mt-4 animate-hidden-bottom">
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6">
                        <div class="search-wrapper position-relative">
                            <input type="text"
                                   id="taskerSearch"
                                   class="form-control search-input"
                                   placeholder="Search for services, taskers, or specific skills..."
                                   autocomplete="off">
                            <div class="search-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <button class="search-clear" id="clearSearch" style="display: none;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="search-suggestions" id="searchSuggestions"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Service/Tasker Cards Section - Hide images for categories without feature_image -->
    <section id="tasker-cards" class="cards-section">
        <div class="container">
            <h2 class="text-center mb-5">Available Taskers</h2>
            <div class="cards-container">
                <!-- Dynamic tasker cards from database -->
                <?php if ($taskersResult && $taskersResult->num_rows > 0): ?>
                    <?php while($tasker = $taskersResult->fetch_assoc()): ?>
                        <div class="tasker-card" data-service="<?= h($tasker['category_name']) ?>">
                            <a href="TaskerTemplate.php?id=<?= h($tasker['user_id']) ?>" class="card-link">
                                <div class="card shadow-sm h-100">
                                    <!-- Only show feature image if it exists and is not empty -->
                                    <?php if (!empty(trim($tasker['feature_image']))): ?>
                                        <img src="<?= h($tasker['feature_image']) ?>" alt="<?= h($tasker['category_name']) ?> Service" class="card-img-top">
                                    <?php endif; ?>

                                    <div class="card-body <?= empty(trim($tasker['feature_image'])) ? 'no-image' : '' ?>">
                                        <div class="avatar-container">
                                            <img src="<?= h($tasker['profile_image']) ?>" class="avatar" alt="<?= h($tasker['first_name']) ?>'s Profile">
                                            <div class="rating-badge">
                                                <?= $tasker['total_reviews'] == 0 ? 'Not Rated' : '<i class="fas fa-star"></i> ' . h(number_format($tasker['average_rating'], 1)) ?>
                                            </div>
                                        </div>
                                        <h5 class="card-title"><?= h($tasker['first_name']) ?> <?= h($tasker['last_name']) ?></h5>
                                        <div class="service-tag"><?= h($tasker['category_name']) ?></div>
                                        <p class="card-text"><?= h($tasker['service_description']) ?></p>
                                        <div class="price-info">
                                            <div class="price">$<?= h(number_format($tasker['hourly_rate'], 2)) ?> <span>/hour</span></div>
                                            <a href="TaskerTemplate.php?id=<?= h($tasker['user_id']) ?>" class="btn btn-primary book-now">View Profile</a>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12 text-center animate-hidden">
                        <p>No taskers available at the moment. Please check back later.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="pagination-controls" class="text-center mt-4"></div>
    </section>

<?php endif; ?>

<!-- Footer -->
<footer class="pt-lg-8 pt-5 footer bg-white">
    <div class="container mt-lg-2">
        <div class="row">
            <div class="col-lg-4 col-md-6 col-12 animate-hidden">
                <!-- about company -->
                <div class="mb-4">
                    <span class="fs-3">Task<span class="buddy">Buddy</span></span>
                    <div class="mt-4">
                        <p>TaskBuddy makes everyday tasks effortless by connecting you with trusted local helpers in minutes. From home repairs to errands, get it done.</p>
                    </div>
                </div>
            </div>
            <div class="offset-lg-1 col-lg-2 col-md-3 col-6 animate-hidden">
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
            <div class="col-lg-2 col-md-3 col-6 animate-hidden">
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
            <div class="col-lg-3 col-md-12 animate-hidden">
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

<script>
    //  NAVBAR SCROLL EFFECT
    window.addEventListener('scroll', () => {
        document.querySelector('.navigation-bar')
            .classList.toggle('scrolled', window.scrollY > 5);
    });

    <?php if ($db_connected): ?>
    //  CONFIG + STATE
    const TASKERS_PER_PAGE = 12;
    let currentPage       = 1;

    const categoryItems = document.querySelectorAll('.category-item');
    const taskerCards   = Array.from(document.querySelectorAll('.tasker-card'));
    const paginationCtr = document.getElementById('pagination-controls');

    // RENDERING FUNCTION
    function renderCards(category) {
        const matches = (category === 'all')
            ? taskerCards
            : taskerCards.filter(c => c.dataset.service === category);

        taskerCards.forEach(c => c.style.display = 'none');

        if (category === 'all') {
            const totalPages = Math.ceil(matches.length / TASKERS_PER_PAGE);
            currentPage = Math.min(Math.max(1, currentPage), totalPages);

            const start = (currentPage - 1) * TASKERS_PER_PAGE;
            matches.slice(start, start + TASKERS_PER_PAGE)
                .forEach(c => c.style.display = 'block');

            buildPagination(totalPages);
        } else {
            matches.forEach(c => c.style.display = 'block');
            paginationCtr.innerHTML = '';
        }
    }

    //  BUILD PLAIN "1 2 3" LINKS
    function buildPagination(totalPages) {
        paginationCtr.innerHTML = '';
        for (let i = 1; i <= totalPages; i++) {
            const link = document.createElement('a');
            link.href = '#';
            link.textContent = i;
            link.style.margin = '0 6px';
            link.style.textDecoration = 'none';
            link.style.color = (i === currentPage) ? '#333' : '#888';
            link.style.fontWeight = (i === currentPage) ? 'bold' : 'normal';

            link.addEventListener('click', e => {
                e.preventDefault();
                if (currentPage === i) return;
                currentPage = i;
                const activeCat = document.querySelector('.category-item.active')
                    .getAttribute('data-category');
                renderCards(activeCat);
                document.getElementById('tasker-cards')
                    .scrollIntoView({ behavior: 'smooth' });
            });

            paginationCtr.appendChild(link);
        }
    }

    //  CATEGORY CLICK HANDLER
    categoryItems.forEach(item => {
        item.addEventListener('click', function() {
            categoryItems.forEach(c => c.classList.remove('active'));
            this.classList.add('active');

            currentPage = 1;
            renderCards(this.getAttribute('data-category'));
        });
    });

    // INITIAL LOAD = "All"
    document.querySelector('.category-item[data-category="all"]').click();


    // CATEGORY SCROLL + KEYBOARD NAV
    document.addEventListener('DOMContentLoaded', () => {
        const container = document.querySelector('.categories-container');
        const leftBtn   = document.querySelector('.scroll-left');
        const rightBtn  = document.querySelector('.scroll-right');

        function checkScroll() {
            if (!container) return;
            const max = container.scrollWidth - container.clientWidth - 20;
            leftBtn .classList.toggle('hidden', container.scrollLeft <= 20);
            rightBtn.classList.toggle('hidden', container.scrollLeft >= max);
        }

        leftBtn?.addEventListener('click', () =>
            container.scrollBy({ left: -200, behavior: 'smooth' })
        );
        rightBtn?.addEventListener('click', () =>
            container.scrollBy({ left:  200, behavior: 'smooth' })
        );

        container?.addEventListener('scroll', checkScroll);
        window.addEventListener('resize', checkScroll);
        setTimeout(checkScroll, 100);

        document.addEventListener('keydown', e => {
            const active = document.activeElement;
            if (active?.classList.contains('category-item')) {
                const items = Array.from(categoryItems);
                const idx   = items.indexOf(active);
                if (e.key === 'ArrowRight' && idx < items.length - 1) {
                    items[idx + 1].focus(); e.preventDefault();
                }
                if (e.key === 'ArrowLeft' && idx > 0) {
                    items[idx - 1].focus(); e.preventDefault();
                }
            }
        });
    });
    <?php endif; ?>
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq"
        crossorigin="anonymous"></script>
<script src="/js/sharedScripts.js"></script>

<!-- Animation script using Intersection Observer -->
<script>
    // Wait for the DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        console.log("DOM loaded, starting animation setup");

        // Select all elements with animation classes
        const hiddenElements = document.querySelectorAll('.animate-hidden, .animate-hidden-right, .animate-hidden-bottom');
        console.log("Found", hiddenElements.length, "elements to animate");

        // Create the Intersection Observer
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                // If the element is intersecting with the viewport
                if (entry.isIntersecting) {
                    console.log("Element entering viewport:", entry.target);
                    // Add the show class to make it visible with animation
                    entry.target.classList.add('animate-show');
                }
            });
        }, {
            // Element is considered "visible" when 15% is in viewport
            threshold: 0.15,
            // Start animations slightly before elements enter viewport
            rootMargin: "0px 0px -50px 0px"
        });

        // Observe all hidden elements
        hiddenElements.forEach((el) => {
            observer.observe(el);
            console.log("Now observing:", el);
        });

        // Apply show class to elements already in view on page load
        setTimeout(() => {
            hiddenElements.forEach((el) => {
                const rect = el.getBoundingClientRect();
                const windowHeight = window.innerHeight || document.documentElement.clientHeight;

                if (rect.top <= windowHeight) {
                    console.log("Element already in viewport, showing immediately:", el);
                    el.classList.add('animate-show');
                }
            });
        }, 100);
    });


    document.addEventListener('DOMContentLoaded', function() {
        // Get the explore services button
        const exploreBtn = document.querySelector('.hero-section .btn-light');

        // Add click event listener
        if (exploreBtn) {
            exploreBtn.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent default anchor behavior

                // Get the categories section
                const categoriesSection = document.querySelector('.categories-section');

                if (categoriesSection) {
                    // Scroll to categories section with a small offset from the top
                    const yOffset = -20; // Adjust this value as needed
                    const y = categoriesSection.getBoundingClientRect().top + window.pageYOffset + yOffset;

                    window.scrollTo({
                        top: y,
                        behavior: 'smooth'
                    });
                }
            });
        }
    });


    // Search functionality variables
    let currentSearchTerm = '';
    const searchInput = document.getElementById('taskerSearch');
    const clearBtn = document.getElementById('clearSearch');
    const suggestions = document.getElementById('searchSuggestions');

    // SEARCH FUNCTIONALITY
    function performSearch(searchTerm, category = 'all') {
        searchTerm = searchTerm.toLowerCase().trim();
        currentSearchTerm = searchTerm;

        let matches = taskerCards;

        // Filter by category first
        if (category !== 'all') {
            matches = matches.filter(card => card.dataset.service === category);
        }

        // Then filter by search term
        if (searchTerm) {
            matches = matches.filter(card => {
                const name = card.querySelector('.card-title').textContent.toLowerCase();
                const service = card.dataset.service.toLowerCase();
                const description = card.querySelector('.card-text').textContent.toLowerCase();

                return name.includes(searchTerm) ||
                    service.includes(searchTerm) ||
                    description.includes(searchTerm);
            });
        }

        return matches;
    }

    // UPDATE THE EXISTING renderCards FUNCTION
    function renderCards(category, searchTerm = '') {
        const matches = performSearch(searchTerm, category);

        // Hide all cards first
        taskerCards.forEach(c => c.style.display = 'none');

        // Remove existing "No results" message
        const existingNoResults = document.querySelector('.no-results');
        if (existingNoResults) existingNoResults.remove();

        // Show "No results" message if no matches
        if (matches.length === 0) {
            const noResults = document.createElement('div');
            noResults.className = 'no-results';
            noResults.innerHTML = `
            <i class="fas fa-search"></i>
            <h4>No results found</h4>
            <p>Try adjusting your search terms or browse all services</p>
        `;
            document.querySelector('.cards-container').appendChild(noResults);
            paginationCtr.innerHTML = '';
            return;
        }

        // Handle pagination for search results and "All" category
        if (category === 'all' || searchTerm) {
            const totalPages = Math.ceil(matches.length / TASKERS_PER_PAGE);
            currentPage = Math.min(Math.max(1, currentPage), totalPages);

            const start = (currentPage - 1) * TASKERS_PER_PAGE;
            matches.slice(start, start + TASKERS_PER_PAGE)
                .forEach(c => c.style.display = 'block');

            buildPagination(totalPages);
        } else {
            // Show all matches for specific categories
            matches.forEach(c => c.style.display = 'block');
            paginationCtr.innerHTML = '';
        }

        // Highlight search terms
        if (searchTerm) {
            highlightSearchTerms(matches, searchTerm);
        } else {
            removeHighlights();
        }
    }

    // HIGHLIGHT SEARCH TERMS
    function highlightSearchTerms(cards, searchTerm) {
        cards.forEach(card => {
            const title = card.querySelector('.card-title');
            const service = card.querySelector('.service-tag');
            const description = card.querySelector('.card-text');

            [title, service, description].forEach(el => {
                if (el && el.textContent.toLowerCase().includes(searchTerm)) {
                    const regex = new RegExp(`(${searchTerm})`, 'gi');
                    el.innerHTML = el.textContent.replace(regex, '<span class="highlight">$1</span>');
                }
            });
        });
    }

    // REMOVE HIGHLIGHTS
    function removeHighlights() {
        document.querySelectorAll('.highlight').forEach(el => {
            el.outerHTML = el.textContent;
        });
    }

    // SEARCH SUGGESTIONS
    function showSuggestions(searchTerm) {
        if (!searchTerm || searchTerm.length < 2) {
            suggestions.style.display = 'none';
            return;
        }

        const uniqueCategories = [...new Set(taskerCards.map(card => card.dataset.service))];
        const matchingCategories = uniqueCategories.filter(cat =>
            cat.toLowerCase().includes(searchTerm.toLowerCase())
        ).slice(0, 5);

        const matchingTaskers = taskerCards.filter(card => {
            const name = card.querySelector('.card-title').textContent;
            return name.toLowerCase().includes(searchTerm.toLowerCase());
        }).slice(0, 3);

        let html = '';

        if (matchingCategories.length > 0) {
            matchingCategories.forEach(cat => {
                html += `<div class="search-suggestion" data-type="category" data-value="${cat}">
                <div class="suggestion-category">Category</div>
                <div>${cat}</div>
            </div>`;
            });
        }

        if (matchingTaskers.length > 0) {
            matchingTaskers.forEach(card => {
                const name = card.querySelector('.card-title').textContent;
                const service = card.dataset.service;
                html += `<div class="search-suggestion" data-type="tasker" data-value="${name}">
                <div class="suggestion-category">Tasker</div>
                <div>${name} - ${service}</div>
            </div>`;
            });
        }

        if (html) {
            suggestions.innerHTML = html;
            suggestions.style.display = 'block';

            // Add click handlers for suggestions
            suggestions.querySelectorAll('.search-suggestion').forEach(suggestion => {
                suggestion.addEventListener('click', () => {
                    const value = suggestion.dataset.value;
                    searchInput.value = value;
                    suggestions.style.display = 'none';
                    handleSearch();
                });
            });
        } else {
            suggestions.style.display = 'none';
        }
    }

    // SEARCH EVENT HANDLERS
    function handleSearch() {
        const searchTerm = searchInput.value.trim();
        currentPage = 1;

        if (searchTerm) {
            clearBtn.style.display = 'block';
            // Reset category to "All" when searching
            categoryItems.forEach(item => item.classList.remove('active'));
            document.querySelector('.category-item[data-category="all"]').classList.add('active');
            renderCards('all', searchTerm);
        } else {
            clearBtn.style.display = 'none';
            const activeCategory = document.querySelector('.category-item.active').dataset.category;
            renderCards(activeCategory);
        }

        suggestions.style.display = 'none';
    }

    // INITIALIZE SEARCH EVENT LISTENERS
    document.addEventListener('DOMContentLoaded', function() {
        if (searchInput) {
            let searchTimeout;

            // Input event for suggestions
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    showSuggestions(e.target.value);
                }, 300);
            });

            // Enter key to search
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    handleSearch();
                }
            });

            // Hide suggestions when input loses focus
            searchInput.addEventListener('blur', () => {
                setTimeout(() => {
                    suggestions.style.display = 'none';
                }, 200);
            });
        }

        // Clear button functionality
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                searchInput.value = '';
                clearBtn.style.display = 'none';
                currentSearchTerm = '';
                removeHighlights();
                const activeCategory = document.querySelector('.category-item.active').dataset.category;
                renderCards(activeCategory);
            });
        }
    });

    // UPDATE YOUR EXISTING CATEGORY CLICK HANDLER
    // Replace your existing category click handler with this updated version:
    categoryItems.forEach(item => {
        item.addEventListener('click', function() {
            categoryItems.forEach(c => c.classList.remove('active'));
            this.classList.add('active');

            currentPage = 1;

            // Clear search when selecting a category
            if (searchInput && searchInput.value) {
                searchInput.value = '';
                clearBtn.style.display = 'none';
                currentSearchTerm = '';
                removeHighlights();
            }

            renderCards(this.getAttribute('data-category'));
        });
    });

    // UPDATE YOUR buildPagination FUNCTION
    // Replace the existing buildPagination function with this updated version:
    function buildPagination(totalPages) {
        paginationCtr.innerHTML = '';
        for (let i = 1; i <= totalPages; i++) {
            const link = document.createElement('a');
            link.href = '#';
            link.textContent = i;
            link.style.margin = '0 6px';
            link.style.textDecoration = 'none';
            link.style.color = (i === currentPage) ? '#333' : '#888';
            link.style.fontWeight = (i === currentPage) ? 'bold' : 'normal';

            link.addEventListener('click', e => {
                e.preventDefault();
                if (currentPage === i) return;
                currentPage = i;
                const activeCat = document.querySelector('.category-item.active')
                    .getAttribute('data-category');
                renderCards(activeCat, currentSearchTerm); // Pass search term here
                document.getElementById('tasker-cards')
                    .scrollIntoView({ behavior: 'smooth' });
            });

            paginationCtr.appendChild(link);
        }
    }
</script>



</body>
</html>
