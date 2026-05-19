<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];
$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));

// Get filter parameters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$subject = $_GET['subject'] ?? '';

// Build query
$sql = "SELECT m.*, u.full_name as uploader_name 
        FROM materials m 
        JOIN users u ON m.uploaded_by = u.id 
        WHERE 1=1";
$params = [];
$types = "";

if (!empty($category)) {
    $sql .= " AND m.category = ?";
    $params[] = $category;
    $types .= "s";
}
if (!empty($subject)) {
    $sql .= " AND m.subject = ?";
    $params[] = $subject;
    $types .= "s";
}
if (!empty($search)) {
    $sql .= " AND (m.title LIKE ? OR m.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

$sql .= " ORDER BY m.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$materials = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get unique categories for filter
$catResult = mysqli_query($conn, "SELECT DISTINCT category FROM materials WHERE category IS NOT NULL ORDER BY category");
$categories = [];
while ($row = mysqli_fetch_assoc($catResult)) {
    $categories[] = $row['category'];
}

// Get unique subjects for filter
$subResult = mysqli_query($conn, "SELECT DISTINCT subject FROM materials WHERE subject IS NOT NULL ORDER BY subject");
$subjects = [];
while ($row = mysqli_fetch_assoc($subResult)) {
    $subjects[] = $row['subject'];
}

$greeting = date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Materials — Smart Learning</title>
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
            --orange:#c8622a;--green:#1f7a5c;--amber:#b87c10;--red:#c0392b;
        }
        html,body{min-height:100vh;font-family:'DM Sans',sans-serif;background:var(--canvas);color:var(--ink)}

        /* Sidebar */
        .sidebar{
            position:fixed;top:0;left:0;height:100vh;width:var(--sidebar);
            background:#1a2235;display:flex;flex-direction:column;z-index:100;
            overflow-y:auto;
        }
        .sb-brand{
            padding:28px 24px 24px;
            background:linear-gradient(135deg,#1e3a5f 0%,#1a2235 100%);
            border-bottom:1px solid rgba(255,255,255,.06);
        }
        .sb-mark{
            width:40px;height:40px;background:var(--accent);border-radius:10px;
            display:flex;align-items:center;justify-content:center;margin-bottom:14px;
        }
        .sb-mark svg{width:22px;height:22px;fill:#fff}
        .sb-name{font-family:'DM Serif Display',serif;font-size:1.15rem;color:#fff;line-height:1.1}
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

        /* Main */
        .main{margin-left:var(--sidebar);min-height:100vh}
        .topbar{
            background:var(--white);border-bottom:1px solid var(--border);
            padding:16px 36px;display:flex;align-items:center;gap:16px;
            position:sticky;top:0;z-index:50;
        }
        .topbar-breadcrumb{font-size:.8rem;color:var(--ink-faint)}
        .topbar-breadcrumb span{color:var(--ink);font-weight:600}
        .topbar-right{margin-left:auto;display:flex;align-items:center;gap:12px}
        .tb-btn{
            display:flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9px;
            font-size:.82rem;font-weight:600;text-decoration:none;transition:background .2s;
        }
        .tb-btn-primary{background:var(--accent);color:#fff}
        .tb-btn-primary:hover{background:#1a4f8f}
        .tb-btn-ghost{background:var(--canvas);color:var(--ink-soft);border:1.5px solid var(--border)}
        .tb-btn-ghost:hover{border-color:var(--accent);color:var(--accent)}

        .body{padding:32px 36px}

        /* Page header */
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px}
        .page-title{font-family:'DM Serif Display',serif;font-size:1.8rem;letter-spacing:-.02em}
        .page-sub{font-size:.85rem;color:var(--ink-faint);margin-top:4px}

        /* Filters */
        .filters-card{
            background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);
            padding:20px 24px;margin-bottom:28px;
        }
        .filter-row{display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end}
        .filter-group{flex:1;min-width:180px}
        .filter-group label{font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-faint);margin-bottom:6px;display:block}
        .filter-group input,.filter-group select{
            width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:10px;
            font-family:'DM Sans',sans-serif;font-size:.85rem;background:var(--canvas);
            transition:border-color .2s;
        }
        .filter-group input:focus,.filter-group select:focus{outline:none;border-color:var(--accent)}
        .filter-btn{padding:10px 20px;background:var(--accent);color:#fff;border:none;border-radius:10px;cursor:pointer;font-weight:600}
        .filter-reset{background:var(--canvas);color:var(--ink-soft);border:1.5px solid var(--border)}

        /* Material grid */
        .material-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px}
        .material-card{
            background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);
            transition:transform .2s,box-shadow .2s;overflow:hidden;
        }
        .material-card:hover{transform:translateY(-4px);box-shadow:0 12px 24px rgba(0,0,0,.08)}
        .material-header{
            padding:20px 20px 12px;border-bottom:1.5px solid var(--border);
            display:flex;align-items:center;gap:14px;
        }
        .material-icon{
            width:48px;height:48px;background:var(--accent-dim);border-radius:14px;
            display:flex;align-items:center;justify-content:center;font-size:1.5rem;
        }
        .material-header h3{font-size:1.1rem;font-weight:600;margin-bottom:4px}
        .material-meta{font-size:.72rem;color:var(--ink-faint)}
        .material-body{padding:16px 20px}
        .material-description{font-size:.85rem;color:var(--ink-soft);line-height:1.5;margin-bottom:12px}
        .material-tags{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
        .tag{
            background:var(--canvas);padding:4px 10px;border-radius:20px;
            font-size:.7rem;font-weight:500;color:var(--ink-soft);
        }
        .material-footer{
            padding:16px 20px;background:var(--canvas);border-top:1.5px solid var(--border);
            display:flex;justify-content:space-between;align-items:center;
        }
        .material-stats{display:flex;gap:16px;font-size:.75rem;color:var(--ink-faint)}
        .btn-view{
            padding:8px 16px;background:var(--accent);color:#fff;border-radius:8px;
            text-decoration:none;font-size:.78rem;font-weight:600;transition:background .2s;
        }
        .btn-view:hover{background:#1a4f8f}

        .empty-state{text-align:center;padding:60px 20px;color:var(--ink-faint)}
        .empty-state .icon{font-size:3rem;margin-bottom:16px}
        .empty-state p{font-size:.9rem}
        .empty-state a{color:var(--accent);text-decoration:none;font-weight:600}

        @media(max-width:900px){
            .sidebar{display:none}.main{margin-left:0}
            .body{padding:20px}
            .material-grid{grid-template-columns:1fr}
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sb-brand">
        <div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div>
        <div class="sb-name">Smart Learning</div>
        <div class="sb-role">Learning Portal</div>
    </div>
    <div class="sb-section">Browse</div>
    <ul class="sb-nav">
        <li><a href="../dashboard/<?php echo $role; ?>_dashboard.php"><span class="nav-icon">⊞</span> Dashboard</a></li>
        <li><a href="materials_list.php" class="active"><span class="nav-icon">📚</span> Materials</a></li>
        <li><a href="../quiz/quizzes.php"><span class="nav-icon">📝</span> Quizzes</a></li>
    </ul>
    <?php if ($role === 'teacher'): ?>
    <div class="sb-section">Teaching</div>
    <ul class="sb-nav">
        <li><a href="upload_material.php"><span class="nav-icon">⬆</span> Upload Material</a></li>
    </ul>
    <?php endif; ?>
    <div class="sb-footer">
        <div class="sb-user">
            <div class="sb-avatar"><?php echo $avatarLetter; ?></div>
            <div>
                <div class="sb-user-name"><?php echo htmlspecialchars($fullName); ?></div>
                <div class="sb-user-sub"><?php echo ucfirst($role); ?></div>
            </div>
        </div>
        <a href="../../logout.php" class="sb-logout">→ Sign out</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div class="topbar-breadcrumb">Learning / <span>Materials</span></div>
        <div class="topbar-right">
            <?php if ($role === 'teacher'): ?>
                <a href="upload_material.php" class="tb-btn tb-btn-primary">↑ Upload New Material</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="body">
        <div class="page-header">
            <div>
                <h1 class="page-title">Learning Materials</h1>
                <p class="page-sub">Browse educational resources uploaded by teachers</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>🔍 Search</label>
                        <input type="text" name="search" placeholder="Search by title..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label>📁 Category</label>
                        <select name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($category === $cat) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>📖 Subject</label>
                        <select name="subject">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects as $sub): ?>
                                <option value="<?php echo htmlspecialchars($sub); ?>" <?php echo ($subject === $sub) ? 'selected' : ''; ?>><?php echo htmlspecialchars($sub); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="filter-btn">Apply Filters</button>
                    <a href="materials_list.php" class="filter-btn filter-reset" style="text-decoration:none;">Reset</a>
                </div>
            </form>
        </div>

        <!-- Materials Grid -->
        <?php if (!empty($materials)): ?>
            <div class="material-grid">
                <?php foreach ($materials as $material): 
                    $typeIcon = array('pdf'=>'📄','doc'=>'📝','docx'=>'📝','ppt'=>'📊','pptx'=>'📊','mp4'=>'🎬','link'=>'🔗');
                    $icon = isset($typeIcon[$material['type']]) ? $typeIcon[$material['type']] : '📁';
                    $fileExt = strtoupper($material['type']);
                ?>
                    <div class="material-card">
                        <div class="material-header">
                            <div class="material-icon"><?php echo $icon; ?></div>
                            <div>
                                <h3><?php echo htmlspecialchars($material['title']); ?></h3>
                                <div class="material-meta"><?php echo $fileExt; ?> · <?php echo date('d M Y', strtotime($material['created_at'])); ?></div>
                            </div>
                        </div>
                        <div class="material-body">
                            <p class="material-description"><?php echo htmlspecialchars(substr($material['description'] ?? 'No description', 0, 100)); ?>...</p>
                            <div class="material-tags">
                                <?php if (!empty($material['category'])): ?>
                                    <span class="tag">📁 <?php echo htmlspecialchars($material['category']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($material['subject'])): ?>
                                    <span class="tag">📖 <?php echo htmlspecialchars($material['subject']); ?></span>
                                <?php endif; ?>
                                <span class="tag">👤 <?php echo htmlspecialchars($material['uploader_name']); ?></span>
                            </div>
                        </div>
                        <div class="material-footer">
                            <div class="material-stats">
                                <span>👁️ <?php echo (int)($material['views'] ?? 0); ?> views</span>
                            </div>
                            <a href="material_detail.php?id=<?php echo $material['id']; ?>" class="btn-view">View →</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">📚</div>
                <p>No learning materials found.</p>
                <?php if ($role === 'teacher'): ?>
                    <p><a href="upload_material.php">Upload the first material →</a></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>