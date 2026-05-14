<?php
/**
 * Smart Learning Career Guidance System
 * login.php — Authentication & role-based redirect
 */

ini_set('display_errors', 0);   // off in production
error_reporting(E_ALL);

require_once 'includes/config.php';

// Already logged in → go straight to dashboard
if (isLoggedIn()) {
    redirect_by_role($_SESSION['role']);
}

$error = '';

// ── POST: authenticate ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = sanitize_input($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';

    } elseif (!$conn) {
        $error = 'Database is currently unavailable. Please try again later.';

    } else {
        // Fetch user — also check is_active so deactivated accounts can't log in
        $stmt = mysqli_prepare($conn,
            'SELECT id, full_name, email, password, role, is_active
               FROM users
              WHERE email = ?
              LIMIT 1'
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user   = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($user && password_verify($password, $user['password'])) {

                if (!(int)$user['is_active']) {
                    // Account exists but is disabled
                    $error = 'Your account has been deactivated. Please contact support.';

                } else {
                    // ── Harden session ────────────────────────────────
                    session_regenerate_id(true); // prevent session fixation

                    $_SESSION['user_id']     = (int)$user['id'];
                    $_SESSION['full_name']   = $user['full_name'];
                    $_SESSION['email']       = $user['email'];
                    $_SESSION['role']        = $user['role'];
                    $_SESSION['logged_in']   = true;
                    $_SESSION['last_active'] = time();

                    // Log the event (fails silently if activity_log table missing)
                    log_activity((int)$user['id'], 'login', 'User logged in');

                    // ── Role-based redirect ───────────────────────────
                    switch ($user['role']) {
                        case 'admin':
                            redirect('views/dashboard/admin_dashboard.php');
                        case 'counselor':
                            redirect('views/dashboard/counselor_dashboard.php');
                        default:  // student
                            redirect('views/dashboard/student_dashboard.php');
                    }
                }

            } else {
                // Generic message — don't reveal whether email exists
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Database error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — <?php echo SITE_NAME; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --ink:        #0f1117;
    --ink-soft:   #4a4d5a;
    --ink-faint:  #9396a3;
    --canvas:     #f7f6f2;
    --white:      #ffffff;
    --accent:     #c8622a;
    --accent-lt:  #f0ebe3;
    --border:     #e2dfd8;
    --danger:     #c0392b;
    --success:    #1f7a5c;
    --info:       #1a5c8a;
    --radius:     12px;
}

html, body { min-height: 100vh; font-family: 'DM Sans', sans-serif; background: var(--canvas); color: var(--ink); }

.page { display: grid; grid-template-columns: 1fr 1fr; min-height: 100vh; }

/* Left panel */
.panel-left {
    background: var(--ink);
    display: flex; flex-direction: column; justify-content: space-between;
    padding: 48px 52px; position: relative; overflow: hidden;
}
.panel-left::before {
    content: ''; position: absolute;
    width: 420px; height: 420px; border-radius: 50%;
    background: radial-gradient(circle, rgba(200,98,42,.35) 0%, transparent 70%);
    top: -80px; right: -100px; pointer-events: none;
}
.panel-left::after {
    content: ''; position: absolute;
    width: 260px; height: 260px; border-radius: 50%;
    background: radial-gradient(circle, rgba(200,98,42,.18) 0%, transparent 70%);
    bottom: 60px; left: -50px; pointer-events: none;
}
.brand { position: relative; z-index: 1; }
.brand-mark {
    width: 44px; height: 44px; background: var(--accent); border-radius: 10px;
    display: flex; align-items: center; justify-content: center; margin-bottom: 18px;
}
.brand-mark svg { width: 24px; height: 24px; fill: none; stroke: #fff; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.brand-name { font-family: 'DM Serif Display', serif; font-size: 1.6rem; color: #fff; letter-spacing: -.02em; line-height: 1.1; }
.brand-sub  { font-size: .75rem; font-weight: 400; color: rgba(255,255,255,.38); letter-spacing: .08em; text-transform: uppercase; margin-top: 5px; }
.panel-copy { position: relative; z-index: 1; }
.panel-quote { font-family: 'DM Serif Display', serif; font-style: italic; font-size: 2rem; line-height: 1.25; color: #fff; margin-bottom: 30px; max-width: 340px; }
.panel-features { list-style: none; display: flex; flex-direction: column; gap: 14px; }
.panel-features li { display: flex; align-items: center; gap: 12px; font-size: .875rem; color: rgba(255,255,255,.55); }
.feat-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--accent); flex-shrink: 0; }

/* Right panel */
.panel-right { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 56px 48px; background: var(--white); }
.form-wrap { width: 100%; max-width: 400px; animation: fadeUp .5s ease both; }
@keyframes fadeUp { from { opacity:0; transform:translateY(18px); } to { opacity:1; transform:translateY(0); } }
.form-heading { font-family: 'DM Serif Display', serif; font-size: 2rem; color: var(--ink); letter-spacing: -.02em; margin-bottom: 4px; }
.form-sub { font-size: .875rem; color: var(--ink-faint); margin-bottom: 30px; }

/* Role pills */
.role-pills { display: flex; gap: 8px; margin-bottom: 30px; }
.role-pill { flex: 1; text-align: center; padding: 9px 4px; border-radius: 10px; font-size: .75rem; font-weight: 600; background: var(--canvas); border: 1.5px solid var(--border); color: var(--ink-soft); letter-spacing: .02em; }
.role-pill span { display: block; font-size: 1.1rem; margin-bottom: 3px; }

/* Alerts */
.alert { display: flex; align-items: flex-start; gap: 10px; padding: 13px 16px; border-radius: var(--radius); font-size: .85rem; margin-bottom: 22px; line-height: 1.5; }
.alert-icon { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }
.alert-danger  { background: #fdf0ef; color: var(--danger); border: 1px solid #f5c6c4; }
.alert-success { background: #edf7f3; color: var(--success); border: 1px solid #b8e0d2; }
.alert-info    { background: #edf4fb; color: var(--info);    border: 1px solid #b8d4ef; }

/* Fields */
.form-group { margin-bottom: 20px; }
label { display: block; font-size: .75rem; font-weight: 600; color: var(--ink-soft); letter-spacing: .05em; text-transform: uppercase; margin-bottom: 7px; }
.form-control { width: 100%; padding: 12px 16px; border: 1.5px solid var(--border); border-radius: var(--radius); font-family: 'DM Sans', sans-serif; font-size: .9rem; color: var(--ink); background: var(--canvas); transition: border-color .2s, box-shadow .2s, background .2s; outline: none; }
.form-control::placeholder { color: var(--ink-faint); }
.form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(200,98,42,.12); background: #fff; }

.pw-wrap { position: relative; }
.pw-wrap .form-control { padding-right: 48px; }
.pw-toggle { position: absolute; right: 13px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 1.05rem; color: var(--ink-faint); padding: 4px; line-height: 1; transition: color .2s; }
.pw-toggle:hover { color: var(--ink); }

.options-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; }
.checkbox-label { display: flex; align-items: center; gap: 8px; font-size: .85rem; color: var(--ink-soft); cursor: pointer; text-transform: none; letter-spacing: 0; font-weight: 400; }
.checkbox-label input[type="checkbox"] { width: 15px; height: 15px; accent-color: var(--accent); cursor: pointer; }
.link-quiet { font-size: .85rem; color: var(--accent); text-decoration: none; font-weight: 500; transition: opacity .2s; }
.link-quiet:hover { opacity:.7; text-decoration: underline; }

.btn-submit { display: block; width: 100%; padding: 14px; background: var(--accent); color: #fff; border: none; border-radius: var(--radius); font-family: 'DM Sans', sans-serif; font-size: .95rem; font-weight: 600; cursor: pointer; letter-spacing: .01em; transition: background .2s, transform .1s, box-shadow .2s; box-shadow: 0 4px 14px rgba(200,98,42,.35); }
.btn-submit:hover { background: #b5551f; box-shadow: 0 6px 20px rgba(200,98,42,.4); }
.btn-submit:active { transform: scale(.985); }

.divider { display: flex; align-items: center; gap: 14px; margin: 24px 0; color: var(--ink-faint); font-size: .78rem; }
.divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }

.footer-text { text-align: center; font-size: .85rem; color: var(--ink-faint); }
.footer-text a { color: var(--accent); font-weight: 600; text-decoration: none; }
.footer-text a:hover { text-decoration: underline; }

@media (max-width: 768px) {
    .page { grid-template-columns: 1fr; }
    .panel-left { display: none; }
    .panel-right { padding: 40px 24px; min-height: 100vh; }
}
</style>
</head>
<body>

<div class="page">

    <!-- ── Left decorative panel ── -->
    <div class="panel-left">
        <div class="brand">
            <div class="brand-mark">
                <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            </div>
            <div class="brand-name"><?php echo SITE_NAME; ?></div>
            <div class="brand-sub">Career Guidance System</div>
        </div>

        <div class="panel-copy">
            <p class="panel-quote">"Your future begins with the right guidance."</p>
            <ul class="panel-features">
                <li><span class="feat-dot"></span> Personalised career path recommendations</li>
                <li><span class="feat-dot"></span> Connect with expert counsellors</li>
                <li><span class="feat-dot"></span> Track your academic &amp; professional growth</li>
                <li><span class="feat-dot"></span> Access curated learning resources</li>
            </ul>
        </div>
    </div>

    <!-- ── Right form panel ── -->
    <div class="panel-right">
        <div class="form-wrap">

            <h1 class="form-heading">Welcome back</h1>
            <p class="form-sub">Sign in to continue your journey</p>

            <!-- Role hint pills -->
            <div class="role-pills">
                <div class="role-pill"><span>🎓</span>Student</div>
                <div class="role-pill"><span>💼</span>Counsellor</div>
                <div class="role-pill"><span>⚙</span>Admin</div>
            </div>

            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <span class="alert-icon">⚠</span>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success">
                    <span class="alert-icon">✓</span>
                    Registration successful! You can now sign in.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['logout'])): ?>
                <div class="alert alert-info">
                    <span class="alert-icon">ℹ</span>
                    You have been signed out successfully.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['timeout'])): ?>
                <div class="alert alert-info">
                    <span class="alert-icon">ℹ</span>
                    Your session expired due to inactivity. Please sign in again.
                </div>
            <?php endif; ?>

            <!-- Login form -->
            <form method="POST" novalidate>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="you@example.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           required autofocus autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="pw-wrap">
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Enter your password"
                               required autocomplete="current-password">
                        <button type="button" class="pw-toggle" id="pwToggle" aria-label="Toggle password visibility">👁</button>
                    </div>
                </div>

                <div class="options-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember_me"> Remember me
                    </label>
                    <a href="forgot_password.php" class="link-quiet">Forgot password?</a>
                </div>

                <button type="submit" class="btn-submit">Sign In</button>
            </form>

            <div class="divider">or</div>

            <p class="footer-text">
                Don&rsquo;t have an account? <a href="register.php">Create one here</a>
            </p>

        </div>
    </div>

</div>

<script>
(function () {
    var toggle = document.getElementById('pwToggle');
    var field  = document.getElementById('password');
    toggle.addEventListener('click', function () {
        var isText = field.type === 'text';
        field.type    = isText ? 'password' : 'text';
        toggle.textContent = isText ? '👁' : '🙈';
    });
})();
</script>
</body>
</html>