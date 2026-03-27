<?php
require_once 'includes/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Smart Career Guidance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-brand {
            font-weight: bold;
            color: #667eea !important;
            font-size: 1.5rem;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0;
            margin-bottom: 50px;
        }
        
        .hero-section h1 {
            font-size: 3rem;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .hero-section p {
            font-size: 1.2rem;
            margin-bottom: 30px;
        }
        
        .feature-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .feature-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 20px;
        }
        
        .career-card {
            border-left: 4px solid #667eea;
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
        
        .career-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .match-score {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        footer {
            background-color: #1a1a2e;
            color: white;
            padding: 50px 0 20px;
            margin-top: 60px;
        }
        
        footer a {
            color: #667eea;
            text-decoration: none;
        }
        
        footer a:hover {
            color: white;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            font-weight: bold;
            color: white;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-outline-primary-custom {
            border: 2px solid #667eea;
            color: #667eea;
            padding: 10px 25px;
            font-weight: bold;
        }
        
        .btn-outline-primary-custom:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }
        
        .section-title {
            margin-bottom: 40px;
            text-align: center;
            position: relative;
            padding-bottom: 15px;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .testimonial-card {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            transition: transform 0.3s;
        }
        
        .testimonial-card:hover {
            transform: translateY(-5px);
        }
        
        .testimonial-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
        }
        
        .stats-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
        }
        
        .stats-section .stat-number {
            color: white;
            font-size: 2.5rem;
        }
        
        @media (max-width: 768px) {
            .hero-section {
                padding: 60px 0;
            }
            
            .hero-section h1 {
                font-size: 2rem;
            }
            
            .hero-section p {
                font-size: 1rem;
            }
        }
        
        .skill-badge {
            display: inline-block;
            background: #e9ecef;
            color: #667eea;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin: 3px;
        }
        
        .recommendation-badge {
            position: absolute;
            top: 15px;
            right: 15px;
        }
        
        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .dashboard-card {
            transition: transform 0.3s;
            cursor: pointer;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-graduation-cap me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="careers.php">Careers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="resources.php">Resources</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                                <li><a class="dropdown-item" href="my_recommendations.php"><i class="fas fa-star me-2"></i>My Recommendations</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-primary text-white px-3 ms-2" href="register.php">
                                <i class="fas fa-user-plus me-1"></i>Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section text-center">
        <div class="container">
            <h1>Discover Your Perfect Career Path</h1>
            <p>Get personalized career recommendations based on your skills, interests, and academic background</p>
            <?php if(!isset($_SESSION['user_id'])): ?>
                <a href="register.php" class="btn btn-primary-custom btn-lg">
                    <i class="fas fa-rocket me-2"></i>Start Your Journey
                </a>
                <a href="login.php" class="btn btn-outline-light btn-lg ms-3">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </a>
            <?php else: ?>
                <a href="dashboard.php" class="btn btn-primary-custom btn-lg">
                    <i class="fas fa-chalkboard-user me-2"></i>Go to Dashboard
                </a>
                <a href="assessments.php" class="btn btn-outline-light btn-lg ms-3">
                    <i class="fas fa-clipboard-list me-2"></i>Take Assessment
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Features Section -->
    <section class="container py-5">
        <h2 class="section-title">How It Works</h2>
        <div class="row">
            <div class="col-md-4">
                <div class="card feature-card text-center p-4">
                    <div class="feature-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h5>1. Create Your Profile</h5>
                    <p>Sign up and create your profile with your skills, interests, and educational background.</p>
                    <a href="register.php" class="btn btn-outline-primary-custom mt-2">Get Started →</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card feature-card text-center p-4">
                    <div class="feature-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <h5>2. AI-Powered Assessment</h5>
                    <p>Our smart algorithm analyzes your profile to match you with suitable career paths.</p>
                    <a href="assessments.php" class="btn btn-outline-primary-custom mt-2">Take Assessment →</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card feature-card text-center p-4">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h5>3. Get Recommendations</h5>
                    <p>Receive personalized career recommendations with match scores and growth insights.</p>
                    <a href="recommendations.php" class="btn btn-outline-primary-custom mt-2">View Careers →</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Career Categories Section -->
    <section class="bg-light py-5">
        <div class="container">
            <h2 class="section-title">Popular Career Categories</h2>
            <div class="row">
                <?php
                $categories = [
                    ['Technology & IT', 'fa-laptop-code', 'Software Developer, Data Scientist, Cybersecurity'],
                    ['Healthcare & Medicine', 'fa-heartbeat', 'Doctor, Nurse, Medical Researcher'],
                    ['Business & Finance', 'fa-chart-line', 'Accountant, Financial Analyst, Marketing'],
                    ['Engineering', 'fa-cogs', 'Civil, Mechanical, Electrical Engineer'],
                    ['Creative Arts', 'fa-palette', 'Graphic Designer, Animator, Writer'],
                    ['Education', 'fa-chalkboard-teacher', 'Teacher, Professor, Educational Consultant']
                ];
                
                foreach($categories as $category) {
                    echo '<div class="col-md-4">
                            <div class="card career-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <i class="fas ' . $category[1] . ' fa-2x text-primary mb-3"></i>
                                            <h5 class="card-title">' . $category[0] . '</h5>
                                            <p class="text-muted small">' . $category[2] . '</p>
                                        </div>
                                        <span class="match-score">Trending</span>
                                    </div>
                                    <a href="careers.php?category=' . urlencode($category[0]) . '" class="btn btn-sm btn-outline-primary mt-2">
                                        Explore Careers <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                          </div>';
                }
                ?>
            </div>
        </div>
    </section>

    <!-- Top Career Recommendations -->
    <section class="container py-5">
        <h2 class="section-title">Top Career Recommendations</h2>
        <div class="row">
            <?php
            $query = "SELECT * FROM career_paths ORDER BY id LIMIT 6";
            $result = mysqli_query($conn, $query);
            
            if(mysqli_num_rows($result) > 0) {
                while($row = mysqli_fetch_assoc($result)) {
                    echo '<div class="col-md-4">
                            <div class="card career-card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-briefcase text-primary me-2"></i>' . htmlspecialchars($row['career_name']) . '
                                    </h5>
                                    <p class="card-text text-muted small">' . htmlspecialchars(substr($row['description'], 0, 100)) . '...</p>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-graduation-cap me-1"></i>' . htmlspecialchars($row['education_required']) . '
                                        </small>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-chart-line me-1"></i>Growth: ' . htmlspecialchars($row['growth_rate']) . '
                                        </small>
                                    </div>
                                    <a href="career_details.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-primary mt-3">
                                        Learn More <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                          </div>';
                }
            } else {
                echo '<div class="col-12">
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle me-2"></i>Career data is being updated. Check back soon!
                        </div>
                      </div>';
            }
            ?>
        </div>
        <div class="text-center mt-4">
            <a href="careers.php" class="btn btn-primary-custom">View All Careers <i class="fas fa-arrow-right ms-2"></i></a>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 mb-3">
                    <div class="stat-number">100+</div>
                    <p>Career Paths</p>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-number">5000+</div>
                    <p>Students Guided</p>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-number">95%</div>
                    <p>Success Rate</p>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-number">50+</div>
                    <p>Expert Advisors</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="container py-5">
        <h2 class="section-title">Success Stories</h2>
        <div class="row">
            <div class="col-md-4">
                <div class="testimonial-card text-center">
                    <img src="https://randomuser.me/api/portraits/women/1.jpg" alt="User" class="testimonial-img">
                    <h5>Sarah Mwangi</h5>
                    <p class="text-muted small">Software Developer</p>
                    <p>"This platform helped me discover my passion for programming. Now I'm working as a full-stack developer at a tech company!"</p>
                    <div class="stars text-warning">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="testimonial-card text-center">
                    <img src="https://randomuser.me/api/portraits/men/2.jpg" alt="User" class="testimonial-img">
                    <h5>John Otieno</h5>
                    <p class="text-muted small">Data Scientist</p>
                    <p>"The career recommendations were spot-on! I followed their guidance and now I'm working as a data scientist at a leading firm."</p>
                    <div class="stars text-warning">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="testimonial-card text-center">
                    <img src="https://randomuser.me/api/portraits/women/3.jpg" alt="User" class="testimonial-img">
                    <h5>Grace Kiprop</h5>
                    <p class="text-muted small">UX Designer</p>
                    <p>"The skills assessment helped me realize my strengths in design. Now I'm creating amazing user experiences for web applications."</p>
                    <div class="stars text-warning">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Newsletter Section -->
    <section class="bg-light py-5">
        <div class="container text-center">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <h3 class="mb-3">Stay Updated with Career Insights</h3>
                    <p class="text-muted mb-4">Subscribe to our newsletter for career tips, job opportunities, and industry trends.</p>
                    <form class="row g-3 justify-content-center">
                        <div class="col-md-6">
                            <input type="email" class="form-control" placeholder="Enter your email address">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary-custom w-100">Subscribe</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5><i class="fas fa-graduation-cap me-2"></i><?php echo SITE_NAME; ?></h5>
                    <p>Your trusted partner in career discovery and guidance. Helping students and professionals find their perfect career path.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php"><i class="fas fa-chevron-right me-2"></i>Home</a></li>
                        <li><a href="careers.php"><i class="fas fa-chevron-right me-2"></i>Careers</a></li>
                        <li><a href="resources.php"><i class="fas fa-chevron-right me-2"></i>Resources</a></li>
                        <li><a href="about.php"><i class="fas fa-chevron-right me-2"></i>About Us</a></li>
                        <li><a href="contact.php"><i class="fas fa-chevron-right me-2"></i>Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5>Contact Info</h5>
                    <p><i class="fas fa-envelope me-2"></i> info@smartcareer.com</p>
                    <p><i class="fas fa-phone me-2"></i> +254 700 123 456</p>
                    <p><i class="fas fa-map-marker-alt me-2"></i> Kabarak University, Kenya</p>
                    <div class="mt-3">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-linkedin fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-instagram fa-lg"></i></a>
                    </div>
                </div>
            </div>
            <hr class="bg-white">
            <div class="text-center">
                <p class="mb-0">&copy; 2025 <?php echo SITE_NAME; ?>. All rights reserved.</p>
                <p class="mt-2">Empowering futures through smart career guidance</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Close database connection
mysqli_close($conn);
?>