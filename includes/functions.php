<?php
/**
 * Smart Learning Career Guidance System
 * includes/functions.php - Global helper functions
 */

// ─────────────────────────────────────────────────────────
//  Session & authentication helpers
// ─────────────────────────────────────────────────────────

/**
 * Check if a user is currently logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Sanitize user input for safe output / database insertion
 * (Prefer prepared statements for DB; use this for display)
 */
function sanitize(string $data): string {
    global $conn;
    $data = trim(strip_tags($data));
    if ($conn) {
        return mysqli_real_escape_string($conn, htmlspecialchars($data, ENT_QUOTES, 'UTF-8'));
    }
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a relative URL (within the site) and stop execution
 */
function redirect(string $path): never {
    // Remove any header injection attempts
    $path = preg_replace('/[\r\n]/', '', $path);
    $url = (str_starts_with($path, 'http') || str_starts_with($path, '/'))
        ? $path
        : SITE_URL . ltrim($path, '/');
    header('Location: ' . $url);
    exit;
}

// ─────────────────────────────────────────────────────────
//  Role-based redirection
// ─────────────────────────────────────────────────────────

/**
 * Redirect user to their role-specific dashboard
 */
function redirect_by_role($role): void {
    switch ($role) {
        case 'student':
            redirect('views/dashboard/student_dashboard.php');
            break;
        case 'counselor':
            redirect('views/dashboard/counselor_dashboard.php');
            break;
        case 'admin':
            redirect('views/dashboard/admin_dashboard.php');
            break;
        default:
            redirect('login.php');
    }
}

// ─────────────────────────────────────────────────────────
//  CSRF token helpers (for forms)
// ─────────────────────────────────────────────────────────

/**
 * Generate or retrieve a CSRF token for the current session
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify that a submitted CSRF token matches the session token
 */
function verify_csrf_token(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ─────────────────────────────────────────────────────────
//  Activity logging (optional)
// ─────────────────────────────────────────────────────────

/**
 * Log user actions (login, registration, profile update, etc.)
 */
function log_activity(int $user_id, string $action, string $details = ''): void {
    global $conn;
    if (!$conn) return;
    $stmt = mysqli_prepare($conn, "INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iss', $user_id, $action, $details);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// ─────────────────────────────────────────────────────────
//  Career recommendation engine
// ─────────────────────────────────────────────────────────

/**
 * Get personalized career recommendations for a student
 * based on the skills stored in their profile.
 * 
 * @param int $user_id  Student's user ID
 * @return array        Array of ['career' => [...], 'score' => int]
 */
function getCareerRecommendations(int $user_id): array {
    global $conn;
    $recommendations = [];

    // Fetch student's skills & interests
    $stmt = mysqli_prepare($conn, "SELECT skills, interests FROM student_profiles WHERE user_id = ?");
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $profile = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$profile || empty($profile['skills'])) {
        return [];
    }

    // Convert comma-separated skills to array
    $user_skills = array_map('trim', explode(',', strtolower($profile['skills'])));

    // Get all active careers
    $careers_query = mysqli_query($conn, "SELECT * FROM career_paths WHERE is_active = 1");
    if (!$careers_query) return [];

    while ($career = mysqli_fetch_assoc($careers_query)) {
        $career_skills = strtolower($career['required_skills'] ?? '');
        $match_count = 0;
        foreach ($user_skills as $skill) {
            if (!empty($skill) && str_contains($career_skills, $skill)) {
                $match_count++;
            }
        }
        if ($match_count > 0) {
            $score = min($match_count * 20, 100);
            $recommendations[] = [
                'career' => $career,
                'score'  => $score
            ];
        }
    }

    // Sort by score descending
    usort($recommendations, function($a, $b) {
        return $b['score'] - $a['score'];
    });

    // Return top 5 recommendations
    return array_slice($recommendations, 0, 5);
}

// ─────────────────────────────────────────────────────────
//  Utility: format date/time (optional)
// ─────────────────────────────────────────────────────────

/**
 * Format a database datetime into a readable string
 */
function format_datetime(string $datetime, string $format = 'M j, Y g:i A'): string {
    if (empty($datetime)) return '';
    return date($format, strtotime($datetime));
}

/**
 * Format a date only
 */
function format_date(string $date, string $format = 'M j, Y'): string {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}
?>