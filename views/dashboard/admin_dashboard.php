<?php
/**
 * Smart Learning Career Guidance System
 * views/dashboard/admin_dashboard.php
 */

require_once '../../includes/config.php';

if (!isLoggedIn()) { redirect('../../login.php?timeout=1'); }
if ($_SESSION['role'] !== 'admin') { redirect_by_role($_SESSION['role']); }

$timeout = 30 * 60;
if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active']) > $timeout) {
    session_unset(); session_destroy();
    redirect('../../login.php?timeout=1');
}
$_SESSION['last_active'] = time();

$full_name = htmlspecialchars($_SESSION['full_name']);

// ── Platform-wide stats ────────────────────────────────────────────────
$stats = ['students' => 0, 'counselors' => 0, 'careers' => 0, 'total_users' => 0];
if ($conn) {
    $r = mysqli_query($conn,
        "SELECT
            SUM(role='student')   AS students,
            SUM(role='counselor') AS counselors,
            COUNT(*)              AS total_users
         FROM users WHERE is_active = 1"
    );
    if ($r) {
        $row = mysqli_fetch_assoc($r);
        $stats['students']    = (int)$row['students'];
        $stats['counselors']  = (int)$row['counselors'];
        $stats['total_users'] = (int)$row['total_users'];
    }
    $r2 = mysqli_query($conn, "SELECT COUNT(*) AS c FROM career_paths WHERE is_active=1");
    if ($r2) $stats['careers'] = (int)mysqli_fetch_assoc($r2)['c'];
}

// ── Recent registrations ───────────────────────────────────────────────
$recent_users = [];
if ($conn) {
    $r = mysqli_query($conn,
        "SELECT id, full_name, email, role, is_active, created_at
           FROM users ORDER BY created_at DESC LIMIT 8"
    );
    if ($r) while ($row = mysqli_fetch_assoc($r)) $recent_users[] = $row;
}

