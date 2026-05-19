<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');

$userId = $_SESSION['user_id'];
$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
$role = $_SESSION['role'];

// Get careers to compare
$compareIds = $_GET['careers'] ?? [];
if (!is_array($compareIds)) $compareIds = [$compareIds];
$compareIds = array_map('intval', $compareIds);

// Add new career to compare
if (isset($_GET['add']) && is_numeric($_GET['add'])) {
    $newId = (int)$_GET['add'];
    if (!in_array($newId, $compareIds) && count($compareIds) < 4) {
        $compareIds[] = $newId;
    }
    header("Location: compare_careers.php?" . http_build_query(['careers' => $compareIds]));
    exit();
}

// Remove from comparison
if (isset($_GET['remove'])) {
    $removeId = (int)$_GET['remove'];
    $compareIds = array_filter($compareIds, fn($id) => $id != $removeId);
    header("Location: compare_careers.php?" . http_build_query(['careers' => $compareIds]));
    exit();
}

// Clear all
if (isset($_GET['clear'])) {
    $compareIds = [];
    header("Location: compare_careers.php");
    exit();
}

// Fetch career data
$careers = [];
if (!empty($compareIds)) {
    $ids = implode(',', $compareIds);
    $result = mysqli_query($conn, "SELECT * FROM career_paths WHERE id IN ($ids)");
    while ($row = mysqli_fetch_assoc($result)) {
        $careers[$row['id']] = $row;
    }
}

