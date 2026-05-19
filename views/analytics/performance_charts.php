<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');

$userId = $_SESSION['user_id'];
$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
$role = $_SESSION['role'];

// Get all quiz attempts for this student
$attempts = [];
$quizResult = mysqli_query($conn, "
    SELECT qa.*, q.title as quiz_title, q.subject, q.time_limit
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.student_id = $userId
    ORDER BY qa.attempted_at DESC
");

while ($row = mysqli_fetch_assoc($quizResult)) {
    $attempts[] = $row;
}

// Calculate statistics
$totalQuizzes = count($attempts);
$totalScore = 0;
$bestScore = 0;
$worstScore = 100;
$subjectScores = [];

foreach ($attempts as $attempt) {
    $percentage = ($attempt['score'] / $attempt['total_questions']) * 100;
    $totalScore += $percentage;
    if ($percentage > $bestScore) $bestScore = $percentage;
    if ($percentage < $worstScore) $worstScore = $percentage;
    
    // Subject breakdown
    $subject = $attempt['subject'];
    if (!isset($subjectScores[$subject])) {
        $subjectScores[$subject] = ['total' => 0, 'count' => 0];
    }
    $subjectScores[$subject]['total'] += $percentage;
    $subjectScores[$subject]['count']++;
}

$avgScore = $totalQuizzes > 0 ? round($totalScore / $totalQuizzes, 1) : 0;

// Get chart data (last 10 attempts for trend)
$trendLabels = [];
$trendScores = [];
$trendData = array_slice($attempts, 0, 10);
$trendData = array_reverse($trendData);
foreach ($trendData as $attempt) {
    $trendLabels[] = date('d M', strtotime($attempt['attempted_at']));
    $trendScores[] = round(($attempt['score'] / $attempt['total_questions']) * 100, 1);
}

// Subject performance data
$subjectLabels = [];
$subjectData = [];
foreach ($subjectScores as $subject => $data) {
    $subjectLabels[] = $subject;
    $subjectData[] = round($data['total'] / $data['count'], 1);
}

// Get recent recommendations
$recommendations = getCareerRecommendations($userId);
$greeting = date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Performance — Smart Learning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --ink:#0f1117;--ink-soft:#4a4d5a;--ink-faint:#9396a3;
            --canvas:#f5f7f9;--white:#fff;
            --accent:#2563a8;--accent-lt:#eef3fb;--accent-dim:rgba(37,99,168,.12);
            --border:#e4e8ed;--radius:14px;
            --sidebar:264px;
            --green:#1f7a5c;--orange:#c8622a;--red:#c0392b;--yellow:#f1c40f;
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
        .topbar{background:var(--white);border-bottom:1px solid var(--border);padding:16px 36px;display:flex;align-items:center;position:sticky;top:0;z-index:50}
        .topbar-breadcrumb{font-size:.8rem;color:var(--ink-faint)}
        .topbar-breadcrumb span{color:var(--ink);font-weight:600}
        .body{padding:32px 36px}

        .page-header{margin-bottom:28px}
        .page-title{font-family:'DM Serif Display',serif;font-size:1.8rem;letter-spacing:-.02em}
        .page-sub{font-size:.85rem;color:var(--ink-faint);margin-top:4px}

        .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
        .stat-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:20px}
        .stat-value{font-family:'DM Serif Display',serif;font-size:2rem;color:var(--ink)}
        .stat-label{font-size:.7rem;color:var(--ink-faint);margin-top:4px}
        .stat-trend{font-size:.75rem;margin-top:6px}
        .trend-up{color:var(--green)}
        .trend-down{color:var(--red)}

        .chart-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px}
        .chart-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:24px}
        .chart-card h3{margin-bottom:20px;font-size:1rem}

        .recent-table{width:100%;background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);overflow-x:auto}
        table{width:100%;border-collapse:collapse}
        th,td{padding:14px 16px;text-align:left;border-bottom:1px solid var(--border)}
        th{background:var(--accent-lt);font-weight:600}
        .score-good{color:var(--green);font-weight:600}
        .score-average{color:var(--orange);font-weight:600}
        .score-poor{color:var(--red);font-weight:600}

        .recommend-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:20px;margin-top:20px}
        .rec-tag{display:inline-block;background:var(--accent-lt);padding:6px 12px;border-radius:20px;margin:4px;font-size:.8rem}
        
        @media(max-width:900px){.sidebar{display:none}.main{margin-left:0}.body{padding:20px}.stats-row{grid-template-columns:repeat(2,1fr)}.chart-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand"><div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><div class="sb-name">Smart Learning</div><div class="sb-role">Analytics</div></div>
    <div class="sb-section">Insights</div>
    <ul class="sb-nav">
        <li><a href="../dashboard/<?php echo $role; ?>_dashboard.php"><span class="nav-icon">⊞</span> Dashboard</a></li>
        <li><a href="performance_charts.php" class="active"><span class="nav-icon">📊</span> My Performance</a></li>
        <li><a href="../career/recommendations.php"><span class="nav-icon">⭐</span> Career Matches</a></li>
        <li><a href="../assessment/interest_questionnaire.php"><span class="nav-icon">📋</span> Take Assessment</a></li>
    </ul>
    <div class="sb-footer">
        <div class="sb-user"><div class="sb-avatar"><?php echo $avatarLetter; ?></div><div><div class="sb-user-name"><?php echo htmlspecialchars($fullName); ?></div><div class="sb-user-sub"><?php echo ucfirst($role); ?></div></div></div>
        <a href="../../logout.php" class="sb-logout">→ Sign out</a>
    </div>
</aside>

<div class="main">
    <div class="topbar"><div class="topbar-breadcrumb">Analytics / <span>My Performance</span></div></div>
    <div class="body">
        <div class="page-header">
            <h1 class="page-title">Performance Analytics</h1>
            <p class="page-sub">Track your quiz performance and academic progress over time</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card"><div class="stat-value"><?php echo $totalQuizzes; ?></div><div class="stat-label">Quizzes Completed</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $avgScore; ?>%</div><div class="stat-label">Average Score</div><div class="stat-trend <?php echo $avgScore >= 70 ? 'trend-up' : ($avgScore >= 50 ? '' : 'trend-down'); ?>"><?php echo $avgScore >= 70 ? '↑ Above Average' : ($avgScore >= 50 ? '→ Average' : '↓ Needs Improvement'); ?></div></div>
            <div class="stat-card"><div class="stat-value"><?php echo round($bestScore, 1); ?>%</div><div class="stat-label">Best Score</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo round($worstScore, 1); ?>%</div><div class="stat-label">Lowest Score</div></div>
        </div>

        <!-- Charts -->
        <div class="chart-grid">
            <div class="chart-card">
                <h3>📈 Performance Trend (Last 10 Quizzes)</h3>
                <canvas id="trendChart" height="200"></canvas>
            </div>
            <div class="chart-card">
                <h3>📚 Performance by Subject</h3>
                <canvas id="subjectChart" height="200"></canvas>
            </div>
        </div>

        <!-- Recent Quiz History -->
        <div class="chart-card" style="margin-bottom:20px;">
            <h3>📋 Recent Quiz History</h3>
            <?php if (!empty($attempts)): ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead><tr><th>Quiz Title</th><th>Subject</th><th>Date</th><th>Score</th><th>Percentage</th><th>Result</th></tr></thead>
                        <tbody>
                            <?php foreach ($attempts as $attempt): 
                                $percentage = ($attempt['score'] / $attempt['total_questions']) * 100;
                                $scoreClass = $percentage >= 70 ? 'score-good' : ($percentage >= 50 ? 'score-average' : 'score-poor');
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($attempt['quiz_title']); ?></td>
                                    <td><?php echo htmlspecialchars($attempt['subject']); ?></td>
                                    <td><?php echo date('d M Y, H:i', strtotime($attempt['attempted_at'])); ?></td>
                                    <td><?php echo $attempt['score'] . '/' . $attempt['total_questions']; ?></td>
                                    <td class="<?php echo $scoreClass; ?>"><?php echo round($percentage, 1); ?>%</td>
                                    <td><?php echo $percentage >= 70 ? '✅ Pass' : ($percentage >= 50 ? '⚠️ Marginal' : '❌ Fail'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state" style="text-align:center; padding:40px;"><p>No quiz attempts yet. Take a quiz to see your performance!</p></div>
            <?php endif; ?>
        </div>

        <!-- Career Recommendations Based on Performance -->
        <?php if (!empty($recommendations)): ?>
            <div class="recommend-card">
                <h3>🎯 Recommended Careers Based on Your Performance</h3>
                <p style="margin-top:8px; font-size:.85rem; color:var(--ink-faint);">Based on your subject strengths and quiz performance</p>
                <div style="margin-top:16px;">
                    <?php foreach ($recommendations as $rec): ?>
                        <a href="../career/career_detail.php?id=<?php echo $rec['career']['id']; ?>" class="rec-tag" style="text-decoration:none;">🏢 <?php echo htmlspecialchars($rec['career']['career_name']); ?> (<?php echo $rec['score']; ?>% match)</a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Performance Trend Chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trendLabels); ?>,
            datasets: [{
                label: 'Quiz Score (%)',
                data: <?php echo json_encode($trendScores); ?>,
                borderColor: '#2563a8',
                backgroundColor: 'rgba(37, 99, 168, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#2563a8',
                pointBorderColor: '#fff',
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: 'Score (%)' } } },
            plugins: { legend: { position: 'top' } }
        }
    });

    // Subject Performance Chart
    const subjectCtx = document.getElementById('subjectChart').getContext('2d');
    new Chart(subjectCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($subjectLabels); ?>,
            datasets: [{
                label: 'Average Score by Subject (%)',
                data: <?php echo json_encode($subjectData); ?>,
                backgroundColor: '#2563a8',
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: 'Average Score (%)' } } },
            plugins: { legend: { position: 'top' } }
        }
    });
</script>
</body>
</html>