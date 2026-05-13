<?php
/**
 * Smart Learning Career Guidance System
 * logout.php — Destroy session & logout
 */

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/config.php';

// CSRF check for logout (prevents CSRF-triggered forced logouts via GET)
// Accept either POST with CSRF token OR a signed logout token in GET
$valid_logout = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valid_logout = verify_csrf_token($_POST['csrf_token'] ?? '');
} elseif (!empty($_GET['token'])) {
    // One-time signed token approach for link-based logout
    $valid_logout = hash_equals(
        hash_hmac('sha256', session_id(), APP_SECRET),
        $_GET['token']
    );
}

if ($valid_logout && isset($_SESSION['user_id'])) {

    // Log the logout event before destroying the session
    try {
        log_activity($pdo, $_SESSION['user_id'], 'logout', 'User logged out');
    } catch (Exception $e) {
        error_log('Logout log error: ' . $e->getMessage());
    }
}

// ── Tear down the session completely ──────────────────────────────────────

// 1. Clear all session variables
$_SESSION = [];

// 2. Delete the session cookie from the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => true,
            'samesite' => 'Strict',
        ]
    );
}

// 3. Destroy the session on the server
session_destroy();

// ── Redirect to login page ────────────────────────────────────────────────
header('Location: login.php?logout=1');
exit;