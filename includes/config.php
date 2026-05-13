<?php
/**
 * Smart Learning Career Guidance System
 * includes/config.php — Database connection & site-wide configuration
 *
 * Error-500 fixes applied
 * ───────────────────────
 *  ✓ Replaced die() with graceful error handling
 *  ✓ Removed duplicate session_start() (handled once here, safely)
 *  ✓ Removed redirect() / sanitize() that clashed with functions.php
 *  ✓ Added mysqli error mode so warnings surface in logs, not the browser
 *  ✓ Wrapped connection in try/catch for clean failure messaging
 */

// ── Session (start once, safely) ─────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    // Harden the session cookie
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// ── Database credentials (override via environment variables in production) ─
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');          // empty = XAMPP default
define('DB_NAME',    getenv('DB_NAME')    ?: 'career_guidance');
define('DB_CHARSET', 'utf8mb4');

// ── Site constants ────────────────────────────────────────────────────────
define('SITE_NAME', 'Smart Learning Career Guidance System');
define('SITE_URL',  rtrim(getenv('SITE_URL') ?: 'http://localhost/career_guidance', '/') . '/');
define('APP_SECRET', getenv('APP_SECRET') ?: 'change-this-secret-key-in-production');

// ── Database connection (mysqli) ──────────────────────────────────────────
$conn = null;
$db_error = null;

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Throw exceptions on error

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset(DB_CHARSET);

} catch (mysqli_sql_exception $e) {
    // Log the real error — never expose it to the browser
    error_log('[DB ERROR] ' . $e->getMessage());
    $db_error = 'We are experiencing a temporary issue. Please try again later.';
    $conn = null;
}

/**
 * Get the active DB connection, or null if unavailable.
 * Use this instead of the global $conn where you need a safety check.
 */
function get_db(): ?mysqli {
    global $conn;
    return $conn;
}

/**
 * Execute a safe query with error handling.
 * Returns a mysqli_result on SELECT, true on INSERT/UPDATE/DELETE, or false on failure.
 *
 * @param string $sql    SQL string (use prepared statements for user data)
 * @param string $types  mysqli bind_param types string, e.g. 'sis'
 * @param mixed  ...$params  Values matching $types
 */
function db_query(string $sql, string $types = '', mixed ...$params): mysqli_result|bool {
    $conn = get_db();
    if (!$conn) return false;

    try {
        if (!empty($types) && !empty($params)) {
            $stmt = $conn->prepare($sql);
            if (!$stmt) return false;
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result !== false ? $result : true;
        }

        return $conn->query($sql);

    } catch (mysqli_sql_exception $e) {
        error_log('[QUERY ERROR] ' . $e->getMessage() . ' | SQL: ' . $sql);
        return false;
    }
}

// ── Helpers (only defined here — do NOT duplicate in functions.php) ───────

/**
 * Check whether a user is currently authenticated.
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Sanitize a string for use in output / DB via mysqli.
 * Prefer prepared statements for DB insertion; use this for display.
 */
function sanitize(string $data): string {
    $conn = get_db();
    $data = trim(strip_tags($data));
    return $conn
        ? mysqli_real_escape_string($conn, htmlspecialchars($data, ENT_QUOTES, 'UTF-8'))
        : htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a path relative to SITE_URL and stop execution.
 */


/**
 * Get AI-style career recommendations for a given user.
 * Scores careers by how many of the user's skills appear in the career's required skills.
 *
 * @param  int   $userId
 * @return array List of up to 5 ['career' => [...], 'score' => int] entries
 */
function getCareerRecommendations(int $userId): array {
    $conn = get_db();
    if (!$conn) return [];

    // Fetch student profile
    $result = db_query(
        'SELECT interests, skills FROM student_profiles WHERE user_id = ? LIMIT 1',
        'i', $userId
    );

    if (!$result || !($profile = mysqli_fetch_assoc($result))) {
        return [];
    }

    $user_skills = array_filter(
        array_map('trim', explode(',', strtolower($profile['skills'] ?? '')))
    );

    if (empty($user_skills)) return [];

    // Fetch all career paths
    $careers_result = db_query('SELECT * FROM career_paths');
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

    // Sort descending by score
    usort($recommendations, fn($a, $b) => $b['score'] - $a['score']);

    return array_slice($recommendations, 0, 5);
}