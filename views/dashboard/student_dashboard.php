<?php
// Enable error reporting for debugging (remove after it works)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../includes/config.php';
require_once '../../includes/functions.php'; // ensure redirect_by_role() and getCareerRecommendations() are loaded

// Authentication & role check
if (!isLoggedIn()) {
    redirect('../../login.php?timeout=1');
}
if ($_SESSION['role'] !== 'student') {
    redirect_by_role($_SESSION['role']);
}

$user_id = (int)$_SESSION['user_id'];
$full_name = htmlspecialchars($_SESSION['full_name']);
$first_name = htmlspecialchars(explode(' ', trim($full_name))[0]);
$avatar_letter = strtoupper(mb_substr(trim($full_name), 0, 1));

$hour = (int)date('H');
$greeting = $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening');

// ─── Fetch student profile ────────────────────────────────
$profile = null;
if ($conn) {
    $stmt = mysqli_prepare($conn, "SELECT institution, course_of_study, skills, interests FROM student_profiles WHERE user_id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $profile = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    }
}

// ─── Profile completeness (0‑100) ─────────────────────────
$completeness = 0;
if ($profile) {
    if (!empty($profile['institution']))     $completeness += 25;
    if (!empty($profile['course_of_study'])) $completeness += 25;
    if (!empty($profile['skills']))          $completeness += 25;
    if (!empty($profile['interests']))       $completeness += 25;
}

// ─── Career recommendations ───────────────────────────────
$recommendations = getCareerRecommendations($user_id);

// ─── Total active careers ─────────────────────────────────
$total_careers = 0;
$career_check = mysqli_query($conn, "SELECT COUNT(*) AS c FROM career_paths WHERE is_active = 1");
if ($career_check) {
    $total_careers = (int)mysqli_fetch_assoc($career_check)['c'];
}

// ─── Assessments taken ────────────────────────────────────
$assessments_taken = 0;
$assess_check = mysqli_query($conn, "SELECT COUNT(*) AS c FROM assessment_results WHERE user_id = $user_id");
if ($assess_check) {
    $assessments_taken = (int)mysqli_fetch_assoc($assess_check)['c'];
}

// ─── Upcoming appointments count ──────────────────────────
$appointment_count = 0;
$app_check = mysqli_query($conn, "SELECT COUNT(*) AS c FROM appointments WHERE student_id = $user_id AND appointment_date >= CURDATE() AND status != 'cancelled'");
if ($app_check) {
    $appointment_count = (int)mysqli_fetch_assoc($app_check)['c'];
}

// ─── Next appointment (for banner) ────────────────────────
$next_appointment = null;
$next_app_query = mysqli_query($conn, "
    SELECT a.appointment_date, a.appointment_time, a.status, u.full_name AS counselor_name
    FROM appointments a
    JOIN users u ON u.id = a.counselor_id
    WHERE a.student_id = $user_id
      AND a.appointment_date >= CURDATE()
      AND a.status != 'cancelled'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 1
");
if ($next_app_query) {
    $next_appointment = mysqli_fetch_assoc($next_app_query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard — <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --ink:#0f1117;--ink-soft:#4a4d5a;--ink-faint:#9396a3;
            --canvas:#f7f6f2;--white:#fff;
            --accent:#c8622a;--accent-lt:#f0ebe3;--accent-dark:#b5551f;
            --border:#e2dfd8;
            --success:#1f7a5c;--success-lt:#edf7f3;--success-border:#b8e0d2;
            --warning:#8a6d1a;--warning-lt:#fdf8ed;--warning-border:#f0dda0;
            --radius:12px;--sidebar:240px;
            --shadow:0 1px 4px rgba(0,0,0,.05);--shadow-md:0 6px 20px rgba(0,0,0,.09);
        }
        body{font-family:'DM Sans',sans-serif;background:var(--canvas);color:var(--ink);display:flex;min-height:100vh;}
        .sidebar{width:var(--sidebar);min-height:100vh;background:var(--ink);display:flex;flex-direction:column;flex-shrink:0;position:sticky;top:0;height:100vh;overflow-y:auto;}
        .sidebar-brand{padding:28px 24px 20px;border-bottom:1px solid rgba(255,255,255,.08);}
        .brand-icon{width:36px;height:36px;background:var(--accent);border-radius:8px;display:flex;align-items:center;justify-content:center;margin-bottom:10px;}
        .brand-icon svg{width:20px;height:20px;fill:none;stroke:#fff;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
        .brand-title{font-family:'DM Serif Display',serif;font-size:1rem;color:#fff;line-height:1.2;}
        .brand-sub{font-size:.65rem;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.08em;margin-top:3px;}
        .nav-section{padding:20px 0 8px;flex:1;}
        .nav-label{font-size:.6rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.25);padding:0 20px 8px;}
        .nav-item{display:flex;align-items:center;gap:10px;padding:10px 20px;font-size:.875rem;color:rgba(255,255,255,.55);text-decoration:none;transition:background .15s,color .15s;border-left:3px solid transparent;}
        .nav-item:hover{background:rgba(255,255,255,.06);color:#fff;}
        .nav-item.active{background:rgba(200,98,42,.18);color:var(--accent);border-left-color:var(--accent);font-weight:600;}
        .nav-icon{font-size:1rem;flex-shrink:0;}
        .sidebar-footer{margin-top:auto;padding:16px 20px;border-top:1px solid rgba(255,255,255,.08);}
        .user-chip{display:flex;align-items:center;gap:10px;}
        .avatar{width:34px;height:34px;background:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:.85rem;flex-shrink:0;}
        .user-info{overflow:hidden;}
        .user-name{font-size:.82rem;color:#fff;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .user-role{font-size:.68rem;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;}
        .logout-btn{display:block;width:100%;margin-top:10px;padding:8px;text-align:center;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:8px;color:rgba(255,255,255,.5);font-size:.78rem;text-decoration:none;transition:background .2s,color .2s;}
        .logout-btn:hover{background:rgba(200,98,42,.2);color:var(--accent);}
        .main{flex:1;padding:36px 40px;}
        .page-header{margin-bottom:32px;}
        .page-header h1{font-family:'DM Serif Display',serif;font-size:1.8rem;letter-spacing:-.02em;}
        .page-header p{font-size:.875rem;color:var(--ink-faint);margin-top:4px;}
        .alert{padding:12px 16px;border-radius:var(--radius);font-size:.83rem;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;border:1px solid;}
        .alert-warning{background:var(--warning-lt);color:var(--warning);border-color:var(--warning-border);}
        .alert-success{background:var(--success-lt);color:var(--success);border-color:var(--success-border);}
        .alert-info{background:var(--accent-lt);color:var(--accent-dark);border-color:#e8c9b0;}
        .completion-banner{background:var(--white);border-radius:var(--radius);padding:16px 20px;display:flex;align-items:center;gap:16px;margin-bottom:28px;border-left:4px solid var(--accent);box-shadow:var(--shadow);}
        .completion-info{flex:1;}
        .completion-info strong{font-size:.88rem;color:var(--ink);}
        .completion-info p{font-size:.78rem;color:var(--ink-faint);margin-top:2px;}
        .completion-bar-outer{height:6px;background:var(--border);border-radius:3px;margin-top:8px;overflow:hidden;}
        .completion-bar-inner{height:100%;background:var(--accent);border-radius:3px;}
        .completion-pct{font-family:'DM Serif Display',serif;font-size:1.3rem;color:var(--accent);flex-shrink:0;}
        .stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:32px;}
        .stat-card{background:var(--white);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);}
        .stat-icon{font-size:1.6rem;margin-bottom:10px;}
        .stat-value{font-family:'DM Serif Display',serif;font-size:1.8rem;color:var(--ink);line-height:1;}
        .stat-label{font-size:.75rem;color:var(--ink-faint);margin-top:4px;text-transform:uppercase;letter-spacing:.06em;}
        .section{margin-bottom:32px;}
        .section-title{font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--accent);margin-bottom:16px;padding-bottom:8px;border-bottom:1.5px solid var(--accent-lt);}
        .profile-card{background:var(--white);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);display:flex;align-items:flex-start;gap:20px;}
        .profile-avatar{width:60px;height:60px;background:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;color:#fff;flex-shrink:0;}
        .profile-details h2{font-size:1.1rem;font-weight:600;margin-bottom:4px;}
        .profile-meta{font-size:.82rem;color:var(--ink-faint);line-height:1.5;}
        .badge{display:inline-block;background:var(--accent-lt);color:var(--accent);padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:600;margin-top:8px;}
        .tag{display:inline-block;background:var(--canvas);border:1px solid var(--border);color:var(--ink-soft);padding:3px 9px;border-radius:20px;font-size:.72rem;margin:2px;}
        .verified-badge{display:inline-flex;align-items:center;gap:6px;background:var(--success-lt);color:var(--success);border:1px solid var(--success-border);padding:4px 12px;border-radius:20px;font-size:.75rem;font-weight:600;}
        .pending-badge{background:var(--warning-lt);color:var(--warning);border-color:var(--warning-border);}
        .btn-sm{display:inline-block;margin-top:12px;padding:7px 16px;background:var(--accent);color:#fff;border-radius:8px;font-size:.8rem;font-weight:600;text-decoration:none;transition:background .2s;}
        .btn-sm:hover{background:var(--accent-dark);}
        .btn-outline{background:transparent;border:1.5px solid var(--accent);color:var(--accent);}
        .btn-outline:hover{background:var(--accent);color:#fff;}
        .info-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;}
        .info-card{background:var(--white);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);border-left:3px solid var(--accent);}
        .info-card h4{font-size:.9rem;font-weight:600;margin-bottom:8px;}
        .career-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;}
        .career-card{background:var(--white);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);border-left:3px solid var(--accent);transition:transform .2s,box-shadow .2s;display:flex;flex-direction:column;gap:8px;}
        .career-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);}
        .career-name{font-size:.95rem;font-weight:600;}
        .career-category{font-size:.72rem;color:var(--ink-faint);text-transform:uppercase;letter-spacing:.05em;}
        .match-bar-wrap{display:flex;align-items:center;gap:10px;margin-top:4px;}
        .match-bar{flex:1;height:4px;background:var(--border);border-radius:2px;overflow:hidden;}
        .match-fill{height:100%;background:var(--accent);border-radius:2px;transition:width .6s ease;}
        .match-pct{font-size:.75rem;font-weight:700;color:var(--accent);flex-shrink:0;}
        .empty-state{text-align:center;padding:40px 20px;color:var(--ink-faint);}
        .empty-state .icon{font-size:2.5rem;margin-bottom:12px;}
        @media(max-width:768px){
            body{flex-direction:column;}
            .sidebar{width:100%;min-height:auto;height:auto;position:relative;}
            .nav-section{display:none;}
            .sidebar-footer{margin:0;}
            .main{padding:20px 16px;}
            .stats-row{grid-template-columns:repeat(2,1fr);}
            .profile-card{flex-direction:column;}
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
        </div>
        <div class="brand-title">Smart Learning</div>
        <div class="brand-sub">Career Guidance</div>
    </div>
    <nav class="nav-section">
        <div class="nav-label">Menu</div>
        <a href="student_dashboard.php" class="nav-item active"><span class="nav-icon">🏠</span> Dashboard</a>
        <a href="../profile.php"        class="nav-item"><span class="nav-icon">👤</span> My Profile</a>
        <a href="../careers.php"        class="nav-item"><span class="nav-icon">🧭</span> Browse Careers</a>
        <a href="../assessments.php"    class="nav-item"><span class="nav-icon">📋</span> Assessments</a>
        <a href="../appointments.php"   class="nav-item"><span class="nav-icon">📅</span> Book Counsellor</a>
        <a href="../resources.php"      class="nav-item"><span class="nav-icon">📚</span> Resources</a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-chip">
            <div class="avatar"><?= $avatar_letter ?></div>
            <div class="user-info">
                <div class="user-name"><?= $full_name ?></div>
                <div class="user-role">Student</div>
            </div>
        </div>
        <a href="../../logout.php" class="logout-btn">⬡ Sign out</a>
    </div>
</aside>

<!-- Main Content -->
<main class="main">
    <div class="page-header">
        <h1>Good <?= $greeting ?>, <?= $first_name ?> 👋</h1>
        <p>Here's an overview of your career guidance journey.</p>
    </div>

    <!-- Timeout notice -->
    <?php if (isset($_GET['timeout'])): ?>
        <div class="alert alert-warning">⚠️ Your session expired. Please sign in again.</div>
    <?php endif; ?>

    <!-- Next appointment banner -->
    <?php if ($next_appointment): ?>
        <div class="alert alert-info">
            📅 You have an upcoming session with <strong><?= htmlspecialchars($next_appointment['counselor_name']) ?></strong>
            on <strong><?= date('D j M Y', strtotime($next_appointment['appointment_date'])) ?></strong>
            at <strong><?= date('g:i A', strtotime($next_appointment['appointment_time'])) ?></strong>.
            <a href="../appointments.php" style="margin-left:8px;color:var(--accent-dark);font-weight:600;">View →</a>
        </div>
    <?php endif; ?>

    <!-- Profile completeness banner -->
    <?php if ($completeness < 100): ?>
        <div class="completion-banner">
            <div class="completion-info">
                <strong>Your profile is <?= $completeness ?>% complete</strong>
                <p>Complete your profile to unlock more accurate career recommendations.</p>
                <div class="completion-bar-outer"><div class="completion-bar-inner" style="width:<?= $completeness ?>%"></div></div>
            </div>
            <div class="completion-pct"><?= $completeness ?>%</div>
        </div>
    <?php else: ?>
        <div class="alert alert-success">✅ Your profile is fully complete — you're getting the best recommendations!</div>
    <?php endif; ?>

    <!-- Stats row -->
    <div class="stats-row">
        <div class="stat-card"><div class="stat-icon">🧭</div><div class="stat-value"><?= $total_careers ?></div><div class="stat-label">Careers Available</div></div>
        <div class="stat-card"><div class="stat-icon">⭐</div><div class="stat-value"><?= count($recommendations) ?></div><div class="stat-label">Your Matches</div></div>
        <div class="stat-card"><div class="stat-icon">📋</div><div class="stat-value"><?= $assessments_taken ?></div><div class="stat-label">Assessments Taken</div></div>
        <div class="stat-card"><div class="stat-icon">📅</div><div class="stat-value"><?= $appointment_count ?></div><div class="stat-label">Appointments</div></div>
    </div>

    <!-- Profile snapshot -->
    <div class="section">
        <div class="section-title">My Profile</div>
        <div class="profile-card">
            <div class="profile-avatar"><?= $avatar_letter ?></div>
            <div class="profile-details">
                <h2><?= $full_name ?></h2>
                <div class="profile-meta">
                    <?php if ($profile): ?>
                        <?= htmlspecialchars($profile['course_of_study'] ?: 'Course not set') ?>
                        <?php if (!empty($profile['institution'])): ?> — <?= htmlspecialchars($profile['institution']) ?><?php endif; ?>
                    <?php else: ?>
                        Profile not yet completed.
                    <?php endif; ?>
                </div>
                <?php if (!empty($profile['skills'])): ?>
                    <div class="skills-list">
                        <?php foreach (array_slice(explode(',', $profile['skills']), 0, 5) as $skill): ?>
                            <span class="tag"><?= htmlspecialchars(trim($skill)) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <br>
                <?php if ($completeness === 100): ?>
                    <span class="verified-badge">✓ Profile Complete</span>
                <?php else: ?>
                    <span class="verified-badge pending-badge">⏳ Profile Incomplete</span>
                <?php endif; ?>
                <span class="badge" style="margin-left:6px;">🎓 Student</span><br>
                <a href="../profile.php" class="btn-sm"><?= $profile ? 'Edit Profile' : 'Complete Profile' ?></a>
                <a href="../assessments.php" class="btn-sm btn-outline">Take Assessment</a>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="section">
        <div class="section-title">Quick Actions</div>
        <div class="info-grid">
            <div class="info-card"><h4>🧭 Explore Careers</h4><p>Browse all available career paths and see how your skills and interests match up.</p><a href="../careers.php" class="btn-sm" style="margin-top:12px;">Browse Careers</a></div>
            <div class="info-card"><h4>📅 Book a Counsellor</h4><p>Schedule a one-on-one session with a career counsellor for personalised guidance.</p><a href="../appointments.php" class="btn-sm" style="margin-top:12px;">Book Appointment</a></div>
            <div class="info-card"><h4>📋 Take an Assessment</h4><p>Complete assessments to improve your match accuracy and discover your strengths.</p><a href="../assessments.php" class="btn-sm" style="margin-top:12px;">Start Assessment</a></div>
        </div>
    </div>

    <!-- Career recommendations -->
    <div class="section">
        <div class="section-title">Career Recommendations for You</div>
        <?php if (!empty($recommendations)): ?>
            <div class="career-grid">
                <?php foreach ($recommendations as $rec):
                    $career = $rec['career'];
                    $score  = min(100, max(0, (int)$rec['score']));
                    $name   = htmlspecialchars($career['career_name'] ?? 'Unknown');
                    $cat    = htmlspecialchars($career['category'] ?? '');
                    $edu    = htmlspecialchars($career['education_required'] ?? 'Various');
                    $id     = (int)($career['id'] ?? 0);
                ?>
                    <div class="career-card">
                        <div class="career-name">🏢 <?= $name ?></div>
                        <?php if ($cat): ?><div class="career-category"><?= $cat ?></div><?php endif; ?>
                        <div class="career-category"><?= $edu ?></div>
                        <div class="match-bar-wrap">
                            <div class="match-bar"><div class="match-fill" style="width:<?= $score ?>%"></div></div>
                            <span class="match-pct"><?= $score ?>%</span>
                        </div>
                        <div class="career-actions">
                            <?php if ($id): ?>
                                <a href="../career_details.php?id=<?= $id ?>" class="btn-sm">View Details</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">🎯</div>
                <p>No recommendations yet.<br><a href="../profile.php">Complete your profile</a> or <a href="../assessments.php">take an assessment</a> to get matched.</p>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>