// Get all careers for dropdown
$allCareers = mysqli_query($conn, "SELECT id, career_name FROM career_paths ORDER BY career_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compare Careers — Smart Learning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --ink:#0f1117;--ink-soft:#4a4d5a;--ink-faint:#9396a3;
            --canvas:#f5f7f9;--white:#fff;
            --accent:#2563a8;--accent-lt:#eef3fb;
            --border:#e4e8ed;--radius:14px;
            --sidebar:264px;
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
        .sb-nav{list-style:none;padding:2px 10px}
        .sb-nav li a{display:flex;align-items:center;gap:11px;padding:10px 14px;border-radius:9px;font-size:.875rem;color:rgba(255,255,255,.5);text-decoration:none;}
        .sb-nav li a:hover{background:rgba(255,255,255,.06);color:#fff}
        .sb-nav li a.active{background:var(--accent);color:#fff}
        .sb-footer{margin-top:auto;padding:16px 10px;border-top:1px solid rgba(255,255,255,.06)}
        .sb-user{display:flex;align-items:center;gap:10px;padding:10px 14px;}
        .sb-avatar{width:36px;height:36px;border-radius:10px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;}
        .sb-user-name{font-size:.85rem;font-weight:600;color:#fff}
        .sb-user-sub{font-size:.7rem;color:rgba(255,255,255,.3)}
        .sb-logout{color:rgba(255,255,255,.3);text-decoration:none;font-size:.8rem;padding:8px 14px;display:block;margin-top:8px}
        
        .main{margin-left:var(--sidebar);min-height:100vh}
        .topbar{background:var(--white);border-bottom:1px solid var(--border);padding:16px 36px;}
        .topbar-breadcrumb{font-size:.8rem;color:var(--ink-faint)}
        .topbar-breadcrumb span{color:var(--ink);font-weight:600}
        .body{padding:32px 36px}
        
        .add-bar{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:20px;margin-bottom:24px;display:flex;gap:16px;align-items:center;flex-wrap:wrap}
        .add-bar select{flex:1;padding:12px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif}
        .btn{display:inline-block;padding:12px 24px;background:var(--accent);color:#fff;border-radius:10px;text-decoration:none;font-weight:600;border:none;cursor:pointer}
        .btn-outline{background:transparent;border:1.5px solid var(--accent);color:var(--accent)}
        .btn-sm{padding:8px 16px;font-size:.8rem}
        
        .comparison-table{width:100%;background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);overflow-x:auto}
        table{width:100%;border-collapse:collapse}
        th,td{padding:16px 20px;text-align:left;border-bottom:1px solid var(--border);vertical-align:top}
        th{background:var(--accent-lt);font-weight:600}
        .career-header{text-align:center}
        .career-title{font-size:1.1rem;font-weight:700;margin-bottom:8px}
        .remove-link{color:var(--red);text-decoration:none;font-size:.75rem}
        .empty-cell{text-align:center;color:var(--ink-faint);padding:40px}
        
        @media(max-width:900px){.sidebar{display:none}.main{margin-left:0}.body{padding:20px}}
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand"><div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><div class="sb-name">Smart Learning</div><div class="sb-role">Compare</div></div>
    <ul class="sb-nav"><li><a href="../dashboard/<?php echo $role; ?>_dashboard.php"><span class="nav-icon">⊞</span> Dashboard</a></li><li><a href="recommendations.php"><span class="nav-icon">⭐</span> Recommendations</a></li><li><a href="compare_careers.php" class="active"><span class="nav-icon">🔄</span> Compare Careers</a></li></ul>
    <div class="sb-footer"><div class="sb-user"><div class="sb-avatar"><?php echo $avatarLetter; ?></div><div><div class="sb-user-name"><?php echo htmlspecialchars($fullName); ?></div><div class="sb-user-sub"><?php echo ucfirst($role); ?></div></div></div><a href="../../logout.php" class="sb-logout">→ Sign out</a></div>
</aside>

<div class="main">
    <div class="topbar"><div class="topbar-breadcrumb">Career / <span>Compare Careers</span></div></div>
    <div class="body">
        <h1 style="font-family:'DM Serif Display',serif; margin-bottom:16px;">Compare Careers</h1>
        <p style="margin-bottom:24px;">Select up to 4 careers to compare side by side</p>
        
        <div class="add-bar">
            <form method="GET" action="" style="display:flex; gap:12px; width:100%;">
                <select name="add">
                    <option value="">— Add another career —</option>
                    <?php while ($c = mysqli_fetch_assoc($allCareers)): 
                        if (!in_array($c['id'], $compareIds)): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['career_name']); ?></option>
                    <?php endif; endwhile; ?>
                </select>
                <button type="submit" class="btn btn-sm">+ Add to Compare</button>
            </form>
            <?php if (!empty($compareIds)): ?>
                <a href="?clear=1" class="btn-outline btn-sm" style="padding:10px 20px; text-decoration:none;">Clear All</a>
            <?php endif; ?>
        </div>
        
        <?php if (empty($careers)): ?>
            <div class="empty-cell">No careers selected for comparison. Use the dropdown above to add careers.</div>
        <?php else: ?>
            <div class="comparison-table">
                <table>
                    <thead>
                        <tr>
                            <th style="width:180px;">Criteria</th>
                            <?php foreach ($careers as $career): ?>
                                <th class="career-header">
                                    <div class="career-title"><?php echo htmlspecialchars($career['career_name']); ?></div>
                                    <a href="?remove=<?php echo $career['id']; ?>&<?php echo http_build_query(['careers' => array_diff($compareIds, [$career['id']])]); ?>" class="remove-link">Remove</a>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>📚 Required Skills</strong></td><?php foreach ($careers as $career): ?><td><?php echo htmlspecialchars($career['required_skills']); ?></td><?php endforeach; ?></tr>
                        <tr><td><strong>💰 Salary Range</strong></td><?php foreach ($careers as $career): ?><td><?php echo htmlspecialchars($career['salary_range']); ?></td><?php endforeach; ?></tr>
                        <tr><td><strong>📈 Job Growth Rate</strong></td><?php foreach ($careers as $career): ?><td><?php echo htmlspecialchars($career['growth_rate']); ?></td><?php endforeach; ?></tr>
                        <tr><td><strong>🎓 Education Required</strong></td><?php foreach ($careers as $career): ?><td><?php echo htmlspecialchars($career['education_required']); ?></td><?php endforeach; ?></tr>
                        <tr><td><strong>🧠 Holland Code</strong></td><?php foreach ($careers as $career): ?><td><?php echo htmlspecialchars($career['holland_codes']); ?></td><?php endforeach; ?></tr>
                        <tr><td><strong>📖 Description</strong></td><?php foreach ($careers as $career): ?><td><small><?php echo htmlspecialchars(substr($career['description'], 0, 150)); ?>...</small></td><?php endforeach; ?></tr>
                        <tr>
                            <td><strong>🔗 Details</strong></td>
                            <?php foreach ($careers as $career): ?>
                                <td><a href="career_detail.php?id=<?php echo $career['id']; ?>" class="btn-outline btn-sm" style="text-decoration:none; display:inline-block;">View Full Profile →</a></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>