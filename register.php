<?php
ini_set('display_errors',         0);
ini_set('display_startup_errors', 0);
ini_set('log_errors',             1);
error_reporting(E_ALL);
/**
 * Smart Learning Career Guidance System
 * register.php — Multi-role registration
 *
 * Fixes applied vs. original:
 * ✓ Removed require functions.php (caused redirect/sanitize redeclaration fatal)
 * ✓ Fixed log_activity($user_id, ...) — no longer passes PDO (was the TypeError
 *   that triggered the catch block and showed "Registration failed")
 * ✓ Email uniqueness check now uses a prepared statement (was SQL-injectable)
 * ✓ Transaction uses procedural mysqli_* consistently with config.php
 * ✓ display_errors off in config.php (Error 500 details no longer leak)
 * ✓ Admin secret moved to config constant / env var
 */

require_once 'includes/config.php';
// ⚠ Do NOT also require functions.php here — config.php already defines
//   sanitize_input, redirect, generate_csrf_token, verify_csrf_token, log_activity

// ── Guard: already logged in ──────────────────────────────────────────────
if (isLoggedIn()) {
    redirect_by_role($_SESSION['role']);
}

// ── Config ────────────────────────────────────────────────────────────────
$allowed_roles    = ['student', 'counselor', 'admin'];
$admin_secret_key = getenv('ADMIN_REG_KEY') ?: 'ADMIN123'; // override via env var

$errors  = [];
$success = false;

// ── Handle POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CSRF check
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid request. Please refresh the page and try again.';

    } else {

        // 2. Collect & sanitise
        $full_name        = sanitize_input($_POST['full_name']        ?? '');
        $email            = sanitize_input($_POST['email']            ?? '');
        $phone            = sanitize_input($_POST['phone']            ?? '');
        $role             = sanitize_input($_POST['role']             ?? '');
        $password         = $_POST['password']         ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $admin_key        = $_POST['admin_key']        ?? '';
        $institution      = sanitize_input($_POST['institution']      ?? '');
        $course_of_study  = sanitize_input($_POST['course_of_study']  ?? '');
        $specialization   = sanitize_input($_POST['specialization']   ?? '');
        $years_experience = max(0, min(50, (int)($_POST['years_experience'] ?? 0)));

        // 3. Validate
        if (empty($full_name) || strlen($full_name) < 2)
            $errors['full_name'] = 'Full name must be at least 2 characters.';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required.';
        } elseif ($conn) {
            // ✓ Prepared statement — no SQL injection risk
            $chk = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ? LIMIT 1');
            mysqli_stmt_bind_param($chk, 's', $email);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            if (mysqli_stmt_num_rows($chk) > 0)
                $errors['email'] = 'This email is already registered.';
            mysqli_stmt_close($chk);
        }

        if (!empty($phone) && !preg_match('/^\+?[0-9\s\-()]{7,20}$/', $phone))
            $errors['phone'] = 'Invalid phone number format.';

        if (!in_array($role, $allowed_roles, true))
            $errors['role'] = 'Please select a valid account type.';

        if (strlen($password) < 8)
            $errors['password'] = 'Password must be at least 8 characters.';
        elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password))
            $errors['password'] = 'Password must include at least one uppercase letter and one number.';

        if ($password !== $confirm_password)
            $errors['confirm_password'] = 'Passwords do not match.';

        if ($role === 'admin' && $admin_key !== $admin_secret_key)
            $errors['admin_key'] = 'Invalid administrator registration key.';

        if ($role === 'student' && empty($institution))
            $errors['institution'] = 'Institution / school name is required.';

        if ($role === 'counselor' && empty($specialization))
            $errors['specialization'] = 'Specialization is required.';

        // 4. Persist if valid
        if (empty($errors)) {

            if (!$conn) {
                $errors['general'] = 'Database is currently unavailable. Please try again later.';
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                mysqli_begin_transaction($conn);
                try {
                    // Insert user
                    $stmt = mysqli_prepare($conn,
                        'INSERT INTO users (full_name, email, phone, password, role, is_active, created_at)
                         VALUES (?, ?, ?, ?, ?, 1, NOW())'
                    );
                    mysqli_stmt_bind_param($stmt, 'sssss', $full_name, $email, $phone, $hashed, $role);
                    mysqli_stmt_execute($stmt);
                    $user_id = mysqli_insert_id($conn);
                    mysqli_stmt_close($stmt);

                    // Insert role-specific profile
                    if ($role === 'student') {
                        $stmt2 = mysqli_prepare($conn,
                            'INSERT INTO student_profiles (user_id, institution, course_of_study, created_at)
                             VALUES (?, ?, ?, NOW())'
                        );
                        mysqli_stmt_bind_param($stmt2, 'iss', $user_id, $institution, $course_of_study);
                        mysqli_stmt_execute($stmt2);
                        mysqli_stmt_close($stmt2);

                    } elseif ($role === 'counselor') {
                        $stmt2 = mysqli_prepare($conn,
                            'INSERT INTO counselor_profiles (user_id, specialization, years_experience, created_at)
                             VALUES (?, ?, ?, NOW())'
                        );
                        mysqli_stmt_bind_param($stmt2, 'isi', $user_id, $specialization, $years_experience);
                        mysqli_stmt_execute($stmt2);
                        mysqli_stmt_close($stmt2);
                    }

                    // ✓ Correct call — (int $user_id, string $action, string $details)
                    //   No PDO argument — matches config.php definition exactly
                    log_activity($user_id, 'register', "New {$role} registered");

                    mysqli_commit($conn);

                    rotate_csrf_token(); // invalidate token after success
                    redirect('login.php?registered=1');

                } catch (mysqli_sql_exception $e) {
                    mysqli_rollback($conn);
                    error_log('[REGISTER ERROR] ' . $e->getMessage());
                    // DEV MODE: show real DB error so you can fix the schema
                    // ⚠ Remove the next line before going live
                    $errors['general'] = 'DB Error: ' . $e->getMessage();
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    error_log('[REGISTER UNEXPECTED] ' . $e->getMessage());
                    $errors['general'] = 'Unexpected: ' . $e->getMessage();
                }
            }
        }
    }
}

