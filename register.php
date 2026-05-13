<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$errors = [];
$success = false;

$allowed_roles = ['student', 'counselor', 'admin'];
$admin_secret_key = 'ADMIN123';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid request. Please refresh the page and try again.';
    } else {
        $full_name        = sanitize_input($_POST['full_name']        ?? '');
        $email            = sanitize_input($_POST['email']            ?? '');
        $phone            = sanitize_input($_POST['phone']            ?? '');
        $role             = sanitize_input($_POST['role']             ?? '');
        $password         = $_POST['password']          ?? '';
        $confirm_password = $_POST['confirm_password']  ?? '';
        $admin_key        = $_POST['admin_key']         ?? '';
        $institution      = sanitize_input($_POST['institution']      ?? '');
        $course_of_study  = sanitize_input($_POST['course_of_study']  ?? '');
        $specialization   = sanitize_input($_POST['specialization']   ?? '');
        $years_experience = (int)($_POST['years_experience'] ?? 0);

        if (empty($full_name) || strlen($full_name) < 2)
            $errors['full_name'] = 'Full name must be at least 2 characters.';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required.';
        } else {
            $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
            if (mysqli_num_rows($check) > 0)
                $errors['email'] = 'Email already registered.';
        }

        if (!empty($phone) && !preg_match('/^\+?[0-9\s\-()]{7,20}$/', $phone))
            $errors['phone'] = 'Invalid phone number.';

        if (!in_array($role, $allowed_roles))
            $errors['role'] = 'Invalid role selected.';

        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Password must include at least one uppercase letter and one number.';
        }

        if ($password !== $confirm_password)
            $errors['confirm_password'] = 'Passwords do not match.';

        if ($role === 'admin' && $admin_key !== $admin_secret_key)
            $errors['admin_key'] = 'Invalid admin registration key.';

        if ($role === 'student' && empty($institution))
            $errors['institution'] = 'Institution name is required.';

        if ($role === 'counselor' && empty($specialization))
            $errors['specialization'] = 'Specialization is required.';

        if (empty($errors)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            mysqli_begin_transaction($conn);
            try {
                $stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, phone, password, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                mysqli_stmt_bind_param($stmt, "sssss", $full_name, $email, $phone, $hashed, $role);
                mysqli_stmt_execute($stmt);
                $user_id = mysqli_insert_id($conn);

                if ($role === 'student') {
                    $stmt2 = mysqli_prepare($conn, "INSERT INTO student_profiles (user_id, institution, course_of_study) VALUES (?, ?, ?)");
                    mysqli_stmt_bind_param($stmt2, "iss", $user_id, $institution, $course_of_study);
                    mysqli_stmt_execute($stmt2);
                } elseif ($role === 'counselor') {
                    $stmt2 = mysqli_prepare($conn, "INSERT INTO counselor_profiles (user_id, specialization, years_experience) VALUES (?, ?, ?)");
                    mysqli_stmt_bind_param($stmt2, "isi", $user_id, $specialization, $years_experience);
                    mysqli_stmt_execute($stmt2);
                }

                log_activity($user_id, 'register', "New $role registered");
                mysqli_commit($conn);
                $success = true;
                header("Location: login.php?registered=1");
                exit();
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $errors['general'] = 'Registration failed. Please try again.';
                error_log($e->getMessage());
            }
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
        .brand-mark svg { width: 20px; height: 20px; fill: #fff; }
        .brand-text { display: flex; flex-direction: column; line-height: 1; }
        .brand-name {
            font-family: 'DM Serif Display', serif;
            font-size: 1.1rem;
            color: #fff;
        }
        .brand-sub {
            font-size: .7rem;
            color: rgba(255,255,255,.45);
            letter-spacing: .07em;
            text-transform: uppercase;
            margin-top: 2px;
        }
        .topbar-link {
            margin-left: auto;
            font-size: .85rem;
            color: rgba(255,255,255,.55);
        }
        .topbar-link a {
            color: var(--accent);
            font-weight: 600;
            text-decoration: none;
        }
        .topbar-link a:hover { text-decoration: underline; }

        /* ── Page body ── */
        .page-body {
            max-width: 680px;
            margin: 0 auto;
            padding: 48px 24px 72px;
            animation: fadeUp .5s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .page-heading {
            font-family: 'DM Serif Display', serif;
            font-size: 2.2rem;
            letter-spacing: -.02em;
            color: var(--ink);
            margin-bottom: 4px;
        }
        .page-sub {
            font-size: .9rem;
            color: var(--ink-faint);
            margin-bottom: 36px;
        }

        /* ── Card ── */
        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 2px 6px rgba(0,0,0,.05), 0 12px 40px rgba(0,0,0,.07);
            padding: 40px 44px;
        }

        /* ── Section titles ── */
        .section-title {
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1.5px solid var(--accent-lt);
        }

        /* ── Role selector ── */
        .role-grid {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 32px;
        }
        .role-option { display: none; }
        .role-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 16px 28px;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            cursor: pointer;
            transition: border-color .2s, background .2s, box-shadow .2s;
            min-width: 130px;
            text-align: center;
        }
        .role-label:hover {
            border-color: var(--accent);
            background: var(--accent-lt);
        }
        .role-option:checked + .role-label {
            border-color: var(--accent);
            background: var(--accent-lt);
            box-shadow: 0 0 0 3px rgba(200,98,42,.12);
        }
        .role-icon { font-size: 1.6rem; }
        .role-name {
            font-size: .82rem;
            font-weight: 600;
            color: var(--ink-soft);
            letter-spacing: .02em;
        }

        /* ── Form ── */
        .row { display: grid; gap: 18px; }
        .row-2 { grid-template-columns: 1fr 1fr; }
        .form-group { margin-bottom: 0; }
        .form-section { margin-bottom: 28px; }

        label {
            display: block;
            font-size: .75rem;
            font-weight: 600;
            color: var(--ink-soft);
            letter-spacing: .05em;
            text-transform: uppercase;
            margin-bottom: 7px;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            font-family: 'DM Sans', sans-serif;
            font-size: .9rem;
            color: var(--ink);
            background: var(--canvas);
            outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
            appearance: none;
        }
        .form-control::placeholder { color: var(--ink-faint); }
        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(200,98,42,.12);
            background: #fff;
        }

        select.form-control {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%239396a3' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 36px;
        }

        .field-error {
            font-size: .78rem;
            color: var(--danger);
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Alert */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 13px 16px;
            border-radius: var(--radius);
            font-size: .85rem;
            margin-bottom: 28px;
            border: 1px solid #f5c6c4;
            background: #fdf0ef;
            color: var(--danger);
        }

        /* Conditional fields */
        .role-fields {
            background: var(--canvas);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 28px;
            border: 1.5px dashed var(--border);
            display: none;
        }
        .role-fields.visible { display: block; animation: fadeIn .3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

        /* Terms */
        .terms-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 28px;
            margin-top: 4px;
        }
        .terms-row input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: var(--accent);
            margin-top: 2px;
            flex-shrink: 0;
            cursor: pointer;
        }
        .terms-row label {
            font-size: .85rem;
            color: var(--ink-soft);
            text-transform: none;
            letter-spacing: 0;
            font-weight: 400;
            cursor: pointer;
        }
        .terms-row label a {
            color: var(--accent);
            font-weight: 600;
            text-decoration: none;
        }
        .terms-row label a:hover { text-decoration: underline; }

        /* Submit */
        .btn-primary {
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

        /* Divider */
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

    <div class="card">

        <?php if (!empty($errors['general'])): ?>
            <div class="alert" role="alert">
                <span>⚠</span> <?= htmlspecialchars($errors['general']) ?>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <!-- Role -->
            <div class="section-title">I am a</div>
            <div class="role-grid">

                <?php foreach (['student' => ['🎓','Student'], 'counselor' => ['💼','Counsellor']] as $val => [$icon, $name]): ?>
                    <input class="role-option" type="radio" name="role"
                           id="role-<?= $val ?>" value="<?= $val ?>"
                           onchange="toggleRoleFields()"
                           <?= (($_POST['role'] ?? '') == $val) ? 'checked' : '' ?>>
                    <label class="role-label" for="role-<?= $val ?>">
                        <span class="role-icon"><?= $icon ?></span>
                        <span class="role-name"><?= $name ?></span>
                    </label>
                <?php endforeach; ?>

                <?php if (isset($_GET['admin_reg'])): ?>
                    <input class="role-option" type="radio" name="role"
                           id="role-admin" value="admin"
                           onchange="toggleRoleFields()"
                           <?= (($_POST['role'] ?? '') == 'admin') ? 'checked' : '' ?>>
                    <label class="role-label" for="role-admin">
                        <span class="role-icon">🔧</span>
                        <span class="role-name">Administrator</span>
                    </label>
                <?php endif; ?>

            </div>
            <?php if (isset($errors['role'])): ?>
                <div class="field-error">⚠ <?= htmlspecialchars($errors['role']) ?></div>
            <?php endif; ?>

            <hr class="divider">

            <!-- Personal info -->
            <div class="section-title">Personal Information</div>
            <div class="form-section">
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" class="form-control"
                           placeholder="Jane Mwangi"
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                    <?php if (isset($errors['full_name'])): ?>
                        <div class="field-error">⚠ <?= htmlspecialchars($errors['full_name']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="row row-2">
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" class="form-control"
                               placeholder="you@example.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        <?php if (isset($errors['email'])): ?>
                            <div class="field-error">⚠ <?= htmlspecialchars($errors['email']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone" class="form-control"
                               placeholder="+254 7XX XXX XXX"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        <?php if (isset($errors['phone'])): ?>
                            <div class="field-error">⚠ <?= htmlspecialchars($errors['phone']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Student fields -->
            <div id="studentFields" class="role-fields <?= (($_POST['role'] ?? '') == 'student') ? 'visible' : '' ?>">
                <div class="section-title">Academic Information</div>
                <div class="form-group" style="margin-bottom:16px;">
                    <label for="institution">Institution / School *</label>
                    <input type="text" id="institution" name="institution" class="form-control"
                           placeholder="University of Nairobi"
                           value="<?= htmlspecialchars($_POST['institution'] ?? '') ?>">
                    <?php if (isset($errors['institution'])): ?>
                        <div class="field-error">⚠ <?= htmlspecialchars($errors['institution']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="course_of_study">Course / Programme</label>
                    <input type="text" id="course_of_study" name="course_of_study" class="form-control"
                           placeholder="e.g. Computer Science"
                           value="<?= htmlspecialchars($_POST['course_of_study'] ?? '') ?>">
                </div>
            </div>

            <!-- Counselor fields -->
            <div id="counselorFields" class="role-fields <?= (($_POST['role'] ?? '') == 'counselor') ? 'visible' : '' ?>">
                <div class="section-title">Professional Information</div>
                <div class="row row-2">
                    <div class="form-group">
                        <label for="specialization">Specialization *</label>
                        <select id="specialization" name="specialization" class="form-control">
                            <option value="">— Select —</option>
                            <?php
                            $specs = ['Career Coaching','Technology & ICT','Business & Entrepreneurship','Health & Medicine','Education'];
                            foreach ($specs as $s):
                                $sel = (($_POST['specialization'] ?? '') == $s) ? 'selected' : '';
                            ?>
                                <option value="<?= $s ?>" <?= $sel ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['specialization'])): ?>
                            <div class="field-error">⚠ <?= htmlspecialchars($errors['specialization']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="years_experience">Years of Experience</label>
                        <input type="number" id="years_experience" name="years_experience"
                               class="form-control" min="0" max="50"
                               value="<?= htmlspecialchars($_POST['years_experience'] ?? 0) ?>">
                    </div>
                </div>
            </div>

            <!-- Admin fields -->
            <div id="adminFields" class="role-fields <?= (($_POST['role'] ?? '') == 'admin') ? 'visible' : '' ?>">
                <div class="section-title">Admin Verification</div>
                <div class="form-group">
                    <label for="admin_key">Admin Registration Key *</label>
                    <input type="password" id="admin_key" name="admin_key" class="form-control"
                           placeholder="Enter secret key">
                    <?php if (isset($errors['admin_key'])): ?>
                        <div class="field-error">⚠ <?= htmlspecialchars($errors['admin_key']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <hr class="divider">

            <!-- Password -->
            <div class="section-title">Set Password</div>
            <div class="row row-2" style="margin-bottom:28px;">
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="At least 8 characters" required>
                    <?php if (isset($errors['password'])): ?>
                        <div class="field-error">⚠ <?= htmlspecialchars($errors['password']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                           placeholder="Repeat password" required>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <div class="field-error">⚠ <?= htmlspecialchars($errors['confirm_password']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Terms -->
            <div class="terms-row">
                <input type="checkbox" id="agree_terms" name="agree_terms" required>
                <label for="agree_terms">
                    I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                </label>
            </div>

            <button type="submit" class="btn-primary">Create Account</button>
        </form>

    </div><!-- /.card -->
</div><!-- /.page-body -->

<script>
function toggleRoleFields() {
    const role = document.querySelector('input[name="role"]:checked')?.value;
    document.getElementById('studentFields').classList.toggle('visible', role === 'student');
    document.getElementById('counselorFields').classList.toggle('visible', role === 'counselor');
    document.getElementById('adminFields').classList.toggle('visible', role === 'admin');
}
toggleRoleFields();
</script>
</body>
</html>