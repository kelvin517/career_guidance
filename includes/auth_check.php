<?php
/**
 * Smart Learning Career Guidance System
 * includes/auth_check.php — Role-based access control (RBAC)
 *
 * Usage patterns
 * ──────────────
 *  // Require any authenticated user:
 *  require_login();
 *
 *  // Require a specific role:
 *  require_role('admin');
 *
 *  // Require one of several roles:
 *  require_role(['counselor', 'admin']);
 *
 *  // Guard a whole page silently (redirect on failure):
 *  guard_page('student');
 *
 *  // Check inline without redirecting:
 *  if (can('manage_users')) { ... }
 *
 *  // Check resource ownership:
 *  require_ownership($resource_user_id);
 */

// functions.php must already be loaded before this file
if (!function_exists('sanitize_input')) {
    require_once __DIR__ . '/functions.php';
}

// ─────────────────────────────────────────────────────────────────────────
// Permission Matrix
// ─────────────────────────────────────────────────────────────────────────
// Maps permission keys to the roles that hold them.
// Add / remove permissions here as the project grows.

const ROLE_PERMISSIONS = [

    // ── Content & Courses ──────────────────────────────────────────────
    'view_courses'         => ['student', 'counselor', 'admin'],
    'enroll_course'        => ['student'],
    'create_course'        => ['counselor', 'admin'],
    'edit_course'          => ['counselor', 'admin'],
    'delete_course'        => ['admin'],
    'publish_course'       => ['admin'],

    // ── Career Guidance ────────────────────────────────────────────────
    'request_guidance'     => ['student'],
    'provide_guidance'     => ['counselor', 'admin'],
    'view_guidance_list'   => ['counselor', 'admin'],

    // ── Appointments / Bookings ────────────────────────────────────────
    'book_appointment'     => ['student'],
    'manage_appointments'  => ['counselor', 'admin'],
    'cancel_appointment'   => ['student', 'counselor', 'admin'],

    // ── Resources & Materials ──────────────────────────────────────────
    'view_resources'       => ['student', 'counselor', 'admin'],
    'upload_resource'      => ['counselor', 'admin'],
    'delete_resource'      => ['admin'],

    // ── User Management ────────────────────────────────────────────────
    'view_profile'         => ['student', 'counselor', 'admin'],
    'edit_own_profile'     => ['student', 'counselor', 'admin'],
    'view_all_users'       => ['admin'],
    'edit_any_user'        => ['admin'],
    'deactivate_user'      => ['admin'],
    'delete_user'          => ['admin'],

    // ── Reports & Analytics ────────────────────────────────────────────
    'view_own_progress'    => ['student'],
    'view_student_reports' => ['counselor', 'admin'],
    'view_system_reports'  => ['admin'],
    'export_reports'       => ['admin'],

    // ── Notifications ──────────────────────────────────────────────────
    'send_notification'    => ['counselor', 'admin'],
    'receive_notification' => ['student', 'counselor', 'admin'],

    // ── Settings ──────────────────────────────────────────────────────
    'manage_settings'      => ['admin'],
    'view_activity_log'    => ['admin'],
];


// ─────────────────────────────────────────────────────────────────────────
// Core Access-Control Functions
// ─────────────────────────────────────────────────────────────────────────

/**
 * Require the visitor to be authenticated.
 * Redirects to login.php if not.
 */
function require_login(): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Please log in to access that page.');
        $intended = urlencode($_SERVER['REQUEST_URI'] ?? '');
        redirect('login.php' . ($intended ? '?next=' . $intended : ''));
    }
    // Also enforce session timeout
    enforce_session_timeout();
}

/**
 * Require the authenticated user to hold one or more roles.
 * Redirects appropriately if the check fails.
 *
 * @param string|array $roles  Required role(s)
 */
function require_role(string|array $roles): void {
    require_login();

    if (!has_role($roles)) {
        // Log the access denial
        error_log(sprintf(
            '[AUTH] Access denied — user #%d (role: %s) attempted to reach %s',
            current_user_id() ?? 0,
            current_role() ?? 'none',
            $_SERVER['REQUEST_URI'] ?? ''
        ));

        set_flash('error', 'You do not have permission to access that page.');
        redirect_by_role(current_role() ?? 'login.php');
    }
}