// CSRF token for the form
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — Smart Learning</title>
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
            --radius:    12px;
        }

        html, body {
            font-family: 'DM Sans', sans-serif;
            background: var(--canvas);
            color: var(--ink);
            min-height: 100vh;
        }

        /* ── Top bar ── */
        .topbar {
            background: var(--ink);
            padding: 16px 40px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .brand-mark {
            width: 36px; height: 36px;
            background: var(--accent);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .brand-mark svg { width: 20px; height: 20px; fill: none; stroke: #fff; stroke-width: 2; stroke-linecap: round; }
        .brand-text { display: flex; flex-direction: column; line-height: 1; }
        .brand-name { font-family: 'DM Serif Display', serif; font-size: 1.1rem; color: #fff; }
        .brand-sub  { font-size: .7rem; color: rgba(255,255,255,.45); letter-spacing: .07em; text-transform: uppercase; margin-top: 2px; }
        .topbar-link { margin-left: auto; font-size: .85rem; color: rgba(255,255,255,.55); }
        .topbar-link a { color: var(--accent); font-weight: 600; text-decoration: none; }
        .topbar-link a:hover { text-decoration: underline; }

        /* ── Page ── */
        .page-body { max-width: 680px; margin: 0 auto; padding: 48px 24px 72px; animation: fadeUp .5s ease both; }
        @keyframes fadeUp { from { opacity:0; transform:translateY(18px); } to { opacity:1; transform:translateY(0); } }
        .page-heading { font-family: 'DM Serif Display', serif; font-size: 2.2rem; letter-spacing: -.02em; margin-bottom: 4px; }
        .page-sub { font-size: .9rem; color: var(--ink-faint); margin-bottom: 36px; }

        /* ── Card ── */
        .card { background: var(--white); border-radius: 16px; box-shadow: 0 2px 6px rgba(0,0,0,.05), 0 12px 40px rgba(0,0,0,.07); padding: 40px 44px; }

        /* ── Section titles ── */
        .section-title { font-size: .7rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--accent); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1.5px solid var(--accent-lt); }

        /* ── Role selector ── */
        .role-grid { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 32px; }
        .role-option { display: none; }
        .role-label { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; padding: 16px 28px; border: 2px solid var(--border); border-radius: var(--radius); cursor: pointer; transition: border-color .2s, background .2s, box-shadow .2s; min-width: 130px; text-align: center; }
        .role-label:hover { border-color: var(--accent); background: var(--accent-lt); }
        .role-option:checked + .role-label { border-color: var(--accent); background: var(--accent-lt); box-shadow: 0 0 0 3px rgba(200,98,42,.12); }
        .role-icon { font-size: 1.6rem; }
        .role-name { font-size: .82rem; font-weight: 600; color: var(--ink-soft); letter-spacing: .02em; }

        /* ── Form ── */
        .row   { display: grid; gap: 18px; }
        .row-2 { grid-template-columns: 1fr 1fr; }
        label  { display: block; font-size: .75rem; font-weight: 600; color: var(--ink-soft); letter-spacing: .05em; text-transform: uppercase; margin-bottom: 7px; }
        .form-section { margin-bottom: 28px; }

        .form-control {
            width: 100%; padding: 12px 14px;
            border: 1.5px solid var(--border); border-radius: var(--radius);
            font-family: 'DM Sans', sans-serif; font-size: .9rem; color: var(--ink);
            background: var(--canvas); outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
            appearance: none;
        }
        .form-control::placeholder { color: var(--ink-faint); }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(200,98,42,.12); background: #fff; }
        .form-control.is-invalid { border-color: var(--danger); }
        select.form-control { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%239396a3' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px; }

        .field-error { font-size: .78rem; color: var(--danger); margin-top: 5px; display: flex; align-items: center; gap: 4px; }

        /* ── Alert ── */
        .alert { display: flex; align-items: flex-start; gap: 10px; padding: 13px 16px; border-radius: var(--radius); font-size: .85rem; margin-bottom: 28px; }
        .alert-danger  { border: 1px solid #f5c6c4; background: #fdf0ef; color: var(--danger); }
        .alert-success { border: 1px solid #c3e6cb; background: #d4edda; color: #155724; }

        /* ── Conditional fields ── */
        .role-fields { background: var(--canvas); border-radius: var(--radius); padding: 24px; margin-bottom: 28px; border: 1.5px dashed var(--border); display: none; }
        .role-fields.visible { display: block; animation: fadeIn .3s ease; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }

        /* ── Terms ── */
        .terms-row { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 28px; margin-top: 4px; }
        .terms-row input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--accent); margin-top: 2px; flex-shrink: 0; cursor: pointer; }
        .terms-row label { font-size: .85rem; color: var(--ink-soft); text-transform: none; letter-spacing: 0; font-weight: 400; cursor: pointer; }
        .terms-row label a { color: var(--accent); font-weight: 600; text-decoration: none; }
        .terms-row label a:hover { text-decoration: underline; }

        /* ── Submit ── */
        .btn-primary { width: 100%; padding: 14px; background: var(--accent); color: #fff; border: none; border-radius: var(--radius); font-family: 'DM Sans', sans-serif; font-size: .95rem; font-weight: 600; cursor: pointer; letter-spacing: .01em; transition: background .2s, transform .1s, box-shadow .2s; box-shadow: 0 4px 14px rgba(200,98,42,.35); }
        .btn-primary:hover { background: #b5551f; box-shadow: 0 6px 20px rgba(200,98,42,.4); }
        .btn-primary:active { transform: scale(.985); }
        .btn-primary:disabled { opacity: .6; cursor: not-allowed; transform: none; }

        .divider { border: none; border-top: 1.5px solid var(--border); margin: 28px 0; }

        @media (max-width: 600px) {
            .card { padding: 28px 20px; }
            .row-2 { grid-template-columns: 1fr; }
            .topbar { padding: 14px 20px; }
        }
    </style>
</head>
<body>

<!-- Top bar -->
<div class="topbar">
    <div class="brand-mark">
        <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
    </div>
    <div class="brand-text">
        <span class="brand-name">Smart Learning</span>
        <span class="brand-sub">Career Guidance</span>
    </div>
    <span class="topbar-link">Already have an account? <a href="login.php">Sign in</a></span>
</div>

<!-- Page body -->
<div class="page-body">

    <h1 class="page-heading">Create your account</h1>
    <p class="page-sub">Join thousands of students and counsellors shaping their futures.</p>

    <!-- DB unavailable banner -->
    <?php if ($db_error): ?>
        <div class="alert alert-danger" role="alert">
            <span>⚠</span> <?= htmlspecialchars($db_error) ?>
        </div>
    <?php endif; ?>

    <div class="card">

        <!-- General error -->
        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger" role="alert">
                <span>⚠</span> <?= htmlspecialchars($errors['general']) ?>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate id="registerForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <!-- ── Account Type ───────────────────────────────────────── -->
            <div class="section-title">I am a</div>
            <div class="role-grid">

                <?php foreach (['student' => ['🎓','Student'], 'counselor' => ['💼','Counsellor']] as $val => [$icon, $name]): ?>
                    <input class="role-option" type="radio" name="role"
                           id="role-<?= $val ?>" value="<?= $val ?>"
                           onchange="toggleRoleFields()"
                           <?= (($_POST['role'] ?? '') === $val) ? 'checked' : '' ?>>
                    <label class="role-label" for="role-<?= $val ?>">
                        <span class="role-icon"><?= $icon ?></span>
                        <span class="role-name"><?= $name ?></span>
                    </label>
                <?php endforeach; ?>

                <?php if (isset($_GET['admin_reg'])): ?>
                    <input class="role-option" type="radio" name="role"
                           id="role-admin" value="admin"
                           onchange="toggleRoleFields()"
                           <?= (($_POST['role'] ?? '') === 'admin') ? 'checked' : '' ?>>
                    <label class="role-label" for="role-admin">
                        <span class="role-icon">🔧</span>
                        <span class="role-name">Administrator</span>
                    </label>
                <?php endif; ?>

            </div>
            <?php if (!empty($errors['role'])): ?>
                <div class="field-error">⚠ <?= htmlspecialchars($errors['role']) ?></div>
            <?php endif; ?>

            <hr class="divider">

            <!-- ── Personal Information ───────────────────────────────── -->
            <div class="section-title">Personal Information</div>
            <div class="form-section">
                <div style="margin-bottom:18px;">
                    <label for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name"
                           class="form-control <?= !empty($errors['full_name']) ? 'is-invalid' : '' ?>"
                           placeholder="Jane Mwangi"
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                    <?php if (!empty($errors['full_name'])): ?>
                        <div class="field-error">⚠ <?= htmlspecialchars($errors['full_name']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="row row-2">
                    <div>
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email"
                               class="form-control <?= !empty($errors['email']) ? 'is-invalid' : '' ?>"
                               placeholder="you@example.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        <?php if (!empty($errors['email'])): ?>
                            <div class="field-error">⚠ <?= htmlspecialchars($errors['email']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone"
                               class="form-control <?= !empty($errors['phone']) ? 'is-invalid' : '' ?>"
                               placeholder="+254 7XX XXX XXX"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        <?php if (!empty($errors['phone'])): ?>
                            <div class="field-error">⚠ <?= htmlspecialchars($errors['phone']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ── Student Fields ─────────────────────────────────────── -->
            <div id="studentFields" class="role-fields <?= (($_POST['role'] ?? '') === 'student') ? 'visible' : '' ?>">
                <div class="section-title">Academic Information</div>
                <div style="margin-bottom:16px;">
                    <label for="institution">Institution / School *</label>
                    <input type="text" id="institution" name="institution"
                           class="form-control <?= !empty($errors['institution']) ? 'is-invalid' : '' ?>"
                           placeholder="University of Nairobi"
                           value="<?= htmlspecialchars($_POST['institution'] ?? '') ?>">
                    <?php if (!empty($errors['institution'])): ?>
                        <div class="field-error">⚠ <?= htmlspecialchars($errors['institution']) ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="course_of_study">Course / Programme</label>
                    <input type="text" id="course_of_study" name="course_of_study"
                           class="form-control"
                           placeholder="e.g. Computer Science"
                           value="<?= htmlspecialchars($_POST['course_of_study'] ?? '') ?>">
                </div>
            </div>

            <!-- ── Counselor Fields ───────────────────────────────────── -->
            <div id="counselorFields" class="role-fields <?= (($_POST['role'] ?? '') === 'counselor') ? 'visible' : '' ?>">
                <div class="section-title">Professional Information</div>
                <div class="row row-2">
                    <div>
                        <label for="specialization">Specialization *</label>
                        <select id="specialization" name="specialization"
                                class="form-control <?= !empty($errors['specialization']) ? 'is-invalid' : '' ?>">
                            <option value="">— Select —</option>
                            <?php
                            $specs = [
                                'Career Coaching', 'Technology & ICT',
                                'Business & Entrepreneurship', 'Health & Medicine', 'Education',
                                'Engineering', 'Creative Arts', 'Law & Policy', 'Agriculture', 'Other',
                            ];
                            foreach ($specs as $s):
                                $sel = (($_POST['specialization'] ?? '') === $s) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($s) ?>" <?= $sel ?>><?= htmlspecialchars($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($errors['specialization'])): ?>
                            <div class="field-error">⚠ <?= htmlspecialchars($errors['specialization']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="years_experience">Years of Experience</label>
                        <input type="number" id="years_experience" name="years_experience"
                               class="form-control" min="0" max="50"
                               value="<?= (int)($_POST['years_experience'] ?? 0) ?>">
                    </div>
                </div>
            </div>

            <!-- ── Admin Fields ───────────────────────────────────────── -->
            <div id="adminFields" class="role-fields <?= (($_POST['role'] ?? '') === 'admin') ? 'visible' : '' ?>">
                <div class="section-title">Admin Verification</div>
                <div>
                    <label for="admin_key">Admin Registration Key *</label>
                    <input type="password" id="admin_key" name="admin_key"
                           class="form-control <?= !empty($errors['admin_key']) ? 'is-invalid' : '' ?>"
                           placeholder="Enter the secret key">
                    <?php if (!empty($errors['admin_key'])): ?>
                        <div class="field-error">⚠ <?= htmlspecialchars($errors['admin_key']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <hr class="divider">

            <!-- ── Password ───────────────────────────────────────────── -->
            <div class="section-title">Set Password</div>
            <div class="row row-2" style="margin-bottom:28px;">
                <div>
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password"
                           class="form-control <?= !empty($errors['password']) ? 'is-invalid' : '' ?>"
                           placeholder="Min. 8 characters" required autocomplete="new-password">
                    <div id="strengthBar" style="height:3px;border-radius:2px;margin-top:6px;background:var(--border);transition:background .3s;"></div>
                    <?php if (!empty($errors['password'])): ?>
                        <div class="field-error">⚠ <?= htmlspecialchars($errors['password']) ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           class="form-control <?= !empty($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                           placeholder="Repeat password" required autocomplete="new-password">
                    <?php if (!empty($errors['confirm_password'])): ?>
                        <div class="field-error">⚠ <?= htmlspecialchars($errors['confirm_password']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <p style="font-size:.78rem;color:var(--ink-faint);margin-top:-18px;margin-bottom:24px;">
                At least 8 characters, one uppercase letter, and one number.
            </p>

            <!-- ── Terms ──────────────────────────────────────────────── -->
            <div class="terms-row">
                <input type="checkbox" id="agree_terms" name="agree_terms" required>
                <label for="agree_terms">
                    I agree to the <a href="terms.php">Terms of Service</a>
                    and <a href="privacy.php">Privacy Policy</a>
                </label>
            </div>

            <button type="submit" class="btn-primary" id="submitBtn">
                Create Account
            </button>
        </form>

    </div><!-- /.card -->
</div><!-- /.page-body -->

<script>
// ── Role field toggling ─────────────────────────────────────────────────
function toggleRoleFields() {
    const role = document.querySelector('input[name="role"]:checked')?.value ?? '';
    document.getElementById('studentFields').classList.toggle('visible',  role === 'student');
    document.getElementById('counselorFields').classList.toggle('visible', role === 'counselor');
    document.getElementById('adminFields').classList.toggle('visible',    role === 'admin');
}
toggleRoleFields(); // run on page load to restore state after validation error

// ── Password strength indicator ─────────────────────────────────────────
document.getElementById('password').addEventListener('input', function () {
    const v = this.value;
    let score = 0;
    if (v.length >= 8)             score++;
    if (/[A-Z]/.test(v))           score++;
    if (/[0-9]/.test(v))           score++;
    if (/[^A-Za-z0-9]/.test(v))    score++;
    const bar    = document.getElementById('strengthBar');
    const colors = ['', '#e74c3c', '#e67e22', '#f1c40f', '#27ae60'];
    bar.style.width      = (score * 25) + '%';
    bar.style.background = colors[score] || 'var(--border)';
});

// ── Prevent double-submit ────────────────────────────────────────────────
document.getElementById('registerForm').addEventListener('submit', function () {
    const btn  = document.getElementById('submitBtn');
    const terms = document.getElementById('agree_terms');
    if (!terms.checked) {
        alert('Please agree to the Terms of Service and Privacy Policy.');
        event.preventDefault();
        return;
    }
    btn.disabled    = true;
    btn.textContent = 'Creating account…';
});
</script>
</body>
</html>