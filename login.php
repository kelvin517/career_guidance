<?php
/**
 * Smart Learning Career Guidance System
 * login.php — Login form & authentication handler
 */

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/config.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect_by_role($_SESSION['role']);
}

$error   = '';
$success = '';

// ── POST: handle login submission ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = sanitize_input($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';
    $csrf     = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $error = 'Invalid request. Please try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, full_name, email, password, role, is_active
               FROM users
              WHERE email = :email
              LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if (!$user['is_active']) {
                $error = 'Your account has been deactivated. Contact support.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']     = $user['id'];
                $_SESSION['full_name']   = $user['full_name'];
                $_SESSION['email']       = $user['email'];
                $_SESSION['role']        = $user['role'];
                $_SESSION['logged_in']   = true;
                $_SESSION['last_active'] = time();
                log_activity($pdo, $user['id'], 'login', 'User logged in');
                redirect_by_role($user['role']);
                exit;
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Smart Learning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --ink:       #0f1117;
            --ink-soft:  #4a4d5a;
            --ink-faint: #9396a3;
            --canvas:    #f7f6f2;
            --white:     #ffffff;
            --accent:    #c8622a;
            --accent-lt: #f0ebe3;
            --border:    #e2dfd8;
            --danger:    #c0392b;
            --success:   #1f7a5c;
            --info:      #1a5c8a;
            --radius:    12px;
            --shadow:    0 2px 6px rgba(0,0,0,.06), 0 12px 40px rgba(0,0,0,.08);
        }

        html, body {
            min-height: 100vh;
            font-family: 'DM Sans', sans-serif;
            background-color: var(--canvas);
            color: var(--ink);
        }

        /* ── Layout ── */
        .page {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 100vh;
        }

        /* ── Left panel ── */
        .panel-left {
            background: var(--ink);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 48px 52px;
            position: relative;
            overflow: hidden;
        }

        .panel-left::before {
            content: '';
            position: absolute;
            width: 420px; height: 420px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(200,98,42,.35) 0%, transparent 70%);
            top: -80px; right: -100px;
            pointer-events: none;
        }
        .panel-left::after {
            content: '';
            position: absolute;
            width: 280px; height: 280px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(200,98,42,.2) 0%, transparent 70%);
            bottom: 60px; left: -60px;
            pointer-events: none;
        }

        .panel-brand {
            position: relative; z-index: 1;
        }

        .brand-mark {
            width: 44px; height: 44px;
            background: var(--accent);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 20px;
        }
        .brand-mark svg { width: 24px; height: 24px; fill: #fff; }

        .brand-name {
            font-family: 'DM Serif Display', serif;
            font-size: 1.6rem;
            color: #fff;
            letter-spacing: -.02em;
            line-height: 1.1;
        }
        .brand-sub {
            font-size: .8rem;
            font-weight: 400;
            color: rgba(255,255,255,.45);
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .panel-copy {
            position: relative; z-index: 1;
        }

        .panel-quote {
            font-family: 'DM Serif Display', serif;
            font-style: italic;
            font-size: 2rem;
            line-height: 1.25;
            color: #fff;
            margin-bottom: 28px;
            max-width: 340px;
        }

        .panel-features {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .panel-features li {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: .875rem;
            color: rgba(255,255,255,.65);
            font-weight: 400;
        }
        .feat-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--accent);
            flex-shrink: 0;
        }

        /* ── Right panel ── */
        .panel-right {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 56px 48px;
            background: var(--white);
        }

        .form-wrap {
            width: 100%;
            max-width: 400px;
            animation: fadeUp .5s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .form-heading {
            font-family: 'DM Serif Display', serif;
            font-size: 2rem;
            color: var(--ink);
            letter-spacing: -.02em;
            margin-bottom: 6px;
        }
        .form-sub {
            font-size: .875rem;
            color: var(--ink-faint);
            margin-bottom: 36px;
        }

        /* Alerts */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 13px 16px;
            border-radius: var(--radius);
            font-size: .85rem;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        .alert-icon { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }
        .alert-danger  { background: #fdf0ef; color: var(--danger); border: 1px solid #f5c6c4; }
        .alert-success { background: #edf7f3; color: var(--success); border: 1px solid #b8e0d2; }
        .alert-info    { background: #edf4fb; color: var(--info);    border: 1px solid #b8d4ef; }

        /* Form groups */
        .form-group { margin-bottom: 20px; }

        label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            color: var(--ink-soft);
            letter-spacing: .04em;
            text-transform: uppercase;
            margin-bottom: 7px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            font-family: 'DM Sans', sans-serif;
            font-size: .9rem;
            color: var(--ink);
            background: var(--canvas);
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }
        .form-control::placeholder { color: var(--ink-faint); }
        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(200,98,42,.12);
            background: #fff;
        }

        .input-password-wrapper {
            position: relative;
        }
        .input-password-wrapper .form-control {
            padding-right: 46px;
        }
        .toggle-password {
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            cursor: pointer; font-size: 1.1rem;
            color: var(--ink-faint);
            padding: 4px;
            transition: color .2s;
        }
        .toggle-password:hover { color: var(--ink); }

        /* Remember / Forgot row */
        .form-row {
            display: flex;
            align-items: center;
            margin-bottom: 28px;
        }
        .form-row--space-between { justify-content: space-between; }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .85rem;
            color: var(--ink-soft);
            text-transform: none;
            letter-spacing: 0;
            font-weight: 400;
            cursor: pointer;
        }
        .checkbox-label input[type="checkbox"] {
            width: 15px; height: 15px;
            accent-color: var(--accent);
            cursor: pointer;
        }

        .link-muted {
            font-size: .85rem;
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
            transition: opacity .2s;
        }
        .link-muted:hover { opacity: .75; text-decoration: underline; }

        /* Submit button */
        .btn-primary {
            display: block;
            width: 100%;
            padding: 14px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            font-family: 'DM Sans', sans-serif;
            font-size: .95rem;
            font-weight: 600;
            cursor: pointer;
            letter-spacing: .01em;
            transition: background .2s, transform .1s, box-shadow .2s;
            box-shadow: 0 4px 14px rgba(200,98,42,.35);
        }
        .btn-primary:hover {
            background: #b5551f;
            box-shadow: 0 6px 20px rgba(200,98,42,.4);
        }
        .btn-primary:active { transform: scale(.985); }

        .auth-footer-text {
            text-align: center;
            font-size: .85rem;
            color: var(--ink-faint);
            margin-top: 24px;
        }
        .auth-footer-text a {
            color: var(--accent);
            font-weight: 600;
            text-decoration: none;
        }
        .auth-footer-text a:hover { text-decoration: underline; }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .page { grid-template-columns: 1fr; }
            .panel-left { display: none; }
            .panel-right { padding: 40px 24px; min-height: 100vh; }
        }
    </style>
</head>
<body>

<div class="page">

    <!-- Left decorative panel -->
    <div class="panel-left">
        <div class="panel-brand">
            <div class="brand-mark">
                <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            </div>
            <div class="brand-name">Smart Learning</div>
            <div class="brand-sub">Career Guidance System</div>
        </div>

        <div class="panel-copy">
            <p class="panel-quote">"Your future begins with the right guidance."</p>
            <ul class="panel-features">
                <li><span class="feat-dot"></span>Personalised career path recommendations</li>
                <li><span class="feat-dot"></span>Connect with expert counsellors</li>
                <li><span class="feat-dot"></span>Track your academic & professional growth</li>
                <li><span class="feat-dot"></span>Access curated learning resources</li>
            </ul>
        </div>
    </div>

    <!-- Right form panel -->
    <div class="panel-right">
        <div class="form-wrap">

            <h1 class="form-heading">Welcome back</h1>
            <p class="form-sub">Sign in to continue your journey</p>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <span class="alert-icon">⚠</span>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($_GET['registered'])): ?>
                <div class="alert alert-success" role="alert">
                    <span class="alert-icon">✓</span>
                    Registration successful! You can now log in.
                </div>
            <?php endif; ?>

            <?php if (!empty($_GET['logout'])): ?>
                <div class="alert alert-info" role="alert">
                    <span class="alert-icon">ℹ</span>
                    You have been logged out successfully.
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="you@example.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required
                        autocomplete="email"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-password-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                            👁
                        </button>
                    </div>
                </div>

                <div class="form-row form-row--space-between">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember_me"> Remember me
                    </label>
                    <a href="forgot_password.php" class="link-muted">Forgot password?</a>
                </div>

                <button type="submit" class="btn-primary">Sign In</button>
            </form>

            <p class="auth-footer-text">
                Don&rsquo;t have an account? <a href="register.php">Create one here</a>
            </p>

        </div>
    </div>

</div>

<script>
document.querySelector('.toggle-password').addEventListener('click', function () {
    const pwd = document.getElementById('password');
    const isText = pwd.type === 'text';
    pwd.type = isText ? 'password' : 'text';
    this.textContent = isText ? '👁' : '🙈';
});
</script>
</body>
</html>