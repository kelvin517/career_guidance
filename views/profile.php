<?php
require_once '../includes/config.php';
if (!isLoggedIn()) redirect('../login.php');

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];
$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
$message = '';
$error = '';

// Get user data
$userQuery = mysqli_query($conn, "SELECT * FROM users WHERE id = $userId");
$user = mysqli_fetch_assoc($userQuery);

// Get role-specific profile
$profile = null;
if ($role === 'student') {
    $profileQuery = mysqli_query($conn, "SELECT * FROM student_profiles WHERE user_id = $userId");
    $profile = mysqli_fetch_assoc($profileQuery);
} elseif ($role === 'teacher') {
    $profileQuery = mysqli_query($conn, "SELECT * FROM teacher_profiles WHERE user_id = $userId");
    $profile = mysqli_fetch_assoc($profileQuery);
} elseif ($role === 'counselor') {
    $profileQuery = mysqli_query($conn, "SELECT * FROM counselor_profiles WHERE user_id = $userId");
    $profile = mysqli_fetch_assoc($profileQuery);
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update basic info
    if (isset($_POST['update_profile'])) {
        $full_name = sanitize_input($_POST['full_name']);
        $phone = sanitize_input($_POST['phone']);
        
        $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ssi', $full_name, $phone, $userId);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['full_name'] = $full_name;
            $message = 'Profile updated successfully!';
        } else {
            $error = 'Failed to update profile.';
        }
        mysqli_stmt_close($stmt);
    }
    
    // Update role-specific profile
    if (isset($_POST['update_role_profile'])) {
        if ($role === 'student') {
            $institution = sanitize_input($_POST['institution']);
            $course_of_study = sanitize_input($_POST['course_of_study']);
            $skills = sanitize_input($_POST['skills']);
            $interests = sanitize_input($_POST['interests']);
            
            if ($profile) {
                $stmt = mysqli_prepare($conn, "UPDATE student_profiles SET institution = ?, course_of_study = ?, skills = ?, interests = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($stmt, 'ssssi', $institution, $course_of_study, $skills, $interests, $userId);
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO student_profiles (user_id, institution, course_of_study, skills, interests) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'issss', $userId, $institution, $course_of_study, $skills, $interests);
            }
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Academic profile updated successfully!';
            } else {
                $error = 'Failed to update academic profile.';
            }
            mysqli_stmt_close($stmt);
        }
        
        elseif ($role === 'teacher') {
            $subject_specialization = sanitize_input($_POST['subject_specialization']);
            $qualification = sanitize_input($_POST['qualification']);
            
            if ($profile) {
                $stmt = mysqli_prepare($conn, "UPDATE teacher_profiles SET subject_specialization = ?, qualification = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($stmt, 'ssi', $subject_specialization, $qualification, $userId);
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO teacher_profiles (user_id, subject_specialization, qualification) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'iss', $userId, $subject_specialization, $qualification);
            }
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Teacher profile updated successfully!';
            } else {
                $error = 'Failed to update teacher profile.';
            }
            mysqli_stmt_close($stmt);
        }
        
        elseif ($role === 'counselor') {
            $specialization = sanitize_input($_POST['specialization']);
            $years_experience = (int)$_POST['years_experience'];
            
            if ($profile) {
                $stmt = mysqli_prepare($conn, "UPDATE counselor_profiles SET specialization = ?, years_experience = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($stmt, 'sii', $specialization, $years_experience, $userId);
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO counselor_profiles (user_id, specialization, years_experience) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'isi', $userId, $specialization, $years_experience);
            }
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Counselor profile updated successfully!';
            } else {
                $error = 'Failed to update counselor profile.';
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Change password
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (password_verify($current_password, $user['password'])) {
            if (strlen($new_password) < 8) {
                $error = 'New password must be at least 8 characters.';
            } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
                $error = 'Password must contain at least one uppercase letter and one number.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'New passwords do not match.';
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'si', $hashed, $userId);
                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Password changed successfully!';
                } else {
                    $error = 'Failed to change password.';
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $error = 'Current password is incorrect.';
        }
    }
}

// Calculate profile completeness
$completeness = 0;
$completenessItems = [];

// Basic info
if (!empty($user['full_name'])) $completeness += 10;
if (!empty($user['phone'])) $completeness += 5;