/**
 * Shorthand: redirect silently to the user's dashboard if access is denied.
 * Suitable as the very first call in a page controller.
 *
 * @param string|array $roles
 */
function guard_page(string|array $roles): void {
    require_role($roles);
}

/**
 * Check whether the current user has a specific permission (non-redirecting).
 *
 * @param  string $permission  A key from ROLE_PERMISSIONS
 * @return bool
 */
function can(string $permission): bool {
    if (!is_logged_in()) return false;
    $allowed_roles = ROLE_PERMISSIONS[$permission] ?? [];
    return in_array(current_role(), $allowed_roles, true);
}

/**
 * Assert a permission and halt with HTTP 403 if it is not held.
 *
 * @param string $permission
 * @param bool   $json  Return a JSON error instead of HTML (for API endpoints)
 */
function require_permission(string $permission, bool $json = false): void {
    require_login();

    if (!can($permission)) {
        if ($json) {
            json_response(['error' => 'Forbidden', 'message' => 'Insufficient permissions.'], 403);
        }
        http_response_code(403);
        set_flash('error', 'You do not have the required permission for that action.');
        redirect_by_role(current_role());
    }
}

/**
 * Require that the current user owns a resource (by matching user IDs).
 * Admins bypass this check.
 *
 * @param int  $resource_user_id  The user_id stored with the resource
 * @param bool $admin_bypass      Whether admins skip ownership check (default: true)
 */
function require_ownership(int $resource_user_id, bool $admin_bypass = true): void {
    require_login();

    $is_owner = ((int)current_user_id() === $resource_user_id);
    $is_admin = ($admin_bypass && has_role('admin'));

    if (!$is_owner && !$is_admin) {
        set_flash('error', 'You can only access your own resources.');
        redirect_by_role(current_role());
    }
}


// ─────────────────────────────────────────────────────────────────────────
// Template Helpers (for use in views / layouts)
// ─────────────────────────────────────────────────────────────────────────

/**
 * Render a block of HTML only if the current user has the given permission.
 * Can be used as a wrapper around echoed content.
 *
 * Usage:
 *   if_can('delete_course', fn() => '<a href="...">Delete</a>');
 *
 * @param  string   $permission
 * @param  callable $callback    Should return (or echo) the HTML
 * @param  callable|null $else   Optional fallback callable if permission denied
 */
function if_can(string $permission, callable $callback, ?callable $else = null): void {
    if (can($permission)) {
        $result = $callback();
        if (is_string($result)) echo $result;
    } elseif ($else !== null) {
        $result = $else();
        if (is_string($result)) echo $result;
    }
}

/**
 * Return an HTML attribute string to disable an element when a permission
 * is not held — useful for buttons.
 *
 * @param  string $permission
 * @param  string $attribute   Default: 'disabled'
 * @return string              e.g. ' disabled title="No permission"' or ''
 */
function disabled_if_cannot(string $permission, string $attribute = 'disabled'): string {
    if (can($permission)) return '';
    return ' ' . $attribute . ' title="You do not have permission for this action"';
}


// ─────────────────────────────────────────────────────────────────────────
// API / AJAX Access Control
// ─────────────────────────────────────────────────────────────────────────

/**
 * For AJAX / JSON endpoints: return 401 if not logged in, 403 if wrong role.
 *
 * @param string|array $roles  Required role(s)
 */
function api_require_role(string|array $roles): void {
    if (!is_logged_in()) {
        json_response(['error' => 'Unauthorized', 'message' => 'Authentication required.'], 401);
    }
    if (!has_role($roles)) {
        json_response(['error' => 'Forbidden', 'message' => 'Insufficient role.'], 403);
    }
}

/**
 * For AJAX endpoints: verify CSRF token and return JSON 403 on failure.
 */
function api_require_csrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($token)) {
        json_response(['error' => 'CSRF validation failed.'], 403);
    }
}