<?php
/**
 * Smart Learning Career Guidance System
 * includes/config.php — Database, session, and all shared helpers
 *
 * ── This file is the single source of truth for: ──────────────────────────
 *   • DB connection (mysqli, procedural style)
 *   • Session bootstrap
 *   • sanitize() / sanitize_input()
 *   • redirect() / redirect_by_role()
 *   • isLoggedIn() / is_logged_in()
 *   • generate_csrf_token() / verify_csrf_token() / rotate_csrf_token()
 *   • log_activity()        ← mysqli-based, no PDO dependency
 *   • set_flash() / get_flash_messages()
 *   • getCareerRecommendations()
 *
 * ── Do NOT redefine any of the above in functions.php ─────────────────────
 */

// ── Error reporting (disable display in production, always log) ───────────
ini_set('display_errors',         0);
ini_set('display_startup_errors', 0);
ini_set('log_errors',             1);
error_reporting(E_ALL);

// ── Session (start once, securely) ───────────────────────────────────────
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

// ── Site constants (guard with defined() to prevent redeclaration errors) ─
if (!defined('SITE_NAME'))   define('SITE_NAME',   'Smart Learning Career Guidance System');
if (!defined('SITE_URL'))    define('SITE_URL',    rtrim(getenv('SITE_URL') ?: 'http://localhost/career_guidance', '/') . '/');
if (!defined('APP_SECRET'))  define('APP_SECRET',  getenv('APP_SECRET') ?: 'change-this-secret-key-in-production');
if (!defined('UPLOAD_DIR'))  define('UPLOAD_DIR',  __DIR__ . '/../uploads/');
if (!defined('MAX_FILE_MB')) define('MAX_FILE_MB', 5);

// ── Database credentials ──────────────────────────────────────────────────
define('DB_HOST',    getenv('DB_HOST') ?: 'localhost');
define('DB_USER',    getenv('DB_USER') ?: 'root');
define('DB_PASS',    getenv('DB_PASS') ?: '');   // empty = XAMPP default
define('DB_NAME',    getenv('DB_NAME') ?: 'career_guidance');
define('DB_CHARSET', 'utf8mb4');

// ── Database connection ───────────────────────────────────────────────────
$conn     = null;
$db_error = null;

// Throw exceptions on DB errors so try/catch works reliably
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    mysqli_set_charset($conn, DB_CHARSET);
} catch (mysqli_sql_exception $e) {
    error_log('[DB CONNECTION ERROR] ' . $e->getMessage());
    $db_error = 'Service temporarily unavailable. Please try again later.';
    $conn     = null;
}


// ════════════════════════════════════════════════════════════════════════════
// HELPERS
// ════════════════════════════════════════════════════════════════════════════

// ── Sanitisation ─────────────────────────────────────────────────────────

/**
 * Trim, strip tags, and HTML-encode a string for safe output.
 * Use prepared statements for DB — never embed strings directly in SQL.
 */
