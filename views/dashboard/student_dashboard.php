<?php
require_once '../../includes/config.php';

if (!isLoggedIn()) redirect('../../login.php');
if ($_SESSION['role'] !== 'student') redirect_by_role($_SESSION['role']);

$user_id = (int)$_SESSION['user_id'];
$full_name = htmlspecialchars($_SESSION['full_name']);
$first_name = explode(' ', $full_name)[0];
$avatar_letter = strtoupper(substr($full_name, 0, 1));
$greeting = date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening');

// Fetch profile
$profile = null;
$stmt = mysqli_prepare($conn, "SELECT institution, course_of_study, skills, interests, holland_code FROM student_profiles WHERE user_id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $profile = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

$completeness = 0;
if ($profile) {
    if (!empty($profile['institution'])) $completeness += 25;
    if (!empty($profile['course_of_study'])) $completeness += 25;
    if (!empty($profile['skills'])) $completeness += 25;
    if (!empty($profile['interests'])) $completeness += 25;
}

$recommendations = getCareerRecommendations($user_id);
$total_careers = 0;
$career_res = mysqli_query($conn, "SELECT COUNT(*) as c FROM career_paths WHERE is_active = 1");
if ($career_res) $total_careers = (int)mysqli_fetch_assoc($career_res)['c'];

// Quiz attempts count
$assessments_taken = 0;
$assess_res = mysqli_query($conn, "SELECT COUNT(*) as c FROM quiz_attempts WHERE student_id = $user_id");
if ($assess_res) $assessments_taken = (int)mysqli_fetch_assoc($assess_res)['c'];

// Appointments count
$appointment_count = 0;
$next_appointment = null;
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'appointments'");
if (mysqli_num_rows($table_check) > 0) {
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM appointments LIKE 'appointment_date'");
    if (mysqli_num_rows($col_check) > 0) {
        $app_res = mysqli_query($conn, "SELECT COUNT(*) as c FROM appointments WHERE student_id = $user_id AND appointment_date >= CURDATE() AND status != 'cancelled'");
        if ($app_res) $appointment_count = (int)mysqli_fetch_assoc($app_res)['c'];
        
        $next_res = mysqli_query($conn, "SELECT a.appointment_date, a.appointment_time, u.full_name as counselor_name FROM appointments a JOIN users u ON u.id = a.counselor_id WHERE a.student_id = $user_id AND a.appointment_date >= CURDATE() AND a.status != 'cancelled' ORDER BY a.appointment_date, a.appointment_time LIMIT 1");
        if ($next_res) $next_appointment = mysqli_fetch_assoc($next_res);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard — <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --ink:#0f1117;--ink-soft:#4a4d5a;--ink-faint:#9396a3;
            --canvas:#f7f6f2;--white:#fff;
            --accent:#c8622a;--accent-lt:#f0ebe3;--accent-dark:#b5551f;
            --border:#e2dfd8;
            --success:#1f7a5c;--success-lt:#edf7f3;
            --warning:#8a6d1a;--warning-lt:#fdf8ed;
            --radius:12px;--sidebar:240px;
            --shadow:0 1px 4px rgba(0,0,0,.05);--shadow-md:0 6px 20px rgba(0,0,0,.09);
        }
        body{font-family:'DM Sans',sans-serif;background:var(--canvas);color:var(--ink);display:flex;min-height:100vh;}
        .sidebar{width:var(--sidebar);min-height:100vh;background:var(--ink);display:flex;flex-direction:column;flex-shrink:0;position:sticky;top:0;height:100vh;overflow-y:auto;}
        .sidebar-brand{padding:28px 24px 20px;border-bottom:1px solid rgba(255,255,255,.08);}
        .brand-icon{width:36px;height:36px;background:var(--accent);border-radius:8px;display:flex;align-items:center;justify-content:center;margin-bottom:10px;}
        .brand-icon svg{width:20px;height:20px;fill:none;stroke:#fff;stroke-width:2;}
        .brand-title{font-family:'DM Serif Display',serif;font-size:1rem;color:#fff;}
        .brand-sub{font-size:.65rem;color:rgba(255,255,255,.35);text-transform:uppercase;margin-top:3px;}
        .nav-section{padding:20px 0 8px;flex:1;}
        .nav-label{font-size:.6rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.25);padding:0 20px 8px;}
        .nav-item{display:flex;align-items:center;gap:10px;padding:10px 20px;font-size:.875rem;color:rgba(255,255,255,.55);text-decoration:none;border-left:3px solid transparent;}
        .nav-item:hover{background:rgba(255,255,255,.06);color:#fff;}
        .nav-item.active{background:rgba(200,98,42,.18);color:var(--accent);border-left-color:var(--accent);font-weight:600;}
        .nav-icon{font-size:1rem;}
        .sidebar-footer{margin-top:auto;padding:16px 20px;border-top:1px solid rgba(255,255,255,.08);}
        .user-chip{display:flex;align-items:center;gap:10px;}
        .avatar{width:34px;height:34px;background:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;}
        .user-name{font-size:.82rem;color:#fff;font-weight:600;}
        .user-role{font-size:.68rem;color:rgba(255,255,255,.35);text-transform:uppercase;}
        .logout-btn{display:block;width:100%;margin-top:10px;padding:8px;text-align:center;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:8px;color:rgba(255,255,255,.5);font-size:.78rem;text-decoration:none;}
        .logout-btn:hover{background:rgba(200,98,42,.2);color:var(--accent);}
        .main{flex:1;padding:36px 40px;}
        .page-header{margin-bottom:32px;}
        .page-header h1{font-family:'DM Serif Display',serif;font-size:1.8rem;}
        .page-header p{font-size:.875rem;color:var(--ink-faint);margin-top:4px;}
        .alert{padding:12px 16px;border-radius:var(--radius);font-size:.83rem;margin-bottom:20px;border:1px solid;}
        .alert-warning{background:var(--warning-lt);color:var(--warning);}
        .alert-success{background:var(--success-lt);color:var(--success);}
        .alert-info{background:var(--accent-lt);color:var(--accent-dark);}
        .completion-banner{background:var(--white);border-radius:var(--radius);padding:16px 20px;display:flex;align-items:center;gap:16px;margin-bottom:28px;border-left:4px solid var(--accent);}
        .completion-info{flex:1;}
        .completion-bar-outer{height:6px;background:var(--border);border-radius:3px;margin-top:8px;}
        .completion-bar-inner{height:100%;background:var(--accent);border-radius:3px;}
        .completion-pct{font-family:'DM Serif Display',serif;font-size:1.3rem;color:var(--accent);}
        .stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:32px;}
        .stat-card{background:var(--white);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);}
        .stat-icon{font-size:1.6rem;margin-bottom:10px;}
        .stat-value{font-family:'DM Serif Display',serif;font-size:1.8rem;}
        .stat-label{font-size:.75rem;color:var(--ink-faint);margin-top:4px;text-transform:uppercase;}
        .section{margin-bottom:32px;}
        .section-title{font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--accent);margin-bottom:16px;padding-bottom:8px;border-bottom:1.5px solid var(--accent-lt);}
        .profile-card{background:var(--white);border-radius:var(--radius);padding:24px;display:flex;gap:20px;}
        .profile-avatar{width:60px;height:60px;background:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;color:#fff;}
        .profile-details h2{font-size:1.1rem;font-weight:600;margin-bottom:4px;}
        .profile-meta{font-size:.82rem;color:var(--ink-faint);}
        .badge{display:inline-block;background:var(--accent-lt);color:var(--accent);padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:600;margin-top:8px;}
        .tag{display:inline-block;background:var(--canvas);border:1px solid var(--border);color:var(--ink-soft);padding:3px 9px;border-radius:20px;font-size:.72rem;margin:2px;}
        .verified-badge{display:inline-flex;align-items:center;gap:6px;background:var(--success-lt);color:var(--success);border:1px solid #b8e0d2;padding:4px 12px;border-radius:20px;font-size:.75rem;font-weight:600;}
        .pending-badge{background:var(--warning-lt);color:var(--warning);}
        .btn-sm{display:inline-block;margin-top:12px;padding:7px 16px;background:var(--accent);color:#fff;border-radius:8px;font-size:.8rem;font-weight:600;text-decoration:none;}
        .btn-sm:hover{background:var(--accent-dark);}
        .btn-outline{background:transparent;border:1.5px solid var(--accent);color:var(--accent);}
        .info-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;}
        .info-card{background:var(--white);border-radius:var(--radius);padding:20px;border-left:3px solid var(--accent);}
        .info-card h4{font-size:.9rem;font-weight:600;margin-bottom:8px;}
        .career-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;}
        .career-card{background:var(--white);border-radius:var(--radius);padding:20px;border-left:3px solid var(--accent);}
        .career-name{font-size:.95rem;font-weight:600;}
        .career-category{font-size:.72rem;color:var(--ink-faint);text-transform:uppercase;}
        .match-bar-wrap{display:flex;align-items:center;gap:10px;margin-top:4px;}
        .match-bar{flex:1;height:4px;background:var(--border);border-radius:2px;}
        .match-fill{height:100%;background:var(--accent);border-radius:2px;}
        .match-pct{font-size:.75rem;font-weight:700;color:var(--accent);}
        .empty-state{text-align:center;padding:40px 20px;color:var(--ink-faint);}
        .empty-state .icon{font-size:2.5rem;margin-bottom:12px;}
        @media(max-width:768px){body{flex-direction:column;}.sidebar{width:100%;min-height:auto;height:auto;}.main{padding:20px 16px;}.profile-card{flex-direction:column;}}
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div>
        <div class="brand-title">Smart Learning</div><div class="brand-sub">Career Guidance</div>
    </div>
    <nav class="nav-section">
        <div class="nav-label">Menu</div>
        <a href="student_dashboard.php" class="nav-item active"><span class="nav-icon">🏠</span> Dashboard</a>
        <a href="../profile.php" class="nav-item"><span class="nav-icon">👤</span> My Profile</a>
        <a href="../career/recommendations.php" class="nav-item"><span class="nav-icon">🧭</span> Browse Careers</a>
        <a href="../assessment/interest_questionnaire.php" class="nav-item"><span class="nav-icon">📋</span> Assessments</a>
        <a href="../appointments.php" class="nav-item"><span class="nav-icon">📅</span> Book Counsellor</a>
        <a href="../learning/materials_list.php" class="nav-item"><span class="nav-icon">📚</span> Resources</a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-chip"><div class="avatar"><?php echo $avatar_letter; ?></div><div><div class="user-name"><?php echo $full_name; ?></div><div class="user-role">Student</div></div></div>
        <a href="../../logout.php" class="logout-btn">⬡ Sign out</a>
    </div>
</aside>

<main class="main">
    <div class="page-header"><h1>Good <?php echo $greeting; ?>, <?php echo $first_name; ?> 👋</h1><p>Here's an overview of your career guidance journey.</p></div>
    
    <?php if ($next_appointment): ?>
        <div class="alert alert-info">📅 Upcoming session with <strong><?php echo htmlspecialchars($next_appointment['counselor_name']); ?></strong> on <strong><?php echo date('D j M Y', strtotime($next_appointment['appointment_date'])); ?></strong> at <strong><?php echo date('g:i A', strtotime($next_appointment['appointment_time'])); ?></strong>. <a href="../appointments.php" style="margin-left:8px;color:var(--accent-dark);font-weight:600;">View →</a></div>
    <?php endif; ?>
    
    <?php if ($completeness < 100): ?>
        <div class="completion-banner"><div class="completion-info"><strong>Profile <?php echo $completeness; ?>% complete</strong><p>Complete your profile for better recommendations.</p><div class="completion-bar-outer"><div class="completion-bar-inner" style="width:<?php echo $completeness; ?>%"></div></div></div><div class="completion-pct"><?php echo $completeness; ?>%</div></div>
    <?php else: ?>
        <div class="alert alert-success">✅ Your profile is fully complete — you're getting the best recommendations!</div>
    <?php endif; ?>
    
    <div class="stats-row">
        <div class="stat-card"><div class="stat-icon">🧭</div><div class="stat-value"><?php echo $total_careers; ?></div><div class="stat-label">Careers Available</div></div>
        <div class="stat-card"><div class="stat-icon">⭐</div><div class="stat-value"><?php echo count($recommendations); ?></div><div class="stat-label">Your Matches</div></div>
        <div class="stat-card"><div class="stat-icon">📋</div><div class="stat-value"><?php echo $assessments_taken; ?></div><div class="stat-label">Quizzes Taken</div></div>
        <div class="stat-card"><div class="stat-icon">📅</div><div class="stat-value"><?php echo $appointment_count; ?></div><div class="stat-label">Appointments</div></div>
    </div>
    
    <div class="section"><div class="section-title">My Profile</div>
        <div class="profile-card"><div class="profile-avatar"><?php echo $avatar_letter; ?></div>
        <div class="profile-details"><h2><?php echo $full_name; ?></h2><div class="profile-meta"><?php if ($profile): ?><?php echo htmlspecialchars($profile['course_of_study'] ?: 'Course not set'); ?><?php if (!empty($profile['institution'])): ?> — <?php echo htmlspecialchars($profile['institution']); ?><?php endif; ?><?php else: ?>Profile not yet completed.<?php endif; ?></div>
        <?php if (!empty($profile['skills'])): ?><div class="skills-list"><?php foreach (array_slice(explode(',', $profile['skills']), 0, 5) as $skill): ?><span class="tag"><?php echo htmlspecialchars(trim($skill)); ?></span><?php endforeach; ?></div><?php endif; ?><br>
        <?php if ($completeness === 100): ?><span class="verified-badge">✓ Profile Complete</span><?php else: ?><span class="verified-badge pending-badge">⏳ Profile Incomplete</span><?php endif; ?><span class="badge" style="margin-left:6px;">🎓 Student</span><br>
        <a href="../profile.php" class="btn-sm"><?php echo $profile ? 'Edit Profile' : 'Complete Profile'; ?></a> <a href="../assessment/interest_questionnaire.php" class="btn-sm btn-outline">Take Assessment</a>
        </div></div>
    </div>
    
    <div class="section"><div class="section-title">Quick Actions</div><div class="info-grid">
        <div class="info-card"><h4>🧭 Explore Careers</h4><p>Browse all career paths and see your matches.</p><a href="../career/recommendations.php" class="btn-sm">Browse Careers</a></div>
        <div class="info-card"><h4>📅 Book a Counsellor</h4><p>Schedule a session for personalised guidance.</p><a href="../appointments.php" class="btn-sm">Book Appointment</a></div>
        <div class="info-card"><h4>📋 Take an Assessment</h4><p>Improve your match accuracy and discover strengths.</p><a href="../assessment/interest_questionnaire.php" class="btn-sm">Start Assessment</a></div>
    </div></div>
    
    <div class="section"><div class="section-title">Career Recommendations for You</div>
    <?php if (!empty($recommendations)): ?>
        <div class="career-grid"><?php foreach ($recommendations as $rec): $career = $rec['career']; $score = min(100, max(0, (int)$rec['score'])); $name = htmlspecialchars($career['career_name'] ?? 'Unknown'); $cat = htmlspecialchars($career['category'] ?? ''); $edu = htmlspecialchars($career['education_required'] ?? 'Various'); $id = (int)($career['id'] ?? 0); ?>
            <div class="career-card"><div class="career-name">🏢 <?php echo $name; ?></div><?php if ($cat): ?><div class="career-category"><?php echo $cat; ?></div><?php endif; ?><div class="career-category"><?php echo $edu; ?></div><div class="match-bar-wrap"><div class="match-bar"><div class="match-fill" style="width:<?php echo $score; ?>%"></div></div><span class="match-pct"><?php echo $score; ?>%</span></div><?php if ($id): ?><a href="../career/career_detail.php?id=<?php echo $id; ?>" class="btn-sm">View Details</a><?php endif; ?></div>
        <?php endforeach; ?></div>
    <?php else: ?>
        <div class="empty-state"><div class="icon">🎯</div><p>No recommendations yet.<br><a href="../profile.php">Complete your profile</a> or <a href="../assessment/interest_questionnaire.php">take an assessment</a> to get matched.</p></div>
    <?php endif; ?>
    </div>
</main>
</body>
</html>