// ── Recent activity log ────────────────────────────────────────────────
$recent_activity = [];
if ($conn) {
    $r = mysqli_query($conn,
        "SELECT al.action, al.details, al.created_at, u.full_name, u.role
           FROM activity_log al
           JOIN users u ON u.id = al.user_id
          ORDER BY al.created_at DESC LIMIT 10"
    );
    if ($r) while ($row = mysqli_fetch_assoc($r)) $recent_activity[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — <?= SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--ink:#0f1117;--ink-soft:#4a4d5a;--ink-faint:#9396a3;--canvas:#f7f6f2;--white:#fff;--accent:#6b2ac8;--accent-lt:#f0ebfb;--border:#e2dfd8;--radius:12px;--sidebar:240px;
      --red:#c0392b;--green:#1f7a5c;--blue:#1a5c8a;--orange:#c8622a;}
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
.nav-item.active{background:rgba(107,42,200,.2);color:var(--accent);border-left-color:var(--accent);font-weight:600;}
.nav-icon{font-size:1rem;flex-shrink:0;}

.sidebar-footer{margin-top:auto;padding:16px 20px;border-top:1px solid rgba(255,255,255,.08);}
.user-chip{display:flex;align-items:center;gap:10px;}
.avatar{width:34px;height:34px;background:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:.85rem;flex-shrink:0;}
.user-name{font-size:.82rem;color:#fff;font-weight:600;}
.user-role{font-size:.68rem;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.06em;}
.logout-btn{display:block;width:100%;margin-top:10px;padding:8px;text-align:center;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:8px;color:rgba(255,255,255,.5);font-size:.78rem;text-decoration:none;transition:background .2s,color .2s;}
.logout-btn:hover{background:rgba(107,42,200,.2);color:var(--accent);}

.main{flex:1;padding:36px 40px;overflow-x:hidden;}
.page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:32px;flex-wrap:wrap;gap:12px;}
.page-header h1{font-family:'DM Serif Display',serif;font-size:1.8rem;letter-spacing:-.02em;}
.page-header p{font-size:.875rem;color:var(--ink-faint);margin-top:4px;}
.header-badge{background:var(--accent-lt);color:var(--accent);padding:6px 14px;border-radius:20px;font-size:.78rem;font-weight:600;}

/* Stats grid */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-bottom:32px;}
.stat-card{background:var(--white);border-radius:var(--radius);padding:20px;box-shadow:0 1px 4px rgba(0,0,0,.05);position:relative;overflow:hidden;}
.stat-card::before{content:'';position:absolute;top:0;left:0;width:4px;height:100%;}
.stat-card.c-students::before{background:#c8622a;}
.stat-card.c-counselors::before{background:#2a6bc8;}
.stat-card.c-careers::before{background:#6b2ac8;}
.stat-card.c-total::before{background:#1f7a5c;}
.stat-icon{font-size:1.6rem;margin-bottom:10px;}
.stat-value{font-family:'DM Serif Display',serif;font-size:1.9rem;color:var(--ink);}
.stat-label{font-size:.72rem;color:var(--ink-faint);margin-top:4px;text-transform:uppercase;letter-spacing:.06em;}

.two-col{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px;}

.panel{background:var(--white);border-radius:var(--radius);padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.05);}
.section-title{font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--accent);margin-bottom:16px;padding-bottom:8px;border-bottom:1.5px solid var(--accent-lt);}

/* Users table */
table{width:100%;border-collapse:collapse;}
th,td{padding:10px 12px;text-align:left;font-size:.82rem;}
th{font-size:.68rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--ink-faint);border-bottom:1.5px solid var(--border);}
td{border-bottom:1px solid var(--border);color:var(--ink-soft);}
tr:last-child td{border-bottom:none;}
tr:hover td{background:var(--canvas);}

.role-tag{display:inline-block;padding:2px 8px;border-radius:6px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
.role-tag.student  {background:#fef0e7;color:var(--orange);}
.role-tag.counselor{background:#e8f0fb;color:var(--blue);}
.role-tag.admin    {background:var(--accent-lt);color:var(--accent);}

.status-dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:5px;}
.status-dot.active  {background:#27ae60;}
.status-dot.inactive{background:#e74c3c;}

/* Activity log */
.activity-list{list-style:none;display:flex;flex-direction:column;gap:12px;}
.activity-item{display:flex;align-items:flex-start;gap:12px;}
.activity-icon{width:32px;height:32px;border-radius:50%;background:var(--accent-lt);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;}
.activity-body{flex:1;}
.activity-who{font-size:.82rem;font-weight:600;color:var(--ink);}
.activity-desc{font-size:.78rem;color:var(--ink-faint);}
.activity-time{font-size:.7rem;color:var(--ink-faint);margin-top:2px;}

@media(max-width:900px){.two-col{grid-template-columns:1fr;}}
@media(max-width:768px){body{flex-direction:column;}.sidebar{width:100%;min-height:auto;height:auto;position:relative;}.main{padding:20px 16px;}}
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
        <div class="nav-label">Administration</div>
        <a href="admin_dashboard.php" class="nav-item active"><span class="nav-icon">🏠</span> Dashboard</a>
        <a href="../admin/users.php" class="nav-item"><span class="nav-icon">👥</span> Users</a>
        <a href="../admin/counselors.php" class="nav-item"><span class="nav-icon">💼</span> Counsellors</a>
        <a href="../admin/careers.php" class="nav-item"><span class="nav-icon">🧭</span> Career Paths</a>
        <a href="../admin/resources.php" class="nav-item"><span class="nav-icon">📚</span> Resources</a>
        <a href="../admin/appointments.php" class="nav-item"><span class="nav-icon">📅</span> Appointments</a>
        <div class="nav-label" style="margin-top:12px;">System</div>
        <a href="../admin/activity_log.php" class="nav-item"><span class="nav-icon">📋</span> Activity Log</a>
        <a href="../admin/settings.php" class="nav-item"><span class="nav-icon">⚙</span> Settings</a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-chip">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <div>
                <div class="user-name"><?= $full_name ?></div>
                <div class="user-role">Administrator</div>
            </div>
        </div>
        <a href="../../logout.php" class="logout-btn">⬡ Sign out</a>
    </div>
</aside>

<main class="main">

    <div class="page-header">
        <div>
            <h1>Admin Dashboard</h1>
            <p>Platform overview &amp; management — <?= date('l, d F Y') ?></p>
        </div>
        <span class="header-badge">⚙ Administrator</span>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card c-students">
            <div class="stat-icon">🎓</div>
            <div class="stat-value"><?= $stats['students'] ?></div>
            <div class="stat-label">Students</div>
        </div>
        <div class="stat-card c-counselors">
            <div class="stat-icon">💼</div>
            <div class="stat-value"><?= $stats['counselors'] ?></div>
            <div class="stat-label">Counsellors</div>
        </div>
        <div class="stat-card c-careers">
            <div class="stat-icon">🧭</div>
            <div class="stat-value"><?= $stats['careers'] ?></div>
            <div class="stat-label">Career Paths</div>
        </div>
        <div class="stat-card c-total">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?= $stats['total_users'] ?></div>
            <div class="stat-label">Total Users</div>
        </div>
    </div>

    <!-- Recent users + Activity log -->
    <div class="two-col">

        <!-- Recent users -->
        <div class="panel">
            <div class="section-title">Recent Registrations</div>
            <?php if ($recent_users): ?>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_users as $u): ?>
                    <tr>
                        <td>
                            <strong style="color:var(--ink);font-size:.85rem;"><?= htmlspecialchars($u['full_name']) ?></strong><br>
                            <span style="font-size:.72rem;"><?= htmlspecialchars($u['email']) ?></span>
                        </td>
                        <td><span class="role-tag <?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                        <td>
                            <span class="status-dot <?= $u['is_active'] ? 'active' : 'inactive' ?>"></span>
                            <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="font-size:.85rem;color:var(--ink-faint);text-align:center;padding:20px;">No users yet.</p>
            <?php endif; ?>
            <div style="margin-top:16px;">
                <a href="../admin/users.php" style="font-size:.82rem;color:var(--accent);font-weight:600;text-decoration:none;">View all users →</a>
            </div>
        </div>

        <!-- Activity log -->
        <div class="panel">
            <div class="section-title">Recent Activity</div>
            <?php if ($recent_activity): ?>
                <ul class="activity-list">
                    <?php
                    $icons = ['login'=>'🔑','register'=>'✨','logout'=>'👋'];
                    foreach ($recent_activity as $a):
                        $icon = $icons[$a['action']] ?? '📋';
                        $ago  = human_time_diff($a['created_at']);
                    ?>
                    <li class="activity-item">
                        <div class="activity-icon"><?= $icon ?></div>
                        <div class="activity-body">
                            <div class="activity-who"><?= htmlspecialchars($a['full_name']) ?>
                                <span class="role-tag <?= $a['role'] ?>"><?= ucfirst($a['role']) ?></span>
                            </div>
                            <div class="activity-desc"><?= htmlspecialchars($a['details'] ?: $a['action']) ?></div>
                            <div class="activity-time">🕐 <?= $ago ?></div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p style="font-size:.85rem;color:var(--ink-faint);text-align:center;padding:20px;">No activity recorded yet.</p>
            <?php endif; ?>
        </div>

    </div>

</main>
</body>
</html>
<?php
// Helper used only on admin dashboard
function human_time_diff(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)      return 'just now';
    if ($diff < 3600)    return floor($diff/60) . ' min ago';
    if ($diff < 86400)   return floor($diff/3600) . ' hr ago';
    return floor($diff/86400) . ' day(s) ago';
}
?>