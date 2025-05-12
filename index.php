<?php
// Start the session at the beginning of your files
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="landing.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskBuddy</title>

</head>
<body>

    <button onclick="toTop()" id="toTopBtn" title="Go to top"> <i class="bi bi-arrow-up-circle"></i> </button>


    <section class="navigation-bar">
        <div class="container">
            <header class="d-flex flex-wrap justify-content-center py-3 mb-0">
                <a href="index.html" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto link-body-emphasis text-decoration-none">
                    <svg class="bi me-2" width="40" height="32" aria-hidden="true"><use xlink:href="#bootstrap"></use></svg>
                    <span class="fs-3">Task<span class="buddy">Buddy</span></span>
                </a>

                <ul class="nav nav-pills">
                    <li class="nav-item"><a href="services.php" class="nav-link">Services</a></li>

                    <?php if ($isLoggedIn): ?>
                    <li class="nav-item"><a href="logout.php" class="nav-link">Log Out</a></li>
                    <?php else: ?>
                    <!-- Show these links when user is NOT logged in -->
                    <li class="nav-item"><a href="signUp.php" class="nav-link">Sign Up</a></li>
                    <li class="nav-item"><a href="signIn.php" class="nav-link">Sign In</a></li>
                    <li class="nav-item"><a href="BecomeATasker.html" class="nav-link" aria-current="page">Become a Tasker</a></li>
                    <?php endif; ?>
                </ul>
            </header>
        </div>
        <div class="border-container">
            <div class="border-line"></div>
        </div>
    </section>

    <section class="hero-section">
        <div class="container">
          <div class="row align-items-center">
            <div class="col-md-6">
              <h1 class="hero-title">Help Is Just a Click Away.</h1>
              <p class="hero-description">Book reliable taskers for everything you need, from home projects to personal errands. Get it done quickly and professionally.</p>
              <a href="services.php" class="btn btn-primary btn-lg">Book a Tasker</a>
              <a href="signUp.php" class="btn btn-primary btn-lg">Become a Tasker</a>
            </div>
            <div class="col-md-6">
              <img src="./images/hero_landing.jpg" alt="Hero Image" class="img-fluid hero-image">
            </div>
          </div>
        </div>
    </section>

  
    <section class="how-it-works-section">
      <div class="container px-4 py-1" id="hanging-icons">
          <h2 class="pb-2 border-bottom">How it works</h2>
          <div class="row g-4 py-5 row-cols-1 row-cols-lg-3">
            <div class="col">
              <div class="d-flex align-items-center mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="me-3" viewBox="0 0 16 16">
                  <path d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8m15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0M9.283 4.002V12H7.971V5.338h-.065L6.072 6.656V5.385l1.899-1.383z"/>
                </svg>
                <h3 class="fs-2 text-body-emphasis mb-0">Choose Tasker</h3>
              </div>
              <p>Browse profiles to find a Tasker who meets your needs. Compare prices, skills, and reviews to make an informed choice, ensuring you select the best person for the job.</p>
            </div>
  
            <div class="col">
              <div class="d-flex align-items-center mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="me-3" viewBox="0 0 16 16">
                  <path d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8m15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0M6.646 6.24v.07H5.375v-.064c0-1.213.879-2.402 2.637-2.402 1.582 0 2.613.949 2.613 2.215 0 1.002-.6 1.667-1.287 2.43l-.096.107-1.974 2.22v.077h3.498V12H5.422v-.832l2.97-3.293c.434-.475.903-1.008.903-1.705 0-.744-.557-1.236-1.313-1.236-.843 0-1.336.615-1.336 1.306"/>
                </svg>
                <h3 class="fs-2 text-body-emphasis mb-0">Schedule Now</h3>
              </div>
              <p>Easily book your Tasker for today or a future date. Select a time that suits you, and have your task completed when it's most convenient for you.</p>
            </div>
            
            <div class="col">
              <div class="d-flex align-items-center mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="me-3" viewBox="0 0 16 16">
                  <path d="M7.918 8.414h-.879V7.342h.838c.78 0 1.348-.522 1.342-1.237 0-.709-.563-1.195-1.348-1.195-.79 0-1.312.498-1.348 1.055H5.275c.036-1.137.95-2.115 2.625-2.121 1.594-.012 2.608.885 2.637 2.062.023 1.137-.885 1.776-1.482 1.875v.07c.703.07 1.71.64 1.734 1.917.024 1.459-1.277 2.396-2.93 2.396-1.705 0-2.707-.967-2.754-2.144H6.33c.059.597.68 1.06 1.541 1.066.973.006 1.6-.563 1.588-1.354-.006-.779-.621-1.318-1.541-1.318Z"/>
                  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8"/>
                </svg>
                <h3 class="fs-2 text-body-emphasis mb-0">Get It Done</h3>
              </div>
              <p>Once your Tasker is scheduled, they’ll handle the task efficiently and professionally, delivering the results you expect, on time and to your satisfaction.</p>
            </div>
          </div>
      </div>
  </section>

  <section class="social-proof" >
    <div class="container my-5">
      <div class="row p-4 pb-0 pe-lg-0 pt-lg-5 align-items-center border">
        <div class="col-lg-7 p-3 p-lg-5 pt-lg-3">
          <h2 class="display-5 fw-bold lh-1 text-body-emphasis">Building a community of reliable local helpers and satisfied customers.</h2>
          <ul>
            <li>Over 500 tasks completed in our first three months</li>
            <li>Local taskers typically respond within 1 hour of your request</li>
            <li>Transparent pricing with no hidden fees - what you see is what you pay</li>
          </ul>
        </div>
        <div class="col-lg-5 offset-lg-0 p-0 overflow-hidden">
          <div class="image-overlay">
            <img class="rounded-lg-3" src="images/handyman_and_client-removebg-.png" alt="" width="612" height="408">
          </div>
        </div>
      </div>
    </div>
  </section>
  

    <section class="testimonial">
      <div class="container">
        <h2 class="text-center mb-5">What Our Clients Say</h2>
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body text-center">
                        <img src="https://randomuser.me/api/portraits/men/7.jpg" class="rounded-circle mb-3" alt="Client Avatar">
                        <h5 class="card-title">John Doe</h5>
                        <br>
                        <p class="card-text">"Fast response, fair pricing, and great communication throughout. Highly recommend for anyone who’s short on time."</p>
                        <div class="text-warning">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-half"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body text-center">
                        <img src="https://randomuser.me/api/portraits/women/8.jpg" class="rounded-circle mb-3" alt="Client Avatar">
                        <h5 class="card-title">Jane Smith</h5>
                        <br>
                        <p class="card-text">"Very convenient. I booked a same-day cleaning and the Tasker was punctual, friendly, and did a great job. Exceeded expectations!"</p>
                        <div class="text-warning">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body text-center">
                        <img src="https://randomuser.me/api/portraits/men/41.jpg" class="rounded-circle mb-3" alt="Client Avatar">
                        <h5 class="card-title">Mike Johnson</h5>
                        <br>
                        <p class="card-text">"I was a bit hesitant to try it at first, but the platform made the whole experience incredibly smooth. The Tasker was on time, friendly, and fixed everything perfectly."</p>
                        <div class="text-warning">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </section>

    <section class="book-a-tasker" style="padding-bottom: 100px; padding-top: 100px;">
      <div class="container">
        <div class="row justify-content-center">
          <div class="col-lg-8 text-center">
            <h2 class="mb-4 fw-bold">Ready to cross tasks off your list?</h2>
            <p class="lead mb-4">Skilled taskers are just a few clicks away. Get started in minutes!</p>
            
      
            
            <a href="services.php" class="btn btn-light btn-lg px-5 py-3 mt-4 fw-bold">
              Book a Tasker Now
              <i class="fas fa-arrow-right ms-2"></i>
            </a>
            <p class="mt-3 mb-0"><small>No obligation. Free to browse available help.</small></p>
          </div>
        </div>
      </div>
    </section>


    <section class="become-a-tasker" >
      <div class="container py-lg-8">
        <div class="row align-items-center">
          <div class="offset-xl-1 col-xl-4 col-lg-6 d-none d-lg-block">
            <!--img-->
            <div class="position-relative">
              <img src="images/become-a-tasker.jpg" alt=" img" class="img-fluid w-100 rounded-4 become-tasker">
            </div>
          </div>
          <div class="col-xl-6 col-lg-5 offset-lg-1 offset-xl-1">
            <div class="d-flex flex-column gap-6">
              <div class="d-flex flex-column gap-2">
                <h2 class="mb-0 h1">Earn Doing What You Do Best</h2>
                <p class="mb-0 fs-5">Whether you're a seasoned handyman, a tech-savvy assistant, or something in between, connect with real people who need your skills</p>
                <br>
              </div>
              <div class="d-flex flex-column gap-8">
                <div class="d-flex flex-column gap-5">
                  <div class="row gap-xxl-3 gap-0">
                    <div class="col-md-1 col-lg-2 col-xxl-1 col-2">
                      <div class="icon-shape icon-lg bg-danger-subtle rounded-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-bullseye text-danger" viewBox="0 0 16 16">
                          <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"></path>
                          <path d="M8 13A5 5 0 1 1 8 3a5 5 0 0 1 0 10m0 1A6 6 0 1 0 8 2a6 6 0 0 0 0 12"></path>
                          <path d="M8 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6m0 1a4 4 0 1 0 0-8 4 4 0 0 0 0 8"></path>
                          <path d="M9.5 8a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0"></path>
                        </svg>
                      </div>
                    </div>
                    <div class="col-md-6 col-lg-10 col-xxl-6 col-10">
                      <h4 class="mb-0">Show the craftsmanship and passion you’ve invested in your work</h4>
                    </div>
                  </div>
                  <div class="row gap-xxl-3">
                    <div class="col-md-1 col-lg-2 col-xxl-1 col-2">
                      <div class="icon-shape icon-lg bg-warning-subtle rounded-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-cash-stack text-warning" viewBox="0 0 16 16">
                          <path d="M1 3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1zm7 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4"></path>
                          <path d="M0 5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H1a1 1 0 0 1-1-1zm3 0a2 2 0 0 1-2 2v4a2 2 0 0 1 2 2h10a2 2 0 0 1 2-2V7a2 2 0 0 1-2-2z"></path>
                        </svg>
                      </div>
                    </div>
                    <div class="col-md-6 col-lg-10 col-xxl-6 col-10">
                      <h4 class="mb-0">Earn extra income while growing your reputation and client base</h4>
                    </div>
                  </div>
                  <div class="row gap-xxl-3">
                    <div class="col-md-1 col-lg-2 col-xxl-1 col-2">
                      <div class="icon-shape icon-lg bg-success-subtle rounded-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-calendar-check text-success" viewBox="0 0 16 16">
                          <path d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0"></path>
                          <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4z"></path>
                        </svg>
                      </div>
                    </div>
                    <div class="col-md-6 col-lg-10 col-xxl-6 col-10">
                      <h4 class="mb-0">Get paid for your skills on your own schedule
                      </h4>
                    </div>
                  </div>
                </div>
                <div>
                  <br>
                  <a href="signUp.php" class="btn btn-primary btn-lg">Become a Tasker</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <br>
    </section>

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
                          <li><a href="#" class="nav-link">FAQ’s</a></li>
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
                      <div class="d-flex">
                          <a href="#"><img src="../assets/images/svg/appstore.svg" alt="" class="img-fluid"></a>
                          <a href="#" class="ms-2"><img src="../assets/images/svg/playstore.svg" alt="" class="img-fluid"></a>
                      </div>
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
                              <span id="copyright5">
                                  <script>
                                      document.getElementById("copyright5").appendChild(document.createTextNode(new Date().getFullYear()));
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
      window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navigation-bar');
        if (window.scrollY > 5) {
          navbar.classList.add('scrolled');
        } else {
          navbar.classList.remove('scrolled');
        }
      });
      </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>

    <script src="sharedScripts.js"></script>

</body>
</html>

