<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');
if ($_SESSION['role'] !== 'admin') redirect_by_role($_SESSION['role']);

$userId = $_SESSION['user_id'];
$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
$role = $_SESSION['role'];

// Get filter parameters
$actionFilter = $_GET['action'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT l.*, u.full_name as user_name, u.role as user_role 
        FROM activity_logs l
        JOIN users u ON l.user_id = u.id
        WHERE 1=1";
if (!empty($actionFilter)) {
    $sql .= " AND l.action = '$actionFilter'";
}
if (!empty($dateFrom)) {
    $sql .= " AND DATE(l.created_at) >= '$dateFrom'";
}
if (!empty($dateTo)) {
    $sql .= " AND DATE(l.created_at) <= '$dateTo'";
}
if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE '%$search%' OR l.details LIKE '%$search%' OR l.ip_address LIKE '%$search%')";
}
$sql .= " ORDER BY l.created_at DESC LIMIT 500";

$logs = mysqli_query($conn, $sql);
$totalLogs = mysqli_num_rows($logs);

// Get statistics
$loginCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM activity_logs WHERE action = 'login'"))['c'];
$registerCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM activity_logs WHERE action = 'register'"))['c'];
$last24h = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"))['c'];

// Get unique actions for filter
$actions = [];
$actionResult = mysqli_query($conn, "SELECT DISTINCT action FROM activity_logs ORDER BY action");
while ($row = mysqli_fetch_assoc($actionResult)) {
    $actions[] = $row['action'];
}

