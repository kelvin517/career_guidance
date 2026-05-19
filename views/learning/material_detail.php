<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');

$materialId = (int)($_GET['id'] ?? 0);
if ($materialId === 0) redirect('materials_list.php');

// Increment view count
mysqli_query($conn, "UPDATE materials SET views = views + 1 WHERE id = $materialId");

// Fetch material details
$stmt = mysqli_prepare($conn, "
    SELECT m.*, u.full_name as uploader_name, u.role as uploader_role
    FROM materials m
    JOIN users u ON m.uploaded_by = u.id
    WHERE m.id = ?
");
mysqli_stmt_bind_param($stmt, 'i', $materialId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$material = mysqli_fetch_assoc($result);

if (!$material) redirect('materials_list.php');

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];
$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
$canEdit = ($role === 'teacher' && $material['uploaded_by'] == $userId) || $role === 'admin';

$typeIcon = ['pdf'=>'📄','doc'=>'📝','docx'=>'📝','ppt'=>'📊','pptx'=>'📊','mp4'=>'🎬','link'=>'🔗'];
$icon = $typeIcon[$material['type']] ?? '📁';
$fileExt = strtoupper($material['type']);

// Get related materials (same subject or category)
$relatedSql = "SELECT id, title, type, views FROM materials WHERE (subject = ? OR category = ?) AND id != ? LIMIT 4";
$stmt2 = mysqli_prepare($conn, $relatedSql);
mysqli_stmt_bind_param($stmt2, 'ssi', $material['subject'], $material['category'], $materialId);
mysqli_stmt_execute($stmt2);
$relatedResult = mysqli_stmt_get_result($stmt2);
$relatedMaterials = mysqli_fetch_all($relatedResult, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($material['title']) ?> — Smart Learning</title>
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
            --orange:#c8622a;--green:#1f7a5c;
        }
        html,body{min-height:100vh;font-family:'DM Sans',sans-serif;background:var(--canvas);color:var(--ink)}

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

        .main{margin-left:var(--sidebar);min-height:100vh}
        .topbar{
            background:var(--white);border-bottom:1px solid var(--border);
            padding:16px 36px;display:flex;align-items:center;gap:16px;
            position:sticky;top:0;z-index:50;
        }
        .topbar-breadcrumb{font-size:.8rem;color:var(--ink-faint)}
        .topbar-breadcrumb a{color:var(--accent);text-decoration:none}
        .topbar-breadcrumb span{color:var(--ink);font-weight:600}
        .body{padding:32px 36px}

        .material-container{display:grid;grid-template-columns:1fr 320px;gap:28px}
        .material-main{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);overflow:hidden}
        .material-header{padding:32px 36px;border-bottom:1.5px solid var(--border);display:flex;gap:20px}
        .material-icon{width:80px;height:80px;background:var(--accent-dim);border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:2.5rem}
        .material-title h1{font-family:'DM Serif Display',serif;font-size:1.8rem;margin-bottom:8px}
        .material-meta{display:flex;gap:16px;margin-top:12px;flex-wrap:wrap}
        .meta-item{display:flex;align-items:center;gap:6px;font-size:.8rem;color:var(--ink-faint)}
        .material-content{padding:32px 36px}
        .material-description{font-size:1rem;line-height:1.6;color:var(--ink-soft);margin-bottom:24px}
        .material-actions{display:flex;gap:12px;margin-top:24px}
        .btn-download,.btn-edit{padding:12px 24px;border-radius:10px;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:8px}
        .btn-download{background:var(--accent);color:#fff}
        .btn-download:hover{background:#1a4f8f}
        .btn-edit{background:var(--canvas);border:1.5px solid var(--border);color:var(--ink-soft)}

        .sidebar-info{display:flex;flex-direction:column;gap:20px}
        .info-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:20px}
        .info-card h3{font-size:1rem;margin-bottom:16px;padding-bottom:8px;border-bottom:1.5px solid var(--border)}
        .info-row{display:flex;justify-content:space-between;padding:8px 0;font-size:.85rem}
        .info-label{font-weight:600;color:var(--ink-soft)}
        .info-value{color:var(--ink-faint)}
        .related-grid{display:flex;flex-direction:column;gap:12px}
        .related-item{display:flex;align-items:center;gap:12px;padding:12px;background:var(--canvas);border-radius:10px;text-decoration:none;transition:background .2s}
        .related-item:hover{background:var(--accent-lt)}
        .related-icon{font-size:1.2rem}
        .related-info{flex:1}
        .related-title{font-size:.85rem;font-weight:600;color:var(--ink)}
        .related-meta{font-size:.7rem;color:var(--ink-faint)}

        @media(max-width:900px){
            .sidebar{display:none}.main{margin-left:0}
            .material-container{grid-template-columns:1fr}
            .body{padding:20px}
            .material-header{flex-direction:column;text-align:center}
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand">
        <div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div>
        <div class="sb-name">Smart Learning</div>
        <div class="sb-role">Learning Portal</div>
    </div>
    <ul class="sb-nav">
        <li><a href="../dashboard/<?= $role ?>_dashboard.php"><span class="nav-icon">⊞</span> Dashboard</a></li>
        <li><a href="materials_list.php"><span class="nav-icon">📚</span> Materials</a></li>
        <li><a href="../quiz/quizzes.php"><span class="nav-icon">📝</span> Quizzes</a></li>
    </ul>
    <div class="sb-footer">
        <div class="sb-user"><div class="sb-avatar"><?= $avatarLetter ?></div><div><div class="sb-user-name"><?= $fullName ?></div><div class="sb-user-sub"><?= ucfirst($role) ?></div></div></div>
        <a href="../../logout.php" class="sb-logout">→ Sign out</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div class="topbar-breadcrumb"><a href="materials_list.php">Materials</a> / <span><?= htmlspecialchars($material['title']) ?></span></div>
    </div>
    <div class="body">
        <div class="material-container">
            <div class="material-main">
                <div class="material-header">
                    <div class="material-icon"><?= $icon ?></div>
                    <div>
                        <div class="material-title"><h1><?= htmlspecialchars($material['title']) ?></h1></div>
                        <div class="material-meta">
                            <span class="meta-item">📅 <?= date('F j, Y', strtotime($material['created_at'])) ?></span>
                            <span class="meta-item">👤 <?= htmlspecialchars($material['uploader_name']) ?></span>
                            <span class="meta-item">👁️ <?= (int)($material['views'] ?? 0) + 1 ?> views</span>
                        </div>
                    </div>
                </div>
                <div class="material-content">
                    <div class="material-description">
                        <?= nl2br(htmlspecialchars($material['description'] ?? 'No description provided.')) ?>
                    </div>
                    <div class="material-actions">
                        <?php if ($material['file_path']): ?>
                            <a href="../../<?= $material['file_path'] ?>" class="btn-download" download>📥 Download Material</a>
                        <?php endif; ?>
                        <?php if ($canEdit): ?>
                            <a href="edit_material.php?id=<?= $material['id'] ?>" class="btn-edit">✏ Edit Material</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="sidebar-info">
                <div class="info-card">
                    <h3>Material Info</h3>
                    <div class="info-row"><span class="info-label">File Type</span><span class="info-value"><?= $fileExt ?></span></div>
                    <div class="info-row"><span class="info-label">Category</span><span class="info-value"><?= htmlspecialchars($material['category'] ?? 'General') ?></span></div>
                    <div class="info-row"><span class="info-label">Subject</span><span class="info-value"><?= htmlspecialchars($material['subject'] ?? 'General') ?></span></div>
                    <div class="info-row"><span class="info-label">Uploaded by</span><span class="info-value"><?= htmlspecialchars($material['uploader_name']) ?></span></div>
                </div>

                <?php if (!empty($relatedMaterials)): ?>
                <div class="info-card">
                    <h3>Related Materials</h3>
                    <div class="related-grid">
                        <?php foreach ($relatedMaterials as $rel): ?>
                            <a href="material_detail.php?id=<?= $rel['id'] ?>" class="related-item">
                                <div class="related-icon"><?= $typeIcon[$rel['type']] ?? '📁' ?></div>
                                <div class="related-info"><div class="related-title"><?= htmlspecialchars($rel['title']) ?></div><div class="related-meta">👁️ <?= (int)($rel['views'] ?? 0) ?> views</div></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>