function sanitize(string $data): string {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Alias — files that call sanitize_input() still work. */
function sanitize_input(string $data): string {
    return sanitize($data);
}

// ── Authentication ────────────────────────────────────────────────────────

/** Returns true when a user is authenticated. */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/** Snake-case alias for isLoggedIn(). */
function is_logged_in(): bool {
    return isLoggedIn();
}

// ── Redirect ─────────────────────────────────────────────────────────────

/**
 * Redirect to a URL (absolute) or path relative to SITE_URL, then exit.
 */
function redirect(string $path): never {
    $path = preg_replace('/[\r\n]/', '', $path); // prevent header injection
    $url  = (str_starts_with($path, 'http') || str_starts_with($path, '/'))
              ? $path
              : SITE_URL . ltrim($path, '/');
    header('Location: ' . $url);
    exit;
}

/** Send a user to their role-specific dashboard. */
function redirect_by_role(string $role): never {
    $map = [
        'student'   => 'student/dashboard.php',
        'counselor' => 'counselor/dashboard.php',
        'admin'     => 'admin/dashboard.php',
    ];
    redirect($map[$role] ?? 'login.php');
}

// ── CSRF Protection ───────────────────────────────────────────────────────

/**
 * Generate (or retrieve) a CSRF token for the current session.
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF token submitted with a form (timing-safe comparison).
 */
function verify_csrf_token(string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/** Rotate the token after a valid submission to prevent re-use. */
function rotate_csrf_token(): void {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Activity Logging ──────────────────────────────────────────────────────

/**
 * Record a user activity with a prepared mysqli statement.
 *
 * Intentionally uses only scalar parameters — no PDO dependency — so it works
 * from every page in the project:
 *
 *   log_activity($user_id, 'login',    'User logged in');
 *   log_activity($user_id, 'register', "New {$role} registered");
 *
 * @param int    $user_id
 * @param string $action   Short key: 'login', 'register', 'logout', etc.
 * @param string $details  Human-readable description (optional)
 */
function log_activity(int $user_id, string $action, string $details = ''): void {
    global $conn;
    if (!$conn) return; // DB unavailable — fail silently, don't break the page

    try {
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO activity_log (user_id, action, details, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        if (!$stmt) return;

        $ip = $_SERVER['REMOTE_ADDR']     ?? 'unknown';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        mysqli_stmt_bind_param($stmt, 'issss', $user_id, $action, $details, $ip, $ua);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

    } catch (mysqli_sql_exception $e) {
        error_log('[ACTIVITY LOG ERROR] ' . $e->getMessage());
        // Do not re-throw — a logging failure must never abort the main action
    }
}

// ── Flash Messages ────────────────────────────────────────────────────────

function set_flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flash_messages(): string {
    if (empty($_SESSION['flash'])) return '';
    $html = '';
    foreach ($_SESSION['flash'] as $f) {
        $t    = htmlspecialchars($f['type'],    ENT_QUOTES, 'UTF-8');
        $m    = htmlspecialchars($f['message'], ENT_QUOTES, 'UTF-8');
        $html .= "<div class=\"alert alert-{$t}\" role=\"alert\">{$m}</div>\n";
    }
    unset($_SESSION['flash']);
    return $html;
}

// ── Career Recommendations ────────────────────────────────────────────────

/**
 * Return up to 5 career path recommendations for a student, sorted by match %.
 *
 * @param  int   $userId
 * @return array Each element: ['career' => [...row...], 'score' => int 0–100]
 */
function getCareerRecommendations(int $userId): array {
    global $conn;
    if (!$conn) return [];

    try {
        $stmt = mysqli_prepare($conn,
            'SELECT skills FROM student_profiles WHERE user_id = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result  = mysqli_stmt_get_result($stmt);
        $profile = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$profile || empty($profile['skills'])) return [];

        $user_skills = array_filter(
            array_map('trim', explode(',', strtolower($profile['skills'])))
        );
        if (empty($user_skills)) return [];

        $careers_result = mysqli_query($conn, 'SELECT * FROM career_paths');
        if (!$careers_result) return [];

        $recommendations = [];
        while ($career = mysqli_fetch_assoc($careers_result)) {
            $career_skills_str = strtolower($career['required_skills'] ?? '');
            $match_count = 0;
            foreach ($user_skills as $skill) {
                if ($skill !== '' && str_contains($career_skills_str, $skill)) {
                    $match_count++;
                }
            }
            if ($match_count > 0) {
                $recommendations[] = [
                    'career' => $career,
                    'score'  => min($match_count * 20, 100),
                ];
            }
        }

        usort($recommendations, fn($a, $b) => $b['score'] - $a['score']);
        return array_slice($recommendations, 0, 5);

    } catch (mysqli_sql_exception $e) {
        error_log('[RECOMMENDATIONS ERROR] ' . $e->getMessage());
        return [];
    }
}