// Role-specific completeness
if ($role === 'student') {
    if (!empty($profile['institution'])) { $completeness += 25; $completenessItems[] = 'Institution'; }
    if (!empty($profile['course_of_study'])) { $completeness += 20; $completenessItems[] = 'Course'; }
    if (!empty($profile['skills'])) { $completeness += 20; $completenessItems[] = 'Skills'; }
    if (!empty($profile['interests'])) { $completeness += 20; $completenessItems[] = 'Interests'; }
} elseif ($role === 'teacher') {
    if (!empty($profile['subject_specialization'])) { $completeness += 40; $completenessItems[] = 'Subject Specialization'; }
    if (!empty($profile['qualification'])) { $completeness += 35; $completenessItems[] = 'Qualification'; }
} elseif ($role === 'counselor') {
    if (!empty($profile['specialization'])) { $completeness += 40; $completenessItems[] = 'Specialization'; }
    if (!empty($profile['years_experience'])) { $completeness += 35; $completenessItems[] = 'Experience'; }
}

$greeting = date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Smart Learning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --ink:#0f1117;--ink-soft:#4a4d5a;--ink-faint:#9396a3;
            --canvas:#f5f7f9;--white:#fff;
            --accent:#2563a8;--accent-lt:#eef3fb;--accent-dim:rgba(37,99,168,.12);
            --border:#e4e8ed;--radius:14px;
            --sidebar:264px;
            --green:#1f7a5c;--orange:#c8622a;--red:#c0392b;
        }
        html,body{min-height:100vh;font-family:'DM Sans',sans-serif;background:var(--canvas);color:var(--ink)}

        .sidebar{
            position:fixed;top:0;left:0;height:100vh;width:var(--sidebar);
            background:#1a2235;display:flex;flex-direction:column;z-index:100;
            overflow-y:auto;
        }
        .sb-brand{padding:28px 24px 24px;background:linear-gradient(135deg,#1e3a5f 0%,#1a2235 100%);border-bottom:1px solid rgba(255,255,255,.06);}
        .sb-mark{width:40px;height:40px;background:var(--accent);border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:14px;}
        .sb-mark svg{width:22px;height:22px;fill:#fff}
        .sb-name{font-family:'DM Serif Display',serif;font-size:1.15rem;color:#fff;}
        .sb-role{font-size:.68rem;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-top:3px}
        .sb-section{padding:22px 20px 6px;font-size:.62rem;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.22)}
        .sb-nav{list-style:none;padding:2px 10px}
        .sb-nav li a{
            display:flex;align-items:center;gap:11px;padding:10px 14px;
            border-radius:9px;font-size:.875rem;color:rgba(255,255,255,.5);
            text-decoration:none;transition:background .2s,color .2s;
        }
        .sb-nav li a:hover{background:rgba(255,255,255,.06);color:#fff}
        .sb-nav li a.active{background:var(--accent);color:#fff}
        .sb-nav li a .nav-icon{font-size:.95rem;width:18px;text-align:center}

        .sb-footer{margin-top:auto;padding:16px 10px;border-top:1px solid rgba(255,255,255,.06)}
        .sb-user{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:9px;}
        .sb-avatar{width:36px;height:36px;border-radius:10px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;color:#fff;flex-shrink:0}
        .sb-user-name{font-size:.85rem;font-weight:600;color:#fff}
        .sb-user-sub{font-size:.7rem;color:rgba(255,255,255,.3)}
        .sb-logout{color:rgba(255,255,255,.3);text-decoration:none;font-size:.8rem;padding:8px 14px;display:block;border-radius:8px;margin-top:8px}
        .sb-logout:hover{color:#fff}

        .main{margin-left:var(--sidebar);min-height:100vh}
        .topbar{background:var(--white);border-bottom:1px solid var(--border);padding:16px 36px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
        .topbar-breadcrumb{font-size:.8rem;color:var(--ink-faint)}
        .topbar-breadcrumb span{color:var(--ink);font-weight:600}
        .body{padding:32px 36px}

        .page-header{margin-bottom:28px}
        .page-title{font-family:'DM Serif Display',serif;font-size:1.8rem;letter-spacing:-.02em}
        .page-sub{font-size:.85rem;color:var(--ink-faint);margin-top:4px}

        .alert{padding:14px 18px;border-radius:12px;margin-bottom:24px}
        .alert-success{background:#e8f5f0;border:1px solid #c5e0d4;color:var(--green)}
        .alert-danger{background:#fef2f2;border:1px solid #fecaca;color:var(--red)}
        .alert-info{background:var(--accent-lt);border:1px solid #c5d8f0;color:var(--accent)}

        .two-col{display:grid;grid-template-columns:1fr 1fr;gap:28px}
        .profile-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:28px}
        .profile-card h2{font-family:'DM Serif Display',serif;font-size:1.3rem;margin-bottom:20px}
        
        .avatar-section{text-align:center;margin-bottom:24px}
        .profile-avatar{width:100px;height:100px;background:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2.5rem;font-weight:700;color:#fff;margin:0 auto 12px}
        .role-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:.75rem;font-weight:600;background:var(--accent-lt);color:var(--accent)}
        
        .form-group{margin-bottom:20px}
        label{display:block;font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-faint);margin-bottom:8px}
        .form-control{width:100%;padding:12px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.9rem;background:var(--canvas);transition:border-color .2s}
        .form-control:focus{outline:none;border-color:var(--accent)}
        textarea.form-control{resize:vertical;min-height:80px}
        select.form-control{cursor:pointer}
        
        .btn{background:var(--accent);color:#fff;border:none;padding:12px 24px;border-radius:10px;font-weight:600;cursor:pointer;width:100%}
        .btn:hover{background:#1a4f8f}
        .btn-secondary{background:var(--canvas);color:var(--ink);border:1.5px solid var(--border)}
        .btn-secondary:hover{background:var(--border)}

        .completion-bar{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:20px;margin-bottom:28px}
        .completion-title{font-size:.8rem;font-weight:600;margin-bottom:8px}
        .bar-outer{height:8px;background:var(--border);border-radius:4px;overflow:hidden}
        .bar-inner{height:100%;background:var(--green);border-radius:4px;transition:width .3s}
        .missing-items{font-size:.72rem;color:var(--ink-faint);margin-top:8px}

        .skills-list{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
        .skill-tag{background:var(--accent-dim);padding:4px 12px;border-radius:20px;font-size:.75rem}
        
        hr{margin:20px 0;border:none;border-top:1.5px solid var(--border)}
        
        @media(max-width:900px){
            .sidebar{display:none}.main{margin-left:0}
            .body{padding:20px}
            .two-col{grid-template-columns:1fr}
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand"><div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><div class="sb-name">Smart Learning</div><div class="sb-role">Profile</div></div>
    <ul class="sb-nav">
        <li><a href="dashboard/<?php echo $role; ?>_dashboard.php"><span class="nav-icon">⊞</span> Dashboard</a></li>
        <li><a href="profile.php" class="active"><span class="nav-icon">👤</span> My Profile</a></li>
        <?php if ($role === 'student'): ?>
            <li><a href="assessment/interest_questionnaire.php"><span class="nav-icon">📋</span> Take Assessment</a></li>
            <li><a href="career/recommendations.php"><span class="nav-icon">🧭</span> Career Matches</a></li>
        <?php endif; ?>
    </ul>
    <div class="sb-footer">
        <div class="sb-user"><div class="sb-avatar"><?php echo $avatarLetter; ?></div><div><div class="sb-user-name"><?php echo htmlspecialchars($fullName); ?></div><div class="sb-user-sub"><?php echo ucfirst($role); ?></div></div></div>
        <a href="../logout.php" class="sb-logout">→ Sign out</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div class="topbar-breadcrumb"><?php echo ucfirst($role); ?> / <span>My Profile</span></div>
    </div>
    <div class="body">
        <div class="page-header">
            <h1 class="page-title">My Profile</h1>
            <p class="page-sub">Manage your personal information and account settings</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">✅ <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger">⚠ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Profile Completeness -->
        <div class="completion-bar">
            <div class="completion-title">Profile Completeness: <?php echo $completeness; ?>%</div>
            <div class="bar-outer"><div class="bar-inner" style="width: <?php echo $completeness; ?>%"></div></div>
            <?php if ($completeness < 100): ?>
                <div class="missing-items">💡 Complete your profile to get better career recommendations</div>
            <?php endif; ?>
        </div>

        <div class="two-col">
            <!-- Left Column: Basic Info -->
            <div class="profile-card">
                <div class="avatar-section">
                    <div class="profile-avatar"><?php echo $avatarLetter; ?></div>
                    <span class="role-badge"><?php echo ucfirst($role); ?></span>
                </div>
                
                <h2>Basic Information</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="background:#e9ecef">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+254 XXX XXX XXX">
                    </div>
                    <button type="submit" name="update_profile" class="btn">Save Changes</button>
                </form>
                
                <hr>
                
                <h2>Change Password</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                        <small style="font-size:.7rem; color:var(--ink-faint);">At least 8 characters, one uppercase letter and one number</small>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-secondary">Change Password</button>
                </form>
            </div>

            <!-- Right Column: Role-Specific Profile -->
            <div class="profile-card">
                <h2><?php 
                    if ($role === 'student') echo '🎓 Academic Information';
                    elseif ($role === 'teacher') echo '👨‍🏫 Professional Information';
                    else echo '💼 Professional Information';
                ?></h2>
                
                <form method="POST">
                    <?php if ($role === 'student'): ?>
                        <div class="form-group">
                            <label>Institution / School</label>
                            <input type="text" name="institution" class="form-control" value="<?php echo htmlspecialchars($profile['institution'] ?? ''); ?>" placeholder="University of Nairobi">
                        </div>
                        <div class="form-group">
                            <label>Course / Programme</label>
                            <input type="text" name="course_of_study" class="form-control" value="<?php echo htmlspecialchars($profile['course_of_study'] ?? ''); ?>" placeholder="BSc Computer Science">
                        </div>
                        <div class="form-group">
                            <label>Skills (comma-separated)</label>
                            <textarea name="skills" class="form-control" placeholder="e.g., Python, JavaScript, Communication"><?php echo htmlspecialchars($profile['skills'] ?? ''); ?></textarea>
                            <?php if (!empty($profile['skills'])): ?>
                                <div class="skills-list">
                                    <?php foreach (explode(',', $profile['skills']) as $skill): ?>
                                        <span class="skill-tag"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Interests (comma-separated)</label>
                            <textarea name="interests" class="form-control" placeholder="e.g., Programming, Design, Business"><?php echo htmlspecialchars($profile['interests'] ?? ''); ?></textarea>
                        </div>
                    
                    <?php elseif ($role === 'teacher'): ?>
                        <div class="form-group">
                            <label>Subject Specialization</label>
                            <select name="subject_specialization" class="form-control">
                                <option value="">-- Select Subject --</option>
                                <?php $subjects = ['Mathematics', 'Physics', 'Chemistry', 'Biology', 'Computer Science', 'English', 'Kiswahili', 'History', 'Geography', 'Business Studies', 'Agriculture']; ?>
                                <?php foreach ($subjects as $sub): ?>
                                    <option value="<?php echo $sub; ?>" <?php echo (($profile['subject_specialization'] ?? '') == $sub) ? 'selected' : ''; ?>><?php echo $sub; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Qualification</label>
                            <select name="qualification" class="form-control">
                                <option value="">-- Select Qualification --</option>
                                <?php $quals = ['Diploma', 'Bachelor\'s Degree', 'Master\'s Degree', 'PhD', 'PGDE']; ?>
                                <?php foreach ($quals as $q): ?>
                                    <option value="<?php echo $q; ?>" <?php echo (($profile['qualification'] ?? '') == $q) ? 'selected' : ''; ?>><?php echo $q; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    
                    <?php elseif ($role === 'counselor'): ?>
                        <div class="form-group">
                            <label>Specialization</label>
                            <select name="specialization" class="form-control">
                                <option value="">-- Select Specialization --</option>
                                <?php $specs = ['Career Coaching', 'Technology & ICT', 'Business & Entrepreneurship', 'Health & Medicine', 'Education', 'Engineering', 'Creative Arts', 'Law & Policy', 'Agriculture']; ?>
                                <?php foreach ($specs as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo (($profile['specialization'] ?? '') == $s) ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Years of Experience</label>
                            <input type="number" name="years_experience" class="form-control" value="<?php echo htmlspecialchars($profile['years_experience'] ?? 0); ?>" min="0" max="50">
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" name="update_role_profile" class="btn">Save <?php echo ucfirst($role); ?> Profile</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>