// Clear logs (admin only)
if (isset($_GET['clear']) && $_GET['clear'] == 'all') {
    mysqli_query($conn, "TRUNCATE TABLE activity_logs");
    header("Location: system_logs.php?cleared=1");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs — Smart Learning Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --ink:#0f1117;--ink-soft:#4a4d5a;--ink-faint:#9396a3;
            --canvas:#f5f7f9;--white:#fff;
            --accent:#2563a8;--accent-lt:#eef3fb;
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
            text-decoration:none;
        }
        .sb-nav li a:hover{background:rgba(255,255,255,.06);color:#fff}
        .sb-nav li a.active{background:var(--accent);color:#fff}
        .sb-footer{margin-top:auto;padding:16px 10px;border-top:1px solid rgba(255,255,255,.06)}
        .sb-user{display:flex;align-items:center;gap:10px;padding:10px 14px;}
        .sb-avatar{width:36px;height:36px;border-radius:10px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;}
        .sb-user-name{font-size:.85rem;font-weight:600;color:#fff}
        .sb-user-sub{font-size:.7rem;color:rgba(255,255,255,.3)}
        .sb-logout{color:rgba(255,255,255,.3);text-decoration:none;font-size:.8rem;padding:8px 14px;display:block;margin-top:8px}
        .sb-logout:hover{color:#fff}

        .main{margin-left:var(--sidebar);min-height:100vh}
        .topbar{background:var(--white);border-bottom:1px solid var(--border);padding:16px 36px;display:flex;align-items:center;justify-content:space-between}
        .topbar-breadcrumb{font-size:.8rem;color:var(--ink-faint)}
        .topbar-breadcrumb span{color:var(--ink);font-weight:600}
        .body{padding:32px 36px}

        .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:28px}
        .stat-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:20px;text-align:center}
        .stat-value{font-family:'DM Serif Display',serif;font-size:2rem}
        .stat-label{font-size:.7rem;color:var(--ink-faint);margin-top:4px}

        .filter-bar{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:20px;margin-bottom:24px}
        .filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;align-items:flex-end}
        .filter-group label{display:block;font-size:.7rem;font-weight:600;margin-bottom:6px}
        .filter-group input,.filter-group select{width:100%;padding:10px;border:1.5px solid var(--border);border-radius:8px}
        .btn{background:var(--accent);color:#fff;border:none;padding:10px 20px;border-radius:8px;cursor:pointer}
        .btn-danger{background:var(--red)}

        .log-table{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);overflow-x:auto}
        table{width:100%;border-collapse:collapse}
        th,td{padding:14px 16px;text-align:left;border-bottom:1px solid var(--border)}
        th{background:var(--accent-lt);font-weight:600}
        .action-badge{display:inline-block;padding:4px 10px;border-radius:20px;font-size:.7rem;font-weight:600}
        .action-login{background:#e8f5f0;color:var(--green)}
        .action-register{background:#eef3fb;color:var(--accent)}
        .action-logout{background:#fef2f2;color:var(--red)}
        .empty-state{text-align:center;padding:60px;color:var(--ink-faint)}

        .alert{padding:14px 18px;border-radius:12px;margin-bottom:24px;background:#e8f5f0;color:var(--green)}
        @media(max-width:900px){.sidebar{display:none}.main{margin-left:0}.body{padding:20px}}
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand"><div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><div class="sb-name">Smart Learning</div><div class="sb-role">Administrator</div></div>
    <ul class="sb-nav">
        <li><a href="../dashboard/admin_dashboard.php"><span class="nav-icon">⊞</span> Dashboard</a></li>
        <li><a href="manage_users.php"><span class="nav-icon">👥</span> Manage Users</a></li>
        <li><a href="manage_careers.php"><span class="nav-icon">💼</span> Manage Careers</a></li>
        <li><a href="system_logs.php" class="active"><span class="nav-icon">📋</span> System Logs</a></li>
    </ul>
    <div class="sb-footer">
        <div class="sb-user"><div class="sb-avatar"><?php echo $avatarLetter; ?></div><div><div class="sb-user-name"><?php echo htmlspecialchars($fullName); ?></div><div class="sb-user-sub">Administrator</div></div></div>
        <a href="../../logout.php" class="sb-logout">→ Sign out</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div class="topbar-breadcrumb">Admin / <span>System Logs</span></div>
        <?php if ($totalLogs > 0): ?>
            <a href="?clear=all" class="btn btn-danger" onclick="return confirm('Delete all logs? This cannot be undone.')">🗑 Clear All Logs</a>
        <?php endif; ?>
    </div>
    <div class="body">
        <?php if (isset($_GET['cleared'])): ?>
            <div class="alert">✅ System logs cleared successfully.</div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-card"><div class="stat-value"><?php echo $totalLogs; ?></div><div class="stat-label">Total Logs</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $loginCount; ?></div><div class="stat-label">Login Events</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $registerCount; ?></div><div class="stat-label">Registrations</div></div>
        </div>

        <div class="filter-bar">
            <form method="GET" class="filter-grid">
                <div class="filter-group"><label>Action</label><select name="action"><option value="">All Actions</option><?php foreach ($actions as $a): ?><option value="<?php echo $a; ?>" <?php echo $actionFilter == $a ? 'selected' : ''; ?>><?php echo ucfirst($a); ?></option><?php endforeach; ?></select></div>
                <div class="filter-group"><label>Date From</label><input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>"></div>
                <div class="filter-group"><label>Date To</label><input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>"></div>
                <div class="filter-group"><label>Search</label><input type="text" name="search" placeholder="User, IP, details..." value="<?php echo htmlspecialchars($search); ?>"></div>
                <div class="filter-group"><button type="submit" class="btn">🔍 Filter</button></div>
                <?php if ($actionFilter || $dateFrom || $dateTo || $search): ?>
                    <div class="filter-group"><a href="system_logs.php" class="btn" style="background:var(--border);color:var(--ink);text-align:center;display:flex;align-items:center;justify-content:center;">Clear</a></div>
                <?php endif; ?>
            </form>
        </div>

        <div class="log-table">
            <?php if ($totalLogs > 0): ?>
                <table>
                    <thead><tr><th>Timestamp</th><th>User</th><th>Role</th><th>Action</th><th>Details</th><th>IP Address</th></tr></thead>
                    <tbody>
                        <?php while ($log = mysqli_fetch_assoc($logs)): 
                            $actionClass = '';
                            if ($log['action'] == 'login') $actionClass = 'action-login';
                            elseif ($log['action'] == 'register') $actionClass = 'action-register';
                            elseif ($log['action'] == 'logout') $actionClass = 'action-logout';
                        ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($log['user_name']); ?></strong></td>
                                <td><?php echo ucfirst($log['user_role']); ?></td>
                                <td><span class="action-badge <?php echo $actionClass; ?>"><?php echo ucfirst($log['action']); ?></span></td>
                                <td><?php echo htmlspecialchars($log['details'] ?? '-'); ?></td>
                                <td><code><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state"><div class="icon">📋</div><p>No activity logs found.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>