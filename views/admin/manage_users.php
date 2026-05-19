<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');
if ($_SESSION['role'] !== 'admin') redirect_by_role($_SESSION['role']);

$userId = $_SESSION['user_id'];
$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
$role = $_SESSION['role'];

// Handle user actions
$message = '';
$error = '';

// Add new user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $full_name = sanitize_input($_POST['full_name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $user_role = sanitize_input($_POST['role']);
    $password = password_hash('Password123', PASSWORD_DEFAULT);
    
    $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
    if (mysqli_num_rows($check) > 0) {
        $error = 'Email already exists.';
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, phone, password, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        mysqli_stmt_bind_param($stmt, 'sssss', $full_name, $email, $phone, $password, $user_role);
        if (mysqli_stmt_execute($stmt)) {
            $message = 'User added successfully! Default password: Password123';
        } else {
            $error = 'Failed to add user.';
        }
        mysqli_stmt_close($stmt);
    }
}

// Update user
if (isset($_POST['update_user'])) {
    $updateId = (int)$_POST['user_id'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $user_role = sanitize_input($_POST['role']);
    
    $stmt = mysqli_prepare($conn, "UPDATE users SET role = ?, is_active = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'sii', $user_role, $is_active, $updateId);
    mysqli_stmt_execute($stmt);
    $message = 'User updated successfully.';
    mysqli_stmt_close($stmt);
}

// Delete user
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId != $userId) {
        mysqli_query($conn, "DELETE FROM users WHERE id = $deleteId");
        $message = 'User deleted successfully.';
    } else {
        $error = 'You cannot delete your own account.';
    }
}

// Reset password
if (isset($_GET['reset_password'])) {
    $resetId = (int)$_GET['reset_password'];
    $newPassword = password_hash('Password123', PASSWORD_DEFAULT);
    mysqli_query($conn, "UPDATE users SET password = '$newPassword' WHERE id = $resetId");
    $message = 'Password reset to: Password123';
}

// Get filter
$filterRole = $_GET['role'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM student_profiles WHERE user_id = u.id) as has_student_profile,
        (SELECT COUNT(*) FROM teacher_profiles WHERE user_id = u.id) as has_teacher_profile,
        (SELECT COUNT(*) FROM counselor_profiles WHERE user_id = u.id) as has_counselor_profile
        FROM users u WHERE 1=1";
if (!empty($filterRole)) {
    $sql .= " AND u.role = '$filterRole'";
}
if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE '%$search%' OR u.email LIKE '%$search%')";
}
$sql .= " ORDER BY u.created_at DESC";

$users = mysqli_query($conn, $sql);

