<?php
/**
 * Smart Learning Career Guidance System
 * includes/config.php — Database, session, and all shared helpers
 */

// ── Error reporting (enable for debugging, disable in production) ──
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ── Session (start once, securely) ─────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// ── Site constants ─────────────────────────────────────────────────
if (!defined('SITE_NAME'))   define('SITE_NAME',   'Smart Learning Career Guidance System');
if (!defined('SITE_URL'))    define('SITE_URL',    rtrim('http://localhost/career_guidance', '/') . '/');
if (!defined('UPLOAD_DIR'))  define('UPLOAD_DIR',  __DIR__ . '/../uploads/');

// ── Database credentials ───────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'career_guidance');
define('DB_CHARSET', 'utf8mb4');

// ── Database connection (mysqli) ───────────────────────────────────
$conn = null;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    mysqli_set_charset($conn, DB_CHARSET);
} catch (mysqli_sql_exception $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    die('Database temporarily unavailable. Please try again later.');
}

// ═══════════════════════════════════════════════════════════════════
//  HELPERS (all in one place – no functions.php needed)
// ═══════════════════════════════════════════════════════════════════

function sanitize(string $data): string {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function sanitize_input(string $data): string {
    return sanitize($data);
}
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function redirect(string $path): never {
    $path = preg_replace('/[\r\n]/', '', $path);
    $url = (str_starts_with($path, 'http') || str_starts_with($path, '/'))
        ? $path
        : SITE_URL . ltrim($path, '/');
    header('Location: ' . $url);
    exit;
}

function redirect_by_role(string $role): never {
    $map = [
        'student'   => 'views/dashboard/student_dashboard.php',
        'teacher'   => 'views/dashboard/teacher_dashboard.php',
        'counselor' => 'views/dashboard/counselor_dashboard.php',
        'admin'     => 'views/dashboard/admin_dashboard.php',
    ];
    redirect($map[$role] ?? 'login.php');
}

function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(string $token): bool {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function rotate_csrf_token(): void {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function log_activity(int $user_id, string $action, string $details = ''): void {
    global $conn;
    if (!$conn) return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $stmt = mysqli_prepare($conn, "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'issss', $user_id, $action, $details, $ip, $ua);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function getCareerRecommendations(int $userId): array {
    global $conn;
    $recommendations = [];
    $stmt = mysqli_prepare($conn, "SELECT skills FROM student_profiles WHERE user_id = ?");
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $profile = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$profile || empty($profile['skills'])) return [];

    $user_skills = array_filter(array_map('trim', explode(',', strtolower($profile['skills']))));
    if (empty($user_skills)) return [];

    $careers = mysqli_query($conn, "SELECT * FROM career_paths WHERE is_active = 1");
    if (!$careers) return [];

    while ($career = mysqli_fetch_assoc($careers)) {
        $career_skills = strtolower($career['required_skills'] ?? '');
        $match_count = 0;
        foreach ($user_skills as $skill) {
            if (str_contains($career_skills, $skill)) $match_count++;
        }
        if ($match_count > 0) {
            $recommendations[] = [
                'career' => $career,
                'score'  => min($match_count * 20, 100)
            ];
        }
    }
    usort($recommendations, fn($a, $b) => $b['score'] - $a['score']);
    return array_slice($recommendations, 0, 5);
}
?>