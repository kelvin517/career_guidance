<?php
/**
 * Smart Learning Career Guidance System
 * views/dashboard/counselor_dashboard.php
 */

require_once '../../includes/config.php';

if (!isLoggedIn()) { redirect('../../login.php?timeout=1'); }
if ($_SESSION['role'] !== 'counselor') { redirect_by_role($_SESSION['role']); }

$timeout = 30 * 60;
if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active']) > $timeout) {
    session_unset(); session_destroy();
    redirect('../../login.php?timeout=1');
}
$_SESSION['last_active'] = time();

$user_id   = (int)$_SESSION['user_id'];
$full_name = htmlspecialchars($_SESSION['full_name']);

// ── Counselor profile ─────────────────────────────────────────────────
$profile = null;
if ($conn) {
    $st = mysqli_prepare($conn,
        'SELECT specialization, years_experience, is_verified
           FROM counselor_profiles WHERE user_id = ? LIMIT 1'
    );
    if ($st) {
        mysqli_stmt_bind_param($st, 'i', $user_id);
        mysqli_stmt_execute($st);
        $profile = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
    }
}

// ── Quick stats ────────────────────────────────────────────────────────
$total_students = 0;
if ($conn) {
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='student' AND is_active=1");
    if ($r) $total_students = (int)mysqli_fetch_assoc($r)['c'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Counsellor Dashboard — <?= SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--ink:#0f1117;--ink-soft:#4a4d5a;--ink-faint:#9396a3;--canvas:#f7f6f2;--white:#fff;--accent:#2a6bc8;--accent-lt:#e8f0fb;--border:#e2dfd8;--radius:12px;--sidebar:240px;}
body{font-family:'DM Sans',sans-serif;background:var(--canvas);color:var(--ink);display:flex;min-height:100vh;}

.sidebar{width:var(--sidebar);min-height:100vh;background:var(--ink);display:flex;flex-direction:column;flex-shrink:0;position:sticky;top:0;height:100vh;overflow-y:auto;}
.sidebar-brand{padding:28px 24px 20px;border-bottom:1px solid rgba(255,255,255,.08);}
.brand-icon{width:36px;height:36px;background:var(--accent);border-radius:8px;display:flex;align-items:center;justify-content:center;margin-bottom:10px;}
.brand-icon svg{width:20px;height:20px;fill:none;stroke:#fff;stroke-width:2;stroke-linecap:round;}
.brand-title{font-family:'DM Serif Display',serif;font-size:1rem;color:#fff;}
.brand-sub{font-size:.65rem;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.08em;margin-top:3px;}

.nav-section{padding:20px 0 8px;}
.nav-label{font-size:.6rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.25);padding:0 20px 8px;}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 20px;font-size:.875rem;color:rgba(255,255,255,.55);text-decoration:none;transition:background .2s,color .2s;border-left:3px solid transparent;}
.nav-item:hover{background:rgba(255,255,255,.06);color:#fff;}
.nav-item.active{background:rgba(42,107,200,.2);color:var(--accent);border-left-color:var(--accent);font-weight:600;}
.nav-icon{font-size:1rem;flex-shrink:0;}

.sidebar-footer{margin-top:auto;padding:16px 20px;border-top:1px solid rgba(255,255,255,.08);}
.user-chip{display:flex;align-items:center;gap:10px;}
.avatar{width:34px;height:34px;background:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:.85rem;flex-shrink:0;}
.user-name{font-size:.82rem;color:#fff;font-weight:600;}
.user-role{font-size:.68rem;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;}
.logout-btn{display:block;width:100%;margin-top:10px;padding:8px;text-align:center;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:8px;color:rgba(255,255,255,.5);font-size:.78rem;text-decoration:none;transition:background .2s,color .2s;}
.logout-btn:hover{background:rgba(42,107,200,.2);color:var(--accent);}

.main{flex:1;padding:36px 40px;}
.page-header{margin-bottom:32px;}
.page-header h1{font-family:'DM Serif Display',serif;font-size:1.8rem;letter-spacing:-.02em;}
.page-header p{font-size:.875rem;color:var(--ink-faint);margin-top:4px;}

.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:32px;}
.stat-card{background:var(--white);border-radius:var(--radius);padding:20px;box-shadow:0 1px 4px rgba(0,0,0,.05);}
.stat-icon{font-size:1.6rem;margin-bottom:10px;}
.stat-value{font-family:'DM Serif Display',serif;font-size:1.8rem;color:var(--ink);}
.stat-label{font-size:.75rem;color:var(--ink-faint);margin-top:4px;text-transform:uppercase;letter-spacing:.06em;}

.section{margin-bottom:32px;}
.section-title{font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--accent);margin-bottom:16px;padding-bottom:8px;border-bottom:1.5px solid var(--accent-lt);}

.profile-card{background:var(--white);border-radius:var(--radius);padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.05);display:flex;align-items:flex-start;gap:20px;}
.profile-avatar{width:60px;height:60px;background:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;color:#fff;flex-shrink:0;}
.profile-details h2{font-size:1.1rem;font-weight:600;margin-bottom:4px;}
.profile-meta{font-size:.82rem;color:var(--ink-faint);}
.badge{display:inline-block;background:var(--accent-lt);color:var(--accent);padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:600;margin-top:8px;}
.btn-sm{display:inline-block;margin-top:12px;padding:7px 16px;background:var(--accent);color:#fff;border-radius:8px;font-size:.8rem;font-weight:600;text-decoration:none;transition:background .2s;}
.btn-sm:hover{background:#1a5baa;}

.info-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;}
.info-card{background:var(--white);border-radius:var(--radius);padding:20px;box-shadow:0 1px 4px rgba(0,0,0,.05);border-left:3px solid var(--accent);}
.info-card h4{font-size:.9rem;font-weight:600;margin-bottom:8px;}
.info-card p{font-size:.82rem;color:var(--ink-faint);line-height:1.5;}

.verified-badge{display:inline-flex;align-items:center;gap:6px;background:#edf7f3;color:#1f7a5c;border:1px solid #b8e0d2;padding:4px 12px;border-radius:20px;font-size:.75rem;font-weight:600;}
.pending-badge{background:#fdf8ed;color:#8a6d1a;border-color:#f0dda0;}

@media(max-width:768px){body{flex-direction:column;}.sidebar{width:100%;min-height:auto;height:auto;}.main{padding:20px 16px;}}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
        </div>
        <div class="brand-title">Smart Learning</div>
        <div class="brand-sub">Career Guidance</div>
    </div>

    <nav class="nav-section">
        <div class="nav-label">Menu</div>
        <a href="counselor_dashboard.php" class="nav-item active"><span class="nav-icon">🏠</span> Dashboard</a>
        <a href="../profile.php" class="nav-item"><span class="nav-icon">👤</span> My Profile</a>
        <a href="../students.php" class="nav-item"><span class="nav-icon">🎓</span> Students</a>
        <a href="../appointments.php" class="nav-item"><span class="nav-icon">📅</span> Appointments</a>
        <a href="../careers.php" class="nav-item"><span class="nav-icon">🧭</span> Career Paths</a>
        <a href="../resources.php" class="nav-item"><span class="nav-icon">📚</span> Resources</a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-chip">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <div>
                <div class="user-name"><?= $full_name ?></div>
                <div class="user-role">Counsellor</div>
            </div>
        </div>
        <a href="../../logout.php" class="logout-btn">⬡ Sign out</a>
    </div>
</aside>

<main class="main">

    <div class="page-header">
        <h1>Welcome, <?= explode(' ', $full_name)[0] ?> 👋</h1>
        <p>Manage your students, appointments, and career guidance resources.</p>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon">🎓</div>
            <div class="stat-value"><?= $total_students ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-value">0</div>
            <div class="stat-label">Appointments</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📚</div>
            <div class="stat-value">0</div>
            <div class="stat-label">Resources Shared</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-value">
                <?php if ($profile): ?>
                    <?= $profile['is_verified'] ? '<span style="color:#1f7a5c">Yes</span>' : '<span style="color:#8a6d1a">Pending</span>' ?>
                <?php else: ?>—<?php endif; ?>
            </div>
            <div class="stat-label">Verified Status</div>
        </div>
    </div>

    <!-- Profile -->
    <div class="section">
        <div class="section-title">My Profile</div>
        <div class="profile-card">
            <div class="profile-avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <div class="profile-details">
                <h2><?= $full_name ?></h2>
                <div class="profile-meta">
                    <?php if ($profile): ?>
                        <?= htmlspecialchars($profile['specialization']) ?> ·
                        <?= $profile['years_experience'] ?> years experience
                    <?php else: ?>Profile not yet set up<?php endif; ?>
                </div>
                <?php if ($profile): ?>
                    <?php if ($profile['is_verified']): ?>
                        <span class="verified-badge">✓ Verified Counsellor</span>
                    <?php else: ?>
                        <span class="verified-badge pending-badge">⏳ Verification Pending</span>
                    <?php endif; ?>
                <?php endif; ?>
                <br>
                <a href="../profile.php" class="btn-sm"><?= $profile ? 'Edit Profile' : 'Complete Profile' ?></a>
            </div>
        </div>
    </div>

    <!-- Quick actions -->
    <div class="section">
        <div class="section-title">Quick Actions</div>
        <div class="info-grid">
            <div class="info-card">
                <h4>📅 Manage Appointments</h4>
                <p>View upcoming sessions, confirm or reschedule student bookings.</p>
                <a href="../appointments.php" class="btn-sm" style="margin-top:12px;">View Appointments</a>
            </div>
            <div class="info-card">
                <h4>🎓 View Students</h4>
                <p>Browse student profiles, review their skills and career interests.</p>
                <a href="../students.php" class="btn-sm" style="margin-top:12px;">Browse Students</a>
            </div>
            <div class="info-card">
                <h4>📚 Upload Resource</h4>
                <p>Share articles, PDFs, and learning materials with students.</p>
                <a href="../resources.php?action=upload" class="btn-sm" style="margin-top:12px;">Upload Resource</a>
            </div>
        </div>
    </div>

</main>
</body>
</html>