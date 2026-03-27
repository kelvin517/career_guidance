<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Leave empty if no password for XAMPP
define('DB_NAME', 'career_guidance');

// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, "utf8mb4");

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Site configuration
define('SITE_NAME', 'Smart Learning Career Guidance System');
define('SITE_URL', 'http://localhost/career_guidance/');

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to redirect
function redirect($url) {
    header("Location: " . SITE_URL . $url);
    exit();
}

// Function to sanitize input
function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(strip_tags(trim($data))));
}

// Function to get career recommendations based on user skills and interests
function getCareerRecommendations($userId) {
    global $conn;
    
    $sql = "SELECT sp.interests, sp.skills 
            FROM student_profiles sp 
            WHERE sp.user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $profile = mysqli_fetch_assoc($result);
    
    if (!$profile) {
        return [];
    }
    
    $interests = strtolower($profile['interests']);
    $skills = strtolower($profile['skills']);
    
    $sql = "SELECT * FROM career_paths";
    $careers = mysqli_query($conn, $sql);
    
    $recommendations = [];
    
    while ($career = mysqli_fetch_assoc($careers)) {
        $score = 0;
        $career_skills = strtolower($career['required_skills']);
        
        // Simple scoring algorithm
        $skill_matches = 0;
        $user_skills_array = explode(',', $skills);
        foreach ($user_skills_array as $skill) {
            if (strpos($career_skills, trim($skill)) !== false) {
                $skill_matches++;
            }
        }
        
        $score = $skill_matches * 20;
        
        if ($score > 0) {
            $recommendations[] = [
                'career' => $career,
                'score' => min($score, 100)
            ];
        }
    }
    
    // Sort by score
    usort($recommendations, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    return array_slice($recommendations, 0, 5);
}
?>