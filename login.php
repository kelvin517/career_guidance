<?php
require_once 'includes/config.php';
if (isLoggedIn()) redirect('dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $result = mysqli_query($conn, "SELECT id, full_name, email, password, role FROM users WHERE email = '$email'");
    if ($row = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['full_name'] = $row['full_name'];
            $_SESSION['role'] = $row['role'];
            log_activity($row['id'], 'login', 'User logged in');
            redirect_by_role($row['role']);
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Email not found.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

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
            --radius:    12px;
        }

        body {
            min-height: 100vh;
            font-family: 'DM Sans', sans-serif;
            background: var(--ink);
            display: flex;
            align-items: stretch;
            justify-content: center;
            margin: 0;
            padding: 0;
        }

        /* ── Page grid ── */
        .auth-page {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
            min-height: 100vh;
        }

        /* ── Left panel ── */
        .panel-left {
            background: var(--ink);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 52px 56px;
            position: relative;
            overflow: hidden;
        }
        .panel-left::before {
            content: '';
            position: absolute;
            width: 440px; height: 440px; border-radius: 50%;
            background: radial-gradient(circle, rgba(200,98,42,.32) 0%, transparent 70%);
            top: -90px; right: -110px; pointer-events: none;
        }
        .panel-left::after {
            content: '';
            position: absolute;
            width: 280px; height: 280px; border-radius: 50%;
            background: radial-gradient(circle, rgba(200,98,42,.16) 0%, transparent 70%);
            bottom: 50px; left: -60px; pointer-events: none;
        }

        .brand { position: relative; z-index: 1; }
        .brand-mark {
            width: 46px; height: 46px;
            background: var(--accent); border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 20px;
        }
        .brand-mark svg { width: 25px; height: 25px; fill: #fff; }
        .brand-name {
            font-family: 'DM Serif Display', serif;
            font-size: 1.65rem; color: #fff;
            letter-spacing: -.02em; line-height: 1.1;
        }
        .brand-sub {
            font-size: .72rem; font-weight: 400;
            color: rgba(255,255,255,.35);
            letter-spacing: .09em; text-transform: uppercase; margin-top: 5px;
        }

        .panel-copy { position: relative; z-index: 1; }
        .panel-quote {
            font-family: 'DM Serif Display', serif;
            font-style: italic; font-size: 2rem;
            line-height: 1.28; color: #fff;
            margin-bottom: 32px; max-width: 340px;
        }
        .panel-features { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 14px; }
        .panel-features li {
            display: flex; align-items: center; gap: 12px;
            font-size: .875rem; color: rgba(255,255,255,.52);
        }
        .feat-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--accent); flex-shrink: 0;
        }

        /* ── Right panel ── */
        .panel-right {
            background: var(--white);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 60px 52px;
        }

        .form-wrap {
            width: 100%; max-width: 400px;
            animation: fadeUp .48s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .form-heading {
            font-family: 'DM Serif Display', serif;
            font-size: 2.1rem; color: var(--ink);
            letter-spacing: -.02em; margin-bottom: 4px;
        }
        .form-sub {
            font-size: .88rem; color: var(--ink-faint); margin-bottom: 36px;
        }

        /* Override Bootstrap alert */
        .alert-danger {
            background: #fdf0ef !important;
            color: var(--danger) !important;
            border: 1px solid #f5c6c4 !important;
            border-radius: var(--radius) !important;
            font-size: .85rem;
            display: flex; align-items: flex-start; gap: 8px;
            padding: 13px 16px !important;
        }
        .alert-danger::before { content: '⚠'; flex-shrink: 0; }

        /* Override Bootstrap labels */
        label.form-label, label {
            display: block;
            font-size: .73rem !important;
            font-weight: 600 !important;
            color: var(--ink-soft) !important;
            letter-spacing: .06em !important;
            text-transform: uppercase !important;
            margin-bottom: 7px !important;
        }

        /* Override Bootstrap form-control */
        .form-control {
            padding: 12px 15px !important;
            border: 1.5px solid var(--border) !important;
            border-radius: var(--radius) !important;
            font-family: 'DM Sans', sans-serif !important;
            font-size: .9rem !important;
            color: var(--ink) !important;
            background: var(--canvas) !important;
            box-shadow: none !important;
            transition: border-color .2s, box-shadow .2s, background .2s !important;
        }
        .form-control::placeholder { color: var(--ink-faint) !important; }
        .form-control:focus {
            border-color: var(--accent) !important;
            box-shadow: 0 0 0 3px rgba(200,98,42,.12) !important;
            background: #fff !important;
        }

        /* Override Bootstrap btn-primary */
        .btn-primary {
            background: var(--accent) !important;
            border: none !important;
            border-radius: var(--radius) !important;
            font-family: 'DM Sans', sans-serif !important;
            font-size: .95rem !important;
            font-weight: 600 !important;
            padding: 13px !important;
            letter-spacing: .01em !important;
            box-shadow: 0 4px 14px rgba(200,98,42,.35) !important;
            transition: background .2s, transform .1s, box-shadow .2s !important;
        }
        .btn-primary:hover {
            background: #b5551f !important;
            box-shadow: 0 6px 20px rgba(200,98,42,.4) !important;
        }
        .btn-primary:active { transform: scale(.985) !important; }

        /* Override Bootstrap card */
        .card { display: none; } /* unused — layout is handled by auth-page grid */

        /* Footer link */
        .text-center a {
            color: var(--accent) !important;
            font-weight: 600;
            text-decoration: none;
        }
        .text-center a:hover { text-decoration: underline; }
        .text-center { color: var(--ink-faint); font-size: .85rem; }

        /* mb-3 spacing */
        .mb-3 { margin-bottom: 20px !important; }

        /* Responsive */
        @media (max-width: 768px) {
            .auth-page { grid-template-columns: 1fr; }
            .panel-left { display: none; }
            .panel-right { padding: 40px 24px; min-height: 100vh; }
        }
    </style>
</head>
<body class="bg-light">

<div class="auth-page">

    <!-- Left decorative panel -->
    <div class="panel-left">
        <div class="brand">
            <div class="brand-mark">
                <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            </div>
            <div class="brand-name">Smart Learning</div>
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

    <!-- Right form panel -->
    <div class="panel-right">
        <div class="form-wrap">

            <h1 class="form-heading">Welcome back</h1>
            <p class="form-sub">Sign in to continue your journey</p>

            <!-- Original container markup preserved exactly, just hidden via CSS above -->
            <div class="container mt-5" style="max-width: 500px; margin: 0 !important; padding: 0 !important;">
                <div class="card shadow" style="display:block; border:none; border-radius:0; box-shadow:none !important;">
                    <div class="card-body p-4" style="padding:0 !important;">

                        <h3 class="text-center mb-4" style="display:none;">Login</h3>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                            </div>
                            <div class="mb-3">
                                <label>Password</label>
                                <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>

                        <p class="text-center mt-3">No account? <a href="register.php">Register</a></p>

                    </div>
                </div>
            </div>

        </div>
    </div>

</div>

</body>
</html>