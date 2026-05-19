<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');
if ($_SESSION['role'] !== 'teacher') redirect_by_role($_SESSION['role']);

$userId   = (int)$_SESSION['user_id'];
$fullName = htmlspecialchars($_SESSION['full_name']);
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
$greeting = date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening');

// ─── Fetch teacher profile ─────────────────────────────────────────────
$teacherProfile = [];
if ($conn) {
    $stmt = mysqli_prepare($conn, "SELECT subject_specialization, qualification FROM teacher_profiles WHERE user_id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $teacherProfile = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);
    }
}

// ─── My quizzes ────────────────────────────────────────────────────────
$myQuizzes = [];
if ($conn) {
    $result = mysqli_query($conn, "
        SELECT q.id, q.title, q.subject, q.is_published, q.created_at,
               (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) AS question_count,
               (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id) AS attempt_count
        FROM quizzes q
        WHERE q.created_by = $userId
        ORDER BY q.created_at DESC
    ");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $myQuizzes[] = $row;
        }
    }
}
$totalQuizzes = count($myQuizzes);
$publishedQuizzes = count(array_filter($myQuizzes, fn($q) => $q['is_published'] == 1));
$totalAttempts = array_sum(array_column($myQuizzes, 'attempt_count'));

// ─── My learning materials ────────────────────────────────────────────
$myMaterials = [];
if ($conn) {
    $result = mysqli_query($conn, "
        SELECT id, title, type, category, views, created_at
        FROM materials
        WHERE uploaded_by = $userId
        ORDER BY created_at DESC
    ");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $myMaterials[] = $row;
        }
    }
}
$totalMaterials = count($myMaterials);

// ─── Platform stats ───────────────────────────────────────────────────
$totalStudents = 0;
$studentRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'student' AND is_active = 1");
if ($studentRes) $totalStudents = (int)mysqli_fetch_assoc($studentRes)['c'];

// ─── Notifications / messages ─────────────────────────────────────────
$unread = 0;
$notifications = [];
if ($conn) {
    $msgRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM messages WHERE receiver_id = $userId AND is_read = 0");
    if ($msgRes) $unread = (int)mysqli_fetch_assoc($msgRes)['c'];
    $notifRes = mysqli_query($conn, "SELECT m.id, m.subject AS title, m.sent_at AS created_at FROM messages m WHERE m.receiver_id = $userId ORDER BY m.sent_at DESC LIMIT 5");
    if ($notifRes) {
        while ($row = mysqli_fetch_assoc($notifRes)) {
            $notifications[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard — Smart Learning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --ink:#0f1117;--ink-soft:#4a4d5a;--ink-faint:#9396a3;
            --canvas:#f5f7f9;--white:#fff;
            --accent:#2563a8;--accent-lt:#eef3fb;
            --border:#e4e8ed;--radius:14px;
            --sidebar:264px;
            --orange:#c8622a;--green:#1f7a5c;--amber:#b87c10;--red:#c0392b;
        }
        html,body{min-height:100vh;font-family:'DM Sans',sans-serif;background:var(--canvas);color:var(--ink)}
        .sidebar{position:fixed;top:0;left:0;height:100vh;width:var(--sidebar);background:#1a2235;display:flex;flex-direction:column;z-index:100;overflow-y:auto;}
        .sb-brand{padding:28px 24px 24px;background:linear-gradient(135deg,#1e3a5f 0%,#1a2235 100%);border-bottom:1px solid rgba(255,255,255,.06);}
        .sb-mark{width:40px;height:40px;background:var(--accent);border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:14px;}
        .sb-mark svg{width:22px;height:22px;fill:#fff}
        .sb-name{font-family:'DM Serif Display',serif;font-size:1.15rem;color:#fff;}
        .sb-role{font-size:.68rem;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-top:3px}
        .sb-section{padding:22px 20px 6px;font-size:.62rem;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.22)}
        .sb-nav{list-style:none;padding:2px 10px}
        .sb-nav li a{display:flex;align-items:center;gap:11px;padding:10px 14px;border-radius:9px;font-size:.875rem;color:rgba(255,255,255,.5);text-decoration:none;}
        .sb-nav li a:hover{background:rgba(255,255,255,.06);color:#fff}
        .sb-nav li a.active{background:var(--accent);color:#fff}
        .sb-nav li a .nav-icon{font-size:.95rem;width:18px;text-align:center}
        .sb-badge{margin-left:auto;background:var(--orange);color:#fff;font-size:.68rem;font-weight:700;padding:2px 7px;border-radius:20px}
        .sb-footer{margin-top:auto;padding:16px 10px;border-top:1px solid rgba(255,255,255,.06)}
        .sb-user{display:flex;align-items:center;gap:10px;padding:10px 14px;}
        .sb-avatar{width:36px;height:36px;border-radius:10px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;}
        .sb-user-name{font-size:.85rem;font-weight:600;color:#fff}
        .sb-user-sub{font-size:.7rem;color:rgba(255,255,255,.3)}
        .sb-logout{color:rgba(255,255,255,.3);text-decoration:none;font-size:.8rem;padding:8px 14px;display:block;border-radius:8px;margin-top:8px}
        .sb-logout:hover{color:#fff}
        .main{margin-left:var(--sidebar);min-height:100vh}
        .topbar{background:var(--white);border-bottom:1px solid var(--border);padding:16px 36px;display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:50;}
        .topbar-breadcrumb{font-size:.8rem;color:var(--ink-faint)}
        .topbar-breadcrumb span{color:var(--ink);font-weight:600}
        .topbar-right{margin-left:auto;display:flex;align-items:center;gap:12px}
        .tb-btn{display:flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9px;font-size:.82rem;font-weight:600;text-decoration:none;}
        .tb-btn-primary{background:var(--accent);color:#fff}
        .tb-btn-primary:hover{background:#1a4f8f}
        .tb-btn-ghost{background:var(--canvas);color:var(--ink-soft);border:1.5px solid var(--border)}
        .body{padding:32px 36px}
        .banner{background:linear-gradient(135deg,#1e3a5f 0%,#2563a8 100%);border-radius:18px;padding:32px 36px;margin-bottom:28px;display:flex;align-items:center;justify-content:space-between;}
        .banner-greeting{font-size:.75rem;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.45);margin-bottom:6px}
        .banner-name{font-family:'DM Serif Display',serif;font-size:2rem;color:#fff;margin-bottom:6px}
        .banner-sub{font-size:.875rem;color:rgba(255,255,255,.55)}
        .banner-actions{display:flex;gap:10px;margin-top:20px}
        .banner-btn{padding:10px 20px;border-radius:9px;font-size:.82rem;font-weight:600;text-decoration:none;}
        .banner-btn-white{background:#fff;color:var(--accent)}
        .banner-btn-outline{background:rgba(255,255,255,.12);color:#fff;border:1.5px solid rgba(255,255,255,.2)}
        .banner-badge{background:rgba(255,255,255,.1);border-radius:14px;padding:18px 24px;text-align:center;}
        .banner-badge-num{font-family:'DM Serif Display',serif;font-size:2.8rem;color:#fff;}
        .banner-badge-label{font-size:.72rem;color:rgba(255,255,255,.5);text-transform:uppercase;margin-top:4px}
        .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
        .stat-card{background:var(--white);border-radius:var(--radius);padding:20px 22px;border:1.5px solid var(--border)}
        .stat-icon{font-size:1.5rem;margin-bottom:10px}
        .stat-label{font-size:.7rem;font-weight:600;text-transform:uppercase;color:var(--ink-faint);margin-bottom:8px}
        .stat-value{font-family:'DM Serif Display',serif;font-size:2rem;color:var(--ink)}
        .stat-delta{font-size:.75rem;color:var(--green);margin-top:4px}
        .grid-main{display:grid;grid-template-columns:1.6fr 1fr;gap:22px;margin-bottom:22px}
        .grid-bottom{display:grid;grid-template-columns:1fr 1fr;gap:22px}
        .panel{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);overflow:hidden}
        .panel-head{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between}
        .panel-title{font-family:'DM Serif Display',serif;font-size:1.05rem}
        .panel-link{font-size:.78rem;color:var(--accent);text-decoration:none;font-weight:600}
        .panel-body{padding:20px 22px}
        .quiz-table{width:100%;border-collapse:collapse}
        .quiz-table th{font-size:.68rem;font-weight:700;text-transform:uppercase;color:var(--ink-faint);padding:0 0 12px;text-align:left;border-bottom:1px solid var(--border)}
        .quiz-table td{padding:13px 0;border-bottom:1px solid var(--border);font-size:.85rem}
        .q-title{font-weight:600}
        .q-count{font-size:.72rem;color:var(--ink-faint)}
        .pill{display:inline-flex;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700}
        .pill-green{background:#e8f5f0;color:var(--green)}
        .pill-amber{background:#fdf4e0;color:var(--amber)}
        .icon-btn{background:none;border:1.5px solid var(--border);border-radius:7px;padding:5px 9px;cursor:pointer;font-size:.8rem;color:var(--ink-soft);text-decoration:none;display:inline-block;}
        .mat-list{list-style:none;display:flex;flex-direction:column;gap:8px}
        .mat-item{display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--canvas);border-radius:10px;border:1.5px solid transparent;text-decoration:none;}
        .mat-item:hover{border-color:var(--accent)}
        .mat-type-icon{width:34px;height:34px;background:rgba(37,99,168,.12);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.9rem}
        .mat-name{font-size:.85rem;font-weight:600;color:var(--ink)}
        .mat-meta{font-size:.72rem;color:var(--ink-faint)}
        .mat-views{margin-left:auto;font-size:.75rem;color:var(--ink-faint)}
        .bar-chart{display:flex;flex-direction:column;gap:10px}
        .bc-row{display:grid;grid-template-columns:80px 1fr 36px;align-items:center;gap:10px}
        .bc-label{font-size:.78rem;color:var(--ink-soft);overflow:hidden;text-overflow:ellipsis}
        .bc-track{height:10px;background:var(--canvas);border-radius:20px;overflow:hidden}
        .bc-fill{height:100%;border-radius:20px;background:var(--accent);transition:width .6s ease}
        .bc-val{font-size:.75rem;font-weight:700;color:var(--ink);text-align:right}
        .feed{list-style:none}
        .feed-item{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--border)}
        .feed-dot{width:8px;height:8px;border-radius:50%;background:var(--accent);margin-top:5px}
        .feed-text{font-size:.83rem;color:var(--ink-soft)}
        .feed-time{font-size:.7rem;color:var(--ink-faint);margin-top:2px}
        .empty{text-align:center;padding:28px 16px;color:var(--ink-faint)}
        @media(max-width:900px){.sidebar{display:none}.main{margin-left:0}.stats-row{grid-template-columns:1fr 1fr}.grid-main,.grid-bottom{grid-template-columns:1fr}.body{padding:20px}}
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand"><div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><div class="sb-name">Smart Learning</div><div class="sb-role">Teacher Portal</div></div>
    <div class="sb-section">Teaching</div>
    <ul class="sb-nav">
        <li><a href="#" class="active"><span class="nav-icon">⊞</span> Dashboard</a></li>
        <li><a href="../quiz/quizzes.php"><span class="nav-icon">📝</span> My Quizzes</a></li>
        <li><a href="../learning/materials_list.php"><span class="nav-icon">📚</span> Materials</a></li>
        <li><a href="../analytics/class_reports.php"><span class="nav-icon">📊</span> Performance</a></li>
    </ul>
    <div class="sb-section">Tools</div>
    <ul class="sb-nav">
        <li><a href="../messaging/inbox.php"><span class="nav-icon">✉</span> Messages<?php if ($unread > 0): ?><span class="sb-badge"><?php echo $unread; ?></span><?php endif; ?></a></li>
        <li><a href="../profile.php"><span class="nav-icon">👤</span> Profile</a></li>
    </ul>
    <div class="sb-footer">
        <div class="sb-user"><div class="sb-avatar"><?php echo $avatarLetter; ?></div><div><div class="sb-user-name"><?php echo $fullName; ?></div><div class="sb-user-sub">Teacher</div></div></div>
        <a href="../../logout.php" class="sb-logout">⬡ Sign out</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div class="topbar-breadcrumb">Smart Learning / <span>Dashboard</span></div>
        <div class="topbar-right">
            <a href="../quiz/create_quiz.php" class="tb-btn tb-btn-ghost">+ New Quiz</a>
            <a href="../learning/upload_material.php" class="tb-btn tb-btn-primary">↑ Upload Material</a>
        </div>
    </div>
    <div class="body">
        <div class="banner">
            <div><div class="banner-greeting">Good <?php echo $greeting; ?>, Teacher</div><div class="banner-name"><?php echo $firstName; ?></div><div class="banner-sub">You have <strong style="color:#fff"><?php echo $publishedQuizzes; ?> published <?php echo $publishedQuizzes === 1 ? 'quiz' : 'quizzes'; ?></strong> and <strong style="color:#fff"><?php echo $totalMaterials; ?> learning <?php echo $totalMaterials === 1 ? 'material' : 'materials'; ?></strong> live.</div><div class="banner-actions"><a href="../quiz/quizzes.php" class="banner-btn banner-btn-white">Manage Quizzes</a><a href="../analytics/class_reports.php" class="banner-btn banner-btn-outline">View Reports</a></div></div>
            <div class="banner-badge"><div class="banner-badge-num"><?php echo $totalAttempts; ?></div><div class="banner-badge-label">Total Attempts</div></div>
        </div>

        <div class="stats-row">
            <div class="stat-card"><div class="stat-icon">📝</div><div class="stat-label">My Quizzes</div><div class="stat-value"><?php echo $totalQuizzes; ?></div><div class="stat-delta"><?php echo $publishedQuizzes; ?> published</div></div>
            <div class="stat-card"><div class="stat-icon">📚</div><div class="stat-label">Materials</div><div class="stat-value"><?php echo $totalMaterials; ?></div><div class="stat-delta">Uploaded by you</div></div>
            <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-label">Quiz Attempts</div><div class="stat-value"><?php echo $totalAttempts; ?></div><div class="stat-delta">Across all quizzes</div></div>
            <div class="stat-card"><div class="stat-icon">🎓</div><div class="stat-label">Students (Platform)</div><div class="stat-value"><?php echo $totalStudents; ?></div><div class="stat-delta">Active learners</div></div>
        </div>

        <div class="grid-main">
            <div class="panel">
                <div class="panel-head"><span class="panel-title">My Quizzes</span><a href="../quiz/quizzes.php" class="panel-link">Manage all →</a></div>
                <div class="panel-body">
                    <?php if ($myQuizzes): ?>
                        <table class="quiz-table"><thead><tr><th>Quiz</th><th>Questions</th><th>Attempts</th><th>Status</th><th></th></tr></thead><tbody>
                        <?php foreach (array_slice($myQuizzes, 0, 6) as $q): ?>
                            <tr><td><div class="q-title"><?php echo htmlspecialchars($q['title']); ?></div><div class="q-count"><?php echo date('d M Y', strtotime($q['created_at'])); ?></div></td><td><?php echo (int)$q['question_count']; ?></td><td><?php echo (int)$q['attempt_count']; ?></td><td><span class="pill <?php echo $q['is_published'] ? 'pill-green' : 'pill-amber'; ?>"><?php echo $q['is_published'] ? 'Live' : 'Draft'; ?></span></td><td><div class="q-actions"><a href="../quiz/create_quiz.php?id=<?php echo $q['id']; ?>" class="icon-btn" title="Edit">✏</a><a href="../quiz/quiz_results.php?quiz_id=<?php echo $q['id']; ?>" class="icon-btn" title="Results">📊</a></div></td></tr>
                        <?php endforeach; ?>
                        </tbody></table>
                    <?php else: ?>
                        <div class="empty"><div class="empty-icon">📝</div>No quizzes yet.<br><a href="../quiz/create_quiz.php" style="color:var(--accent)">Create your first quiz</a></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><span class="panel-title">My Materials</span><a href="../learning/materials_list.php" class="panel-link">View all →</a></div>
                <div class="panel-body">
                    <?php if ($myMaterials): ?>
                        <ul class="mat-list"><?php $typeIcon = ['pdf'=>'📄','doc'=>'📝','docx'=>'📝','ppt'=>'📊','pptx'=>'📊','mp4'=>'🎬','link'=>'🔗']; foreach (array_slice($myMaterials, 0, 5) as $m): $icon = $typeIcon[$m['type']] ?? '📁'; ?>
                            <li><a href="../learning/material_detail.php?id=<?php echo $m['id']; ?>" class="mat-item"><div class="mat-type-icon"><?php echo $icon; ?></div><div><div class="mat-name"><?php echo htmlspecialchars($m['title']); ?></div><div class="mat-meta"><?php echo strtoupper($m['type']); ?> · <?php echo $m['category'] ?? 'General'; ?></div></div><div class="mat-views"><?php echo (int)($m['views'] ?? 0); ?> views</div></a></li>
                        <?php endforeach; ?></ul>
                    <?php else: ?>
                        <div class="empty"><div class="empty-icon">📚</div>No materials uploaded yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grid-bottom">
            <div class="panel">
                <div class="panel-head"><span class="panel-title">Quiz Engagement</span></div>
                <div class="panel-body">
                    <?php if ($myQuizzes): ?><?php $maxAttempts = max(array_column($myQuizzes, 'attempt_count')) ?: 1; ?>
                        <div class="bar-chart"><?php foreach (array_slice($myQuizzes, 0, 6) as $q): ?>
                            <div class="bc-row"><div class="bc-label" title="<?php echo htmlspecialchars($q['title']); ?>"><?php echo htmlspecialchars(mb_substr($q['title'], 0, 14)); ?></div><div class="bc-track"><div class="bc-fill" style="width:<?php echo round(($q['attempt_count']/$maxAttempts)*100); ?>%"></div></div><div class="bc-val"><?php echo $q['attempt_count']; ?></div></div>
                        <?php endforeach; ?></div>
                    <?php else: ?>
                        <div class="empty"><div class="empty-icon">📊</div>No engagement data yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><span class="panel-title">Recent Notifications</span><a href="../messaging/inbox.php" class="panel-link">Inbox →</a></div>
                <div class="panel-body">
                    <?php if ($notifications): ?>
                        <ul class="feed"><?php foreach ($notifications as $n): ?>
                            <li class="feed-item"><div class="feed-dot"></div><div><div class="feed-text"><?php echo htmlspecialchars($n['title']); ?></div><div class="feed-time"><?php echo date('d M, H:i', strtotime($n['created_at'])); ?></div></div></li>
                        <?php endforeach; ?></ul>
                    <?php else: ?>
                        <div class="empty"><div class="empty-icon">🔔</div>All caught up!</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>