// Get statistics
$totalUsers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users"))['c'];
$totalStudents = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role = 'student'"))['c'];
$totalTeachers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role = 'teacher'"))['c'];
$totalCounselors = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role = 'counselor'"))['c'];
$totalAdmins = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role = 'admin'"))['c'];
$activeUsers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE is_active = 1"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users — Smart Learning Admin</title>
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

        .stats-row{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:28px}
        .stat-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:16px;text-align:center}
        .stat-value{font-family:'DM Serif Display',serif;font-size:1.5rem}
        .stat-label{font-size:.7rem;color:var(--ink-faint);margin-top:4px}

        .action-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px}
        .filter-group{display:flex;gap:12px}
        .filter-select,.search-input{padding:10px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif}
        .btn{background:var(--accent);color:#fff;border:none;padding:10px 20px;border-radius:10px;cursor:pointer;text-decoration:none}
        .btn-outline{background:transparent;border:1.5px solid var(--accent);color:var(--accent)}
        .btn-danger{background:var(--red)}
        .btn-sm{padding:6px 12px;font-size:.75rem}

        .user-table{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);overflow-x:auto}
        table{width:100%;border-collapse:collapse}
        th,td{padding:14px 16px;text-align:left;border-bottom:1px solid var(--border)}
        th{background:var(--accent-lt);font-weight:600}
        .status-badge{display:inline-block;padding:4px 10px;border-radius:20px;font-size:.7rem;font-weight:600}
        .status-active{background:#e8f5f0;color:var(--green)}
        .status-inactive{background:#fef2f2;color:var(--red)}
        .role-badge{background:var(--accent-dim);padding:4px 10px;border-radius:20px;font-size:.7rem}

        .modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center}
        .modal.show{display:flex}
        .modal-content{background:var(--white);border-radius:var(--radius);padding:28px;max-width:500px;width:90%}
        .modal-header{display:flex;justify-content:space-between;margin-bottom:20px}
        .modal-close{background:none;border:none;font-size:1.5rem;cursor:pointer}
        .form-group{margin-bottom:16px}
        .form-group label{display:block;margin-bottom:6px;font-weight:600}
        .form-group input,.form-group select{width:100%;padding:10px;border:1.5px solid var(--border);border-radius:8px}

        .alert{padding:14px 18px;border-radius:12px;margin-bottom:24px}
        .alert-success{background:#e8f5f0;border:1px solid #c5e0d4;color:var(--green)}
        .alert-danger{background:#fef2f2;border:1px solid #fecaca;color:var(--red)}

        @media(max-width:900px){.sidebar{display:none}.main{margin-left:0}.body{padding:20px}.stats-row{grid-template-columns:repeat(3,1fr)}}
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand"><div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><div class="sb-name">Smart Learning</div><div class="sb-role">Administrator</div></div>
    <div class="sb-section">Admin</div>
    <ul class="sb-nav">
        <li><a href="../dashboard/admin_dashboard.php"><span class="nav-icon">⊞</span> Dashboard</a></li>
        <li><a href="manage_users.php" class="active"><span class="nav-icon">👥</span> Manage Users</a></li>
        <li><a href="manage_careers.php"><span class="nav-icon">💼</span> Manage Careers</a></li>
        <li><a href="system_logs.php"><span class="nav-icon">📋</span> System Logs</a></li>
    </ul>
    <div class="sb-footer">
        <div class="sb-user"><div class="sb-avatar"><?php echo $avatarLetter; ?></div><div><div class="sb-user-name"><?php echo htmlspecialchars($fullName); ?></div><div class="sb-user-sub">Administrator</div></div></div>
        <a href="../../logout.php" class="sb-logout">→ Sign out</a>
    </div>
</aside>

<div class="main">
    <div class="topbar"><div class="topbar-breadcrumb">Admin / <span>Manage Users</span></div></div>
    <div class="body">
        <?php if ($message): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger">⚠ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="stats-row">
            <div class="stat-card"><div class="stat-value"><?php echo $totalUsers; ?></div><div class="stat-label">Total Users</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $totalStudents; ?></div><div class="stat-label">Students</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $totalTeachers; ?></div><div class="stat-label">Teachers</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $totalCounselors; ?></div><div class="stat-label">Counselors</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $totalAdmins; ?></div><div class="stat-label">Admins</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $activeUsers; ?></div><div class="stat-label">Active</div></div>
        </div>

        <div class="action-bar">
            <div class="filter-group">
                <select class="filter-select" onchange="window.location.href='?role='+this.value+'&search=<?php echo urlencode($search); ?>'">
                    <option value="">All Roles</option>
                    <option value="student" <?php echo $filterRole === 'student' ? 'selected' : ''; ?>>Students</option>
                    <option value="teacher" <?php echo $filterRole === 'teacher' ? 'selected' : ''; ?>>Teachers</option>
                    <option value="counselor" <?php echo $filterRole === 'counselor' ? 'selected' : ''; ?>>Counselors</option>
                    <option value="admin" <?php echo $filterRole === 'admin' ? 'selected' : ''; ?>>Admins</option>
                </select>
                <form method="GET" style="display:inline;">
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($filterRole); ?>">
                    <input type="text" name="search" class="search-input" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-sm">🔍 Search</button>
                </form>
            </div>
            <button class="btn" onclick="openAddModal()">+ Add New User</button>
        </div>

        <div class="user-table">
            <table>
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php while ($user = mysqli_fetch_assoc($users)): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><span class="role-badge"><?php echo ucfirst($user['role']); ?></span></td>
                            <td><span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                            <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <button class="btn-sm btn-outline" onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['role']); ?>', <?php echo $user['is_active']; ?>)">✏ Edit</button>
                                <a href="?reset_password=<?php echo $user['id']; ?>" class="btn-sm btn-outline" onclick="return confirm('Reset password for <?php echo htmlspecialchars($user['full_name']); ?>?')">🔑 Reset</a>
                                <?php if ($user['id'] != $userId): ?>
                                    <a href="?delete=<?php echo $user['id']; ?>" class="btn-sm btn-danger" onclick="return confirm('Delete <?php echo htmlspecialchars($user['full_name']); ?>? This cannot be undone.')">🗑 Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Add New User</h3><button class="modal-close" onclick="closeModal('addModal')">×</button></div>
        <form method="POST">
            <div class="form-group"><label>Full Name</label><input type="text" name="full_name" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
            <div class="form-group"><label>Phone</label><input type="text" name="phone"></div>
            <div class="form-group"><label>Role</label><select name="role" required><option value="student">Student</option><option value="teacher">Teacher</option><option value="counselor">Counselor</option><option value="admin">Admin</option></select></div>
            <button type="submit" name="add_user" class="btn" style="width:100%">Create User</button>
        </form>
        <p style="margin-top:12px; font-size:.75rem; color:var(--ink-faint);">Default password: <strong>Password123</strong></p>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Edit User</h3><button class="modal-close" onclick="closeModal('editModal')">×</button></div>
        <form method="POST">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="form-group"><label>Role</label><select name="role" id="edit_role"><option value="student">Student</option><option value="teacher">Teacher</option><option value="counselor">Counselor</option><option value="admin">Admin</option></select></div>
            <div class="form-group"><label><input type="checkbox" name="is_active" id="edit_is_active"> Active Account</label></div>
            <button type="submit" name="update_user" class="btn" style="width:100%">Update User</button>
        </form>
    </div>
</div>

<script>
    function openAddModal() { document.getElementById('addModal').classList.add('show'); }
    function openEditModal(id, role, isActive) {
        document.getElementById('edit_user_id').value = id;
        document.getElementById('edit_role').value = role;
        document.getElementById('edit_is_active').checked = isActive == 1;
        document.getElementById('editModal').classList.add('show');
    }
    function closeModal(modalId) { document.getElementById(modalId).classList.remove('show'); }
    window.onclick = function(e) {
        if (e.target.classList.contains('modal')) e.target.classList.remove('show');
    }
</script>
</body>
</html>