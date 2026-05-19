<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');
if ($_SESSION['role'] !== 'admin') redirect_by_role($_SESSION['role']);

$userId = $_SESSION['user_id'];
$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
$role = $_SESSION['role'];
$message = '';
$error = '';

// Handle career actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add/Update Career
    if (isset($_POST['save_career'])) {
        $career_id = (int)($_POST['career_id'] ?? 0);
        $career_name = sanitize_input($_POST['career_name']);
        $description = sanitize_input($_POST['description']);
        $required_skills = sanitize_input($_POST['required_skills']);
        $salary_range = sanitize_input($_POST['salary_range']);
        $growth_rate = sanitize_input($_POST['growth_rate']);
        $education_required = sanitize_input($_POST['education_required']);
        $holland_codes = sanitize_input($_POST['holland_codes']);
        $category = sanitize_input($_POST['category']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($career_id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE career_paths SET career_name=?, description=?, required_skills=?, salary_range=?, growth_rate=?, education_required=?, holland_codes=?, category=?, is_active=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'ssssssssii', $career_name, $description, $required_skills, $salary_range, $growth_rate, $education_required, $holland_codes, $category, $is_active, $career_id);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO career_paths (career_name, description, required_skills, salary_range, growth_rate, education_required, holland_codes, category, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssssssssi', $career_name, $description, $required_skills, $salary_range, $growth_rate, $education_required, $holland_codes, $category, $is_active);
        }
        if (mysqli_stmt_execute($stmt)) {
            $message = 'Career saved successfully!';
        } else {
            $error = 'Failed to save career.';
        }
        mysqli_stmt_close($stmt);
    }
    
    // Delete Career
    if (isset($_POST['delete_career'])) {
        $deleteId = (int)$_POST['career_id'];
        mysqli_query($conn, "DELETE FROM career_paths WHERE id = $deleteId");
        $message = 'Career deleted successfully.';
    }
}

// Get all careers
$careers = mysqli_query($conn, "SELECT * FROM career_paths ORDER BY id");
$editCareer = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editCareer = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM career_paths WHERE id = $editId"));
}

// Get statistics
$totalCareers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM career_paths"))['c'];
$activeCareers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM career_paths WHERE is_active = 1"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Careers — Smart Learning Admin</title>
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
            --green:#1f7a5c;--red:#c0392b;
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
        .topbar{background:var(--white);border-bottom:1px solid var(--border);padding:16px 36px;}
        .topbar-breadcrumb{font-size:.8rem;color:var(--ink-faint)}
        .topbar-breadcrumb span{color:var(--ink);font-weight:600}
        .body{padding:32px 36px}

        .stats-row{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:28px}
        .stat-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:20px;text-align:center}
        .stat-value{font-family:'DM Serif Display',serif;font-size:2rem}
        .stat-label{font-size:.7rem;color:var(--ink-faint);margin-top:4px}

        .form-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:28px;margin-bottom:28px}
        .form-header{margin-bottom:20px}
        .form-header h2{font-family:'DM Serif Display',serif}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        .form-group{margin-bottom:16px}
        .form-group label{display:block;margin-bottom:6px;font-weight:600;font-size:.8rem}
        .form-group input,.form-group select,.form-group textarea{width:100%;padding:10px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif}
        .form-group textarea{resize:vertical;min-height:80px}
        .full-width{grid-column:span 2}
        .btn{background:var(--accent);color:#fff;border:none;padding:12px 24px;border-radius:10px;cursor:pointer;font-weight:600}
        .btn-danger{background:var(--red)}

        .career-table{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);overflow-x:auto}
        table{width:100%;border-collapse:collapse}
        th,td{padding:14px 16px;text-align:left;border-bottom:1px solid var(--border)}
        th{background:var(--accent-lt);font-weight:600}
        .status-badge{display:inline-block;padding:4px 10px;border-radius:20px;font-size:.7rem}
        .status-active{background:#e8f5f0;color:var(--green)}
        .status-inactive{background:#fef2f2;color:var(--red)}
        .alert{padding:14px 18px;border-radius:12px;margin-bottom:24px}
        .alert-success{background:#e8f5f0;color:var(--green)}
        .alert-danger{background:#fef2f2;color:var(--red)}

        @media(max-width:900px){.sidebar{display:none}.main{margin-left:0}.body{padding:20px}.form-grid{grid-template-columns:1fr}.full-width{grid-column:span 1}}
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand"><div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><div class="sb-name">Smart Learning</div><div class="sb-role">Administrator</div></div>
    <ul class="sb-nav">
        <li><a href="../dashboard/admin_dashboard.php"><span class="nav-icon">⊞</span> Dashboard</a></li>
        <li><a href="manage_users.php"><span class="nav-icon">👥</span> Manage Users</a></li>
        <li><a href="manage_careers.php" class="active"><span class="nav-icon">💼</span> Manage Careers</a></li>
        <li><a href="system_logs.php"><span class="nav-icon">📋</span> System Logs</a></li>
    </ul>
    <div class="sb-footer">
        <div class="sb-user"><div class="sb-avatar"><?php echo $avatarLetter; ?></div><div><div class="sb-user-name"><?php echo htmlspecialchars($fullName); ?></div><div class="sb-user-sub">Administrator</div></div></div>
        <a href="../../logout.php" class="sb-logout">→ Sign out</a>
    </div>
</aside>

<div class="main">
    <div class="topbar"><div class="topbar-breadcrumb">Admin / <span>Manage Careers</span></div></div>
    <div class="body">
        <?php if ($message): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger">⚠ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="stats-row">
            <div class="stat-card"><div class="stat-value"><?php echo $totalCareers; ?></div><div class="stat-label">Total Careers</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $activeCareers; ?></div><div class="stat-label">Active Careers</div></div>
        </div>

        <!-- Add/Edit Career Form -->
        <div class="form-card">
            <div class="form-header"><h2><?php echo $editCareer ? 'Edit Career' : 'Add New Career'; ?></h2></div>
            <form method="POST">
                <input type="hidden" name="career_id" value="<?php echo $editCareer['id'] ?? 0; ?>">
                <div class="form-grid">
                    <div class="form-group"><label>Career Name *</label><input type="text" name="career_name" value="<?php echo htmlspecialchars($editCareer['career_name'] ?? ''); ?>" required></div>
                    <div class="form-group"><label>Category</label><input type="text" name="category" value="<?php echo htmlspecialchars($editCareer['category'] ?? ''); ?>" placeholder="e.g., Technology, Healthcare"></div>
                    <div class="form-group full-width"><label>Description *</label><textarea name="description" required><?php echo htmlspecialchars($editCareer['description'] ?? ''); ?></textarea></div>
                    <div class="form-group"><label>Required Skills</label><input type="text" name="required_skills" value="<?php echo htmlspecialchars($editCareer['required_skills'] ?? ''); ?>" placeholder="e.g., Python, Problem Solving"></div>
                    <div class="form-group"><label>Salary Range</label><input type="text" name="salary_range" value="<?php echo htmlspecialchars($editCareer['salary_range'] ?? ''); ?>" placeholder="e.g., $60k-$90k"></div>
                    <div class="form-group"><label>Growth Rate</label><input type="text" name="growth_rate" value="<?php echo htmlspecialchars($editCareer['growth_rate'] ?? ''); ?>" placeholder="e.g., 15% (High demand)"></div>
                    <div class="form-group"><label>Education Required</label><input type="text" name="education_required" value="<?php echo htmlspecialchars($editCareer['education_required'] ?? ''); ?>" placeholder="e.g., Bachelor's Degree"></div>
                    <div class="form-group"><label>Holland Codes</label><input type="text" name="holland_codes" value="<?php echo htmlspecialchars($editCareer['holland_codes'] ?? ''); ?>" placeholder="e.g., IRC, IRE"></div>
                    <div class="form-group"><label><input type="checkbox" name="is_active" value="1" <?php echo (($editCareer['is_active'] ?? 1) == 1) ? 'checked' : ''; ?>> Active (visible to students)</label></div>
                </div>
                <div style="display:flex; gap:12px; margin-top:20px;">
                    <button type="submit" name="save_career" class="btn">💾 Save Career</button>
                    <?php if ($editCareer): ?>
                        <a href="manage_careers.php" class="btn" style="background:var(--border);color:var(--ink);">Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Careers List -->
        <div class="career-table">
            <h3 style="padding:16px 16px 0;">📋 All Career Paths</h3>
            <table>
                <thead><tr><th>ID</th><th>Career Name</th><th>Category</th><th>Salary Range</th><th>Growth Rate</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php while ($career = mysqli_fetch_assoc($careers)): ?>
                        <tr>
                            <td><?php echo $career['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($career['career_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($career['category'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($career['salary_range'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($career['growth_rate'] ?? '-'); ?></td>
                            <td><span class="status-badge <?php echo $career['is_active'] ? 'status-active' : 'status-inactive'; ?>"><?php echo $career['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                            <td>
                                <a href="?edit=<?php echo $career['id']; ?>" class="btn" style="padding:6px 12px; font-size:.75rem;">✏ Edit</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this career?')">
                                    <input type="hidden" name="career_id" value="<?php echo $career['id']; ?>">
                                    <button type="submit" name="delete_career" class="btn" style="background:var(--red); padding:6px 12px; font-size:.75rem;">🗑 Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>