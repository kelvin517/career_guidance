<?php
/**
 * Smart Learning Career Guidance System
 * includes/functions.php — Utility & helper functions
 *
 * Sections:
 *   1. Constants & app config
 *   2. Input sanitisation
 *   3. CSRF protection
 *   4. Redirects
 *   5. Session & authentication helpers
 *   6. Activity logging
 *   7. Flash messages
 *   8. Pagination
 *   9. Date / time helpers
 *  10. File upload helpers
 *  11. Notification helpers
 */

// ─────────────────────────────────────────────────────────────────────────
// 1. Constants & App Config
// ─────────────────────────────────────────────────────────────────────────

define('APP_NAME',    'Smart Learning Career Guidance');
define('APP_VERSION', '1.0.0');

define('APP_URL',     rtrim(getenv('APP_URL') ?: 'http://localhost/smart-learning', '/'));
define('UPLOAD_DIR',  __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB

// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 30 * 60);

// Role → dashboard path map
define('ROLE_DASHBOARDS', [
    'student'   => 'student/dashboard.php',
    'counselor' => 'counselor/dashboard.php',
    'admin'     => 'admin/dashboard.php',
]);


// ─────────────────────────────────────────────────────────────────────────
// 2. Input Sanitisation
// ─────────────────────────────────────────────────────────────────────────

/**
 * Trim, strip tags, and convert special HTML characters.
 *
 * @param  string $data  Raw user input
 * @return string        Sanitised string safe for output / DB insertion
 */
function sanitize_input(string $data): string {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitise a string for safe use inside a URL query parameter.
 */
function sanitize_url_param(string $param): string {
    return urlencode(strip_tags(trim($param)));
}

/**
 * Cast and clamp an integer value.
 */
function sanitize_int(mixed $value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int {
    return max($min, min($max, (int) $value));
}

/**
 * Validate and return a safe email, or empty string on failure.
 */
function sanitize_email(string $email): string {
    $clean = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    return filter_var($clean, FILTER_VALIDATE_EMAIL) ? $clean : '';
}


// ─────────────────────────────────────────────────────────────────────────
// 3. CSRF Protection
// ─────────────────────────────────────────────────────────────────────────

/**
 * Generate a CSRF token for the current session and store it.
 *
 * @return string  Hex CSRF token
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF token submitted with a form.
 *
 * @param  string $token  Token from the POST field
 * @return bool
 */
function verify_csrf_token(string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Rotate the CSRF token (call after each successful form submission).
 */
function rotate_csrf_token(): void {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// ─────────────────────────────────────────────────────────────────────────
// 4. Redirects
// ─────────────────────────────────────────────────────────────────────────

/**
 * Redirect to an internal URL and stop execution.
 *
 * @param string $path  Relative path (e.g. 'login.php') or absolute URL
 */
function redirect(string $path): never {
    // Prevent header injection
    $path = preg_replace('/[\r\n]/', '', $path);
    header('Location: ' . $path);
    exit;
}

/**
 * Redirect a user to their role-appropriate dashboard.
 *
 * @param string $role  One of: student | counselor | admin
 */
function redirect_by_role(string $role): never {
    $dashboards = ROLE_DASHBOARDS;
    $target = $dashboards[$role] ?? 'login.php';
    redirect($target);
}

/**
 * Redirect back to the previous page, or to a fallback.
 */
function redirect_back(string $fallback = 'index.php'): never {
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    // Only redirect to same-origin referrers
    if (!empty($ref) && parse_url($ref, PHP_URL_HOST) === parse_url(APP_URL, PHP_URL_HOST)) {
        redirect($ref);
    }
    redirect($fallback);
}


// ─────────────────────────────────────────────────────────────────────────
// 5. Session & Authentication Helpers
// ─────────────────────────────────────────────────────────────────────────

/**
 * Check whether a user is currently authenticated.
 */
function is_logged_in(): bool {
    return !empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Check whether the authenticated user has a specific role.
 *
 * @param  string|array $roles  A single role string or list of role strings
 */
function has_role(string|array $roles): bool {
    if (!is_logged_in()) return false;
    $allowed = is_array($roles) ? $roles : [$roles];
    return in_array($_SESSION['role'] ?? '', $allowed, true);
}

/**
 * Enforce session inactivity timeout.
 * Call this near the top of every protected page.
 */
function enforce_session_timeout(): void {
    if (!is_logged_in()) return;

    $last = $_SESSION['last_active'] ?? 0;
    if (time() - $last > SESSION_TIMEOUT) {
        // Destroy session cleanly
        session_unset();
        session_destroy();
        redirect('login.php?timeout=1');
    }
    $_SESSION['last_active'] = time();
}

/**
 * Return the currently authenticated user's ID, or null.
 */
function current_user_id(): ?int {
    return is_logged_in() ? (int)$_SESSION['user_id'] : null;
}

/**
 * Return the currently authenticated user's role, or null.
 */
function current_role(): ?string {
    return is_logged_in() ? ($_SESSION['role'] ?? null) : null;
}

/**
 * Generate a signed one-time logout token (for link-based logout).
 */
function generate_logout_token(): string {
    return hash_hmac('sha256', session_id(), APP_SECRET);
}


// ─────────────────────────────────────────────────────────────────────────
// 6. Activity Logging
// ─────────────────────────────────────────────────────────────────────────

/**
 * Record a user activity to the activity_log table.
 *
 * @param PDO    $pdo
 * @param int    $user_id
 * @param string $action   Short action key, e.g. 'login', 'view_course'
 * @param string $details  Human-readable description
 */
function log_activity(PDO $pdo, int $user_id, string $action, string $details = ''): void {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO activity_log (user_id, action, details, ip_address, user_agent, created_at)
             VALUES (:user_id, :action, :details, :ip, :ua, NOW())'
        );
        $stmt->execute([
            ':user_id' => $user_id,
            ':action'  => $action,
            ':details' => $details,
            ':ip'      => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ':ua'      => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (PDOException $e) {
        error_log('Activity log error: ' . $e->getMessage());
    }
}


// ─────────────────────────────────────────────────────────────────────────
// 7. Flash Messages
// ─────────────────────────────────────────────────────────────────────────

/**
 * Store a one-time flash message in the session.
 *
 * @param string $type     'success' | 'error' | 'warning' | 'info'
 * @param string $message  Message text (will be HTML-escaped on output)
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear all flash messages. Returns HTML string.
 */
function get_flash_messages(): string {
    if (empty($_SESSION['flash'])) return '';

    $html = '';
    foreach ($_SESSION['flash'] as $flash) {
        $type    = htmlspecialchars($flash['type'],    ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
        $html   .= "<div class=\"alert alert-{$type}\" role=\"alert\">{$message}</div>\n";
    }
    unset($_SESSION['flash']);
    return $html;
}


// ─────────────────────────────────────────────────────────────────────────
// 8. Pagination
// ─────────────────────────────────────────────────────────────────────────

/**
 * Calculate pagination offset and validate current page.
 *
 * @param  int $total_records
 * @param  int $per_page
 * @return array{page: int, offset: int, total_pages: int, per_page: int}
 */
function paginate(int $total_records, int $per_page = 15): array {
    $total_pages = max(1, (int) ceil($total_records / $per_page));
    $page        = sanitize_int($_GET['page'] ?? 1, 1, $total_pages);
    $offset      = ($page - 1) * $per_page;

    return compact('page', 'offset', 'total_pages', 'per_page');
}

/**
 * Render simple previous/next pagination links.
 *
 * @param  int    $current_page
 * @param  int    $total_pages
 * @param  string $base_url     Base URL (without page query param)
 * @return string HTML
 */
function pagination_links(int $current_page, int $total_pages, string $base_url = ''): string {
    if ($total_pages <= 1) return '';

    $sep    = str_contains($base_url, '?') ? '&amp;' : '?';
    $html   = '<nav class="pagination" aria-label="Page navigation"><ul class="pagination__list">';

    // Previous
    if ($current_page > 1) {
        $html .= '<li><a href="' . $base_url . $sep . 'page=' . ($current_page - 1) . '" class="pagination__link">&laquo; Prev</a></li>';
    }

    // Pages
    for ($p = max(1, $current_page - 2); $p <= min($total_pages, $current_page + 2); $p++) {
        $active  = ($p === $current_page) ? ' pagination__link--active' : '';
        $html   .= '<li><a href="' . $base_url . $sep . 'page=' . $p . '" class="pagination__link' . $active . '">' . $p . '</a></li>';
    }

    // Next
    if ($current_page < $total_pages) {
        $html .= '<li><a href="' . $base_url . $sep . 'page=' . ($current_page + 1) . '" class="pagination__link">Next &raquo;</a></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}


// ─────────────────────────────────────────────────────────────────────────
// 9. Date / Time Helpers
// ─────────────────────────────────────────────────────────────────────────

/**
 * Format a datetime string or timestamp for display.
 */
function format_date(string|int $date, string $format = 'd M Y'): string {
    try {
        $dt = is_int($date)
            ? (new DateTimeImmutable())->setTimestamp($date)
            : new DateTimeImmutable($date);
        return $dt->format($format);
    } catch (Exception) {
        return '—';
    }
}

/**
 * Return a human-readable "time ago" string, e.g. "3 hours ago".
 */
function time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);

    return match (true) {
        $diff < 60          => 'just now',
        $diff < 3600        => floor($diff / 60) . ' minute(s) ago',
        $diff < 86400       => floor($diff / 3600) . ' hour(s) ago',
        $diff < 2592000     => floor($diff / 86400) . ' day(s) ago',
        $diff < 31536000    => floor($diff / 2592000) . ' month(s) ago',
        default             => floor($diff / 31536000) . ' year(s) ago',
    };
}


// ─────────────────────────────────────────────────────────────────────────
// 10. File Upload Helpers
// ─────────────────────────────────────────────────────────────────────────

/**
 * Handle a file upload safely.
 *
 * @param  array  $file           $_FILES element (e.g. $_FILES['avatar'])
 * @param  string $subdirectory   Sub-folder under UPLOAD_DIR
 * @param  array  $allowed_types  Allowed MIME types
 * @return array{success: bool, filename?: string, error?: string}
 */
function handle_upload(
    array  $file,
    string $subdirectory = 'general',
    array  $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf']
): array {

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed with error code: ' . $file['error']];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File exceeds the 5 MB size limit.'];
    }

    // MIME check using finfo (more reliable than the browser-supplied type)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed_types, true)) {
        return ['success' => false, 'error' => 'File type not allowed: ' . $mime];
    }

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_name = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
    $dest_dir = UPLOAD_DIR . $subdirectory . '/';

    if (!is_dir($dest_dir) && !mkdir($dest_dir, 0755, true)) {
        return ['success' => false, 'error' => 'Could not create upload directory.'];
    }

    if (!move_uploaded_file($file['tmp_name'], $dest_dir . $new_name)) {
        return ['success' => false, 'error' => 'Could not save the uploaded file.'];
    }

    return ['success' => true, 'filename' => $subdirectory . '/' . $new_name];
}


// ─────────────────────────────────────────────────────────────────────────
// 11. Miscellaneous
// ─────────────────────────────────────────────────────────────────────────

/**
 * Truncate a string to a given length, appending an ellipsis if needed.
 */
function truncate(string $text, int $length = 100, string $ellipsis = '…'): string {
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . $ellipsis;
}

/**
 * Generate a URL-friendly slug from a string.
 */
function slugify(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\pL\d]+/u', '-', $text);
    $text = trim($text, '-');
    return preg_replace('/-+/', '-', $text);
}

/**
 * Return the base URL for an asset file.
 */
function asset(string $path): string {
    return APP_URL . '/assets/' . ltrim($path, '/');
}

/**
 * Output a JSON response and stop execution.
 *
 * @param mixed $data
 * @param int   $status_code  HTTP status code
 */
function json_response(mixed $data, int $status_code = 200): never {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}