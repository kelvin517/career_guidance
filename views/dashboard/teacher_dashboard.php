<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../models/Performance.php';
require_once __DIR__ . '/../models/Quiz.php';
require_once __DIR__ . '/../models/Material.php';
require_once __DIR__ . '/../models/Message.php';

require_role('teacher');

$perf     = new Performance($conn);
$quiz     = new Quiz($conn);
$material = new Material($conn);
$msg      = new Message($conn);

$userId   = $_SESSION['user_id'];
$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];

$myQuizzes     = $quiz->getByCreator($userId);
$myMaterials   = $material->getByUploader($userId);
$unread        = $msg->countUnread($userId);
$notifications = $msg->getNotifications($userId, true, 5);
$platformStats = $perf->getPlatformStats();
$recentSignups = array_slice($perf->getRegistrationTrend(), -6);

$hour     = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

$totalQuizzes    = count($myQuizzes);
$totalMaterials  = count($myMaterials);
$totalAttempts   = array_sum(array_column($myQuizzes, 'attempt_count'));
$publishedQuizzes = count(array_filter($myQuizzes, fn($q) => $q['is_published']));
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
  --accent:#2563a8;--accent-lt:#eef3fb;--accent-dim:rgba(37,99,168,.12);
  --border:#e4e8ed;--radius:14px;
  --sidebar:264px;
  --orange:#c8622a;--green:#1f7a5c;--amber:#b87c10;--red:#c0392b;
}
html,body{min-height:100vh;font-family:'DM Sans',sans-serif;background:var(--canvas);color:var(--ink)}

/* ── Sidebar ── */
.sidebar{
  position:fixed;top:0;left:0;height:100vh;width:var(--sidebar);
  background:#1a2235;display:flex;flex-direction:column;z-index:100;
  padding:0;overflow-y:auto;
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
.sb-badge{margin-left:auto;background:var(--orange);color:#fff;font-size:.68rem;font-weight:700;padding:2px 7px;border-radius:20px}

.sb-footer{margin-top:auto;padding:16px 10px;border-top:1px solid rgba(255,255,255,.06)}
.sb-user{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:9px;cursor:default}
.sb-avatar{width:36px;height:36px;border-radius:10px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;color:#fff;flex-shrink:0}
.sb-user-name{font-size:.85rem;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sb-user-sub{font-size:.7rem;color:rgba(255,255,255,.3)}
.sb-logout{color:rgba(255,255,255,.3);text-decoration:none;font-size:.8rem;padding:8px 14px;display:block;border-radius:8px;transition:color .2s}
.sb-logout:hover{color:#fff}

/* ── Main ── */
.main{margin-left:var(--sidebar);min-height:100vh;display:flex;flex-direction:column}

/* ── Topbar ── */
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
  font-size:.82rem;font-weight:600;text-decoration:none;border:none;cursor:pointer;
  font-family:'DM Sans',sans-serif;transition:background .2s,color .2s;
}
.tb-btn-primary{background:var(--accent);color:#fff}
.tb-btn-primary:hover{background:#1a4f8f}
.tb-btn-ghost{background:var(--canvas);color:var(--ink-soft);border:1.5px solid var(--border)}
.tb-btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.notif-btn{position:relative;background:none;border:none;cursor:pointer;font-size:1.1rem;color:var(--ink-soft);padding:8px;border-radius:8px;transition:background .2s}
.notif-btn:hover{background:var(--accent-lt)}
.notif-dot{position:absolute;top:5px;right:5px;width:7px;height:7px;background:var(--orange);border-radius:50%;border:2px solid #fff}

/* ── Body ── */
.body{padding:32px 36px;flex:1}

/* Banner */
.banner{
  background:linear-gradient(135deg,#1e3a5f 0%,#2563a8 100%);
  border-radius:18px;padding:32px 36px;margin-bottom:28px;
  display:flex;align-items:center;justify-content:space-between;
  position:relative;overflow:hidden;
}
.banner::after{
  content:'';position:absolute;right:-40px;top:-60px;
  width:300px;height:300px;border-radius:50%;
  background:radial-gradient(circle,rgba(255,255,255,.08) 0%,transparent 70%);
}
.banner-greeting{font-size:.75rem;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.45);margin-bottom:6px}
.banner-name{font-family:'DM Serif Display',serif;font-size:2rem;color:#fff;letter-spacing:-.02em;margin-bottom:6px}
.banner-sub{font-size:.875rem;color:rgba(255,255,255,.55);line-height:1.6}
.banner-actions{display:flex;gap:10px;margin-top:20px;position:relative;z-index:1}
.banner-btn{
  padding:10px 20px;border-radius:9px;font-size:.82rem;font-weight:600;
  text-decoration:none;transition:background .2s;
}
.banner-btn-white{background:#fff;color:var(--accent)}
.banner-btn-white:hover{background:#eef3fb}
.banner-btn-outline{background:rgba(255,255,255,.12);color:#fff;border:1.5px solid rgba(255,255,255,.2)}
.banner-btn-outline:hover{background:rgba(255,255,255,.2)}
.banner-badge{
  position:relative;z-index:1;background:rgba(255,255,255,.1);
  border-radius:14px;padding:18px 24px;text-align:center;border:1px solid rgba(255,255,255,.15);
}
.banner-badge-num{font-family:'DM Serif Display',serif;font-size:2.8rem;color:#fff;line-height:1}
.banner-badge-label{font-size:.72rem;color:rgba(255,255,255,.5);letter-spacing:.06em;text-transform:uppercase;margin-top:4px}

/* Stats */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
.stat-card{
  background:var(--white);border-radius:var(--radius);padding:20px 22px;
  border:1.5px solid var(--border);
  animation:fadeUp .4s ease both;
}
.stat-card:nth-child(1){animation-delay:.05s}
.stat-card:nth-child(2){animation-delay:.1s}
.stat-card:nth-child(3){animation-delay:.15s}
.stat-card:nth-child(4){animation-delay:.2s}
.stat-icon{font-size:1.5rem;margin-bottom:10px}
.stat-label{font-size:.7rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--ink-faint);margin-bottom:8px}
.stat-value{font-family:'DM Serif Display',serif;font-size:2rem;color:var(--ink);letter-spacing:-.02em}
.stat-delta{font-size:.75rem;color:var(--green);margin-top:4px;font-weight:500}
.stat-delta.neg{color:var(--red)}

/* Grid */
.grid-main{display:grid;grid-template-columns:1.6fr 1fr;gap:22px;margin-bottom:22px}
.grid-bottom{display:grid;grid-template-columns:1fr 1fr;gap:22px}

/* Panels */
.panel{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);overflow:hidden;animation:fadeUp .4s ease both;animation-delay:.25s}
.panel-head{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.panel-title{font-family:'DM Serif Display',serif;font-size:1.05rem;letter-spacing:-.01em}
.panel-link{font-size:.78rem;color:var(--accent);text-decoration:none;font-weight:600}
.panel-link:hover{text-decoration:underline}
.panel-body{padding:20px 22px}

/* Quiz table */
.quiz-table{width:100%;border-collapse:collapse}
.quiz-table th{
  font-size:.68rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;
  color:var(--ink-faint);padding:0 0 12px;text-align:left;border-bottom:1px solid var(--border);
}
.quiz-table td{padding:13px 0;border-bottom:1px solid var(--border);font-size:.85rem;vertical-align:middle}
.quiz-table tr:last-child td{border-bottom:none}
.q-title{font-weight:600;color:var(--ink)}
.q-count{color:var(--ink-faint);font-size:.8rem}
.pill{
  display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;
  font-size:.72rem;font-weight:700;letter-spacing:.03em;
}
.pill-green{background:#e8f5f0;color:var(--green)}
.pill-amber{background:#fdf4e0;color:var(--amber)}
.pill-red{background:#fdf0ef;color:var(--red)}
.q-actions{display:flex;gap:6px}
.icon-btn{
  background:none;border:1.5px solid var(--border);border-radius:7px;
  padding:5px 9px;cursor:pointer;font-size:.8rem;color:var(--ink-soft);
  transition:border-color .2s,color .2s;text-decoration:none;
}
.icon-btn:hover{border-color:var(--accent);color:var(--accent)}

/* Materials list */
.mat-list{list-style:none;display:flex;flex-direction:column;gap:8px}
.mat-item{
  display:flex;align-items:center;gap:12px;padding:12px 14px;
  background:var(--canvas);border-radius:10px;border:1.5px solid transparent;
  text-decoration:none;transition:border-color .2s;
}
.mat-item:hover{border-color:var(--accent)}
.mat-type-icon{
  width:34px;height:34px;border-radius:8px;background:var(--accent-dim);
  display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;
}
.mat-name{font-size:.85rem;font-weight:600;color:var(--ink)}
.mat-meta{font-size:.72rem;color:var(--ink-faint);margin-top:2px}
.mat-views{margin-left:auto;font-size:.75rem;color:var(--ink-faint)}

/* Activity feed */
.feed{list-style:none;display:flex;flex-direction:column;gap:0}
.feed-item{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--border)}
.feed-item:last-child{border-bottom:none}
.feed-dot{width:8px;height:8px;border-radius:50%;background:var(--accent);margin-top:5px;flex-shrink:0}
.feed-text{font-size:.83rem;color:var(--ink-soft);line-height:1.5}
.feed-time{font-size:.7rem;color:var(--ink-faint);margin-top:2px}

/* Bar chart */
.bar-chart{display:flex;flex-direction:column;gap:10px}
.bc-row{display:grid;grid-template-columns:80px 1fr 36px;align-items:center;gap:10px}
.bc-label{font-size:.78rem;color:var(--ink-soft);font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bc-track{height:10px;background:var(--canvas);border-radius:20px;overflow:hidden}
.bc-fill{height:100%;border-radius:20px;background:var(--accent);transition:width .6s ease}
.bc-val{font-size:.75rem;font-weight:700;color:var(--ink);text-align:right}

.empty{text-align:center;padding:28px 16px;color:var(--ink-faint);font-size:.85rem}
.empty-icon{font-size:1.8rem;margin-bottom:8px}

@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

@media(max-width:900px){
  .sidebar{display:none}.main{margin-left:0}
  .stats-row{grid-template-columns:1fr 1fr}
  .grid-main,.grid-bottom{grid-template-columns:1fr}
  .body{padding:20px}
}
</style>
</head>
<body>

<!-- ── Sidebar ── -->
<aside class="sidebar">
  <div class="sb-brand">
    <div class="sb-mark">
      <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
    </div>
    <div class="sb-name">Smart Learning</div>
    <div class="sb-role">Teacher Portal</div>
  </div>

  <div class="sb-section">Teaching</div>
  <ul class="sb-nav">
    <li><a href="#" class="active"><span class="nav-icon">⊞</span> Dashboard</a></li>
    <li><a href="../quizzes.php"><span class="nav-icon">📝</span> My Quizzes</a></li>
    <li><a href="../materials.php"><span class="nav-icon">📚</span> Materials</a></li>
    <li><a href="../students.php"><span class="nav-icon">🎓</span> Students</a></li>
    <li><a href="../performance.php"><span class="nav-icon">📊</span> Performance</a></li>
  </ul>

  <div class="sb-section">Tools</div>
  <ul class="sb-nav">
    <li>
      <a href="../messages.php">
        <span class="nav-icon">✉</span> Messages
        <?php if ($unread > 0): ?><span class="sb-badge"><?= $unread ?></span><?php endif; ?>
      </a>
    </li>
    <li><a href="../profile.php"><span class="nav-icon">👤</span> Profile</a></li>
  </ul>

  <div class="sb-footer">
    <div class="sb-user">
      <div class="sb-avatar"><?= strtoupper(substr($firstName, 0, 1)) ?></div>
      <div>
        <div class="sb-user-name"><?= htmlspecialchars($fullName) ?></div>
        <div class="sb-user-sub">Teacher</div>
      </div>
    </div>
    <a href="../logout.php" class="sb-logout">→ Sign out</a>
  </div>
</aside>

<!-- ── Main ── -->
<div class="main">

  <div class="topbar">
    <div class="topbar-breadcrumb">Smart Learning / <span>Dashboard</span></div>
    <div class="topbar-right">
      <a href="../quizzes.php?new=1" class="tb-btn tb-btn-ghost">+ New Quiz</a>
      <a href="../materials.php?upload=1" class="tb-btn tb-btn-primary">↑ Upload Material</a>
      <button class="notif-btn" title="Notifications">
        🔔<?php if (!empty($notifications)): ?><span class="notif-dot"></span><?php endif; ?>
      </button>
    </div>
  </div>

  <div class="body">

    <!-- Banner -->
    <div class="banner">
      <div>
        <div class="banner-greeting"><?= $greeting ?>, Teacher</div>
        <div class="banner-name"><?= htmlspecialchars($firstName) ?></div>
        <div class="banner-sub">You have <strong style="color:#fff"><?= $publishedQuizzes ?> published <?= $publishedQuizzes === 1 ? 'quiz' : 'quizzes' ?></strong> and <strong style="color:#fff"><?= $totalMaterials ?> learning <?= $totalMaterials === 1 ? 'material' : 'materials' ?></strong> live right now.</div>
        <div class="banner-actions">
          <a href="../quizzes.php" class="banner-btn banner-btn-white">Manage Quizzes</a>
          <a href="../performance.php" class="banner-btn banner-btn-outline">View Reports</a>
        </div>
      </div>
      <div class="banner-badge">
        <div class="banner-badge-num"><?= $totalAttempts ?></div>
        <div class="banner-badge-label">Total Attempts</div>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-icon">📝</div>
        <div class="stat-label">My Quizzes</div>
        <div class="stat-value"><?= $totalQuizzes ?></div>
        <div class="stat-delta"><?= $publishedQuizzes ?> published</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📚</div>
        <div class="stat-label">Materials</div>
        <div class="stat-value"><?= $totalMaterials ?></div>
        <div class="stat-delta">Uploaded by you</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-label">Quiz Attempts</div>
        <div class="stat-value"><?= $totalAttempts ?></div>
        <div class="stat-delta">Across all quizzes</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">🎓</div>
        <div class="stat-label">Students (Platform)</div>
        <div class="stat-value"><?= $platformStats['total_students'] ?? 0 ?></div>
        <div class="stat-delta">Active learners</div>
      </div>
    </div>

    <!-- Main grid -->
    <div class="grid-main">

      <!-- Quiz table -->
      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">My Quizzes</span>
          <a href="../quizzes.php" class="panel-link">Manage all →</a>
        </div>
        <div class="panel-body">
          <?php if ($myQuizzes): ?>
            <table class="quiz-table">
              <thead>
                <tr>
                  <th>Quiz</th>
                  <th>Questions</th>
                  <th>Attempts</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (array_slice($myQuizzes, 0, 6) as $q): ?>
                  <tr>
                    <td>
                      <div class="q-title"><?= htmlspecialchars($q['title']) ?></div>
                      <div class="q-count"><?= date('d M Y', strtotime($q['created_at'])) ?></div>
                    </td>
                    <td><?= $q['question_count'] ?></td>
                    <td><?= $q['attempt_count'] ?></td>
                    <td>
                      <span class="pill <?= $q['is_published'] ? 'pill-green' : 'pill-amber' ?>">
                        <?= $q['is_published'] ? 'Live' : 'Draft' ?>
                      </span>
                    </td>
                    <td>
                      <div class="q-actions">
                        <a href="../quiz_edit.php?id=<?= $q['id'] ?>" class="icon-btn" title="Edit">✏</a>
                        <a href="../quiz_results.php?id=<?= $q['id'] ?>" class="icon-btn" title="Results">📊</a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="empty"><div class="empty-icon">📝</div>No quizzes yet.<br><a href="../quizzes.php?new=1" style="color:var(--accent)">Create your first quiz</a></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Materials -->
      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">My Materials</span>
          <a href="../materials.php" class="panel-link">View all →</a>
        </div>
        <div class="panel-body">
          <?php if ($myMaterials): ?>
            <ul class="mat-list">
              <?php
              $typeIcon = ['pdf'=>'📄','doc'=>'📝','docx'=>'📝','ppt'=>'📊','pptx'=>'📊','mp4'=>'🎬','link'=>'🔗'];
              foreach (array_slice($myMaterials, 0, 5) as $m):
                $icon = $typeIcon[$m['type']] ?? '📁';
              ?>
                <li>
                  <a href="../material_view.php?id=<?= $m['id'] ?>" class="mat-item">
                    <div class="mat-type-icon"><?= $icon ?></div>
                    <div>
                      <div class="mat-name"><?= htmlspecialchars($m['title']) ?></div>
                      <div class="mat-meta"><?= strtoupper($m['type']) ?> · <?= $m['category'] ?? 'General' ?></div>
                    </div>
                    <div class="mat-views"><?= $m['views'] ?? 0 ?> views</div>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="empty"><div class="empty-icon">📚</div>No materials uploaded yet.</div>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- Bottom grid -->
    <div class="grid-bottom">

      <!-- Attempt distribution -->
      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">Quiz Engagement</span>
        </div>
        <div class="panel-body">
          <?php if ($myQuizzes): ?>
            <?php
            $maxAttempts = max(array_column($myQuizzes, 'attempt_count')) ?: 1;
            ?>
            <div class="bar-chart">
              <?php foreach (array_slice($myQuizzes, 0, 6) as $q): ?>
                <div class="bc-row">
                  <div class="bc-label" title="<?= htmlspecialchars($q['title']) ?>"><?= htmlspecialchars(mb_substr($q['title'], 0, 14)) ?></div>
                  <div class="bc-track">
                    <div class="bc-fill" style="width:<?= round(($q['attempt_count']/$maxAttempts)*100) ?>%"></div>
                  </div>
                  <div class="bc-val"><?= $q['attempt_count'] ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="empty"><div class="empty-icon">📊</div>No engagement data yet.</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Notifications -->
      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">Recent Notifications</span>
          <a href="../messages.php" class="panel-link">Inbox →</a>
        </div>
        <div class="panel-body">
          <?php if ($notifications): ?>
            <ul class="feed">
              <?php foreach ($notifications as $n): ?>
                <li class="feed-item">
                  <div class="feed-dot"></div>
                  <div>
                    <div class="feed-text"><?= htmlspecialchars($n['title']) ?></div>
                    <div class="feed-time"><?= date('d M, H:i', strtotime($n['created_at'])) ?></div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
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