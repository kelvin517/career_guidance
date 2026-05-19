<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');
if ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'counselor') redirect_by_role($_SESSION['role']);

$userId = $_SESSION['user_id'];
$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
$role = $_SESSION['role'];

// Get filter parameters
$selectedClass = $_GET['class'] ?? '';
$selectedSubject = $_GET['subject'] ?? '';

// Get all students (for teachers, optionally filter by class)
$students = [];
$studentQuery = "SELECT id, full_name, email FROM users WHERE role = 'student' AND is_active = 1 ORDER BY full_name";
if ($role === 'teacher' && !empty($selectedClass)) {
    // Teachers might have class assignments - you can implement class filtering
}
$studentResult = mysqli_query($conn, $studentQuery);
while ($student = mysqli_fetch_assoc($studentResult)) {
    $students[] = $student;
}

// Get all subjects for filter
$subjects = [];
$subjResult = mysqli_query($conn, "SELECT DISTINCT subject FROM quizzes WHERE subject IS NOT NULL ORDER BY subject");
while ($row = mysqli_fetch_assoc($subjResult)) {
    $subjects[] = $row['subject'];
}

// Student performance data
$studentPerformance = [];
foreach ($students as $student) {
    $sql = "SELECT qa.*, q.title as quiz_title, q.subject 
            FROM quiz_attempts qa 
            JOIN quizzes q ON qa.quiz_id = q.id 
            WHERE qa.student_id = {$student['id']}";
    if (!empty($selectedSubject)) {
        $sql .= " AND q.subject = '$selectedSubject'";
    }
    $sql .= " ORDER BY qa.attempted_at DESC";
    
    $attempts = mysqli_query($conn, $sql);
    $totalScore = 0;
    $attemptCount = 0;
    $bestScore = 0;
    
    while ($attempt = mysqli_fetch_assoc($attempts)) {
        $percentage = ($attempt['score'] / $attempt['total_questions']) * 100;
        $totalScore += $percentage;
        $attemptCount++;
        if ($percentage > $bestScore) $bestScore = $percentage;
    }
    
    $studentPerformance[$student['id']] = [
        'name' => $student['full_name'],
        'email' => $student['email'],
        'attempts' => $attemptCount,
        'avgScore' => $attemptCount > 0 ? round($totalScore / $attemptCount, 1) : 0,
        'bestScore' => round($bestScore, 1)
    ];
}

// Sort by average score (descending)
uasort($studentPerformance, function($a, $b) {
    return $b['avgScore'] - $a['avgScore'];
});

// Calculate class statistics
$totalStudents = count($students);
$studentsWithAttempts = count(array_filter($studentPerformance, fn($s) => $s['attempts'] > 0));
$classAverage = 0;
$totalAvg = 0;
foreach ($studentPerformance as $perf) {
    $totalAvg += $perf['avgScore'];
}
$classAverage = $totalStudents > 0 ? round($totalAvg / $totalStudents, 1) : 0;

// Get top performers
$topPerformers = array_slice($studentPerformance, 0, 5, true);

// Get struggling students (below 50% average)
$strugglingStudents = array_filter($studentPerformance, fn($s) => $s['avgScore'] > 0 && $s['avgScore'] < 50);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Reports — Smart Learning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .sb-nav{list-style:none;padding:2px 10px}
        .sb-nav li a{
            display:flex;align-items:center;gap:11px;padding:10px 14px;
            border-radius:9px;font-size:.875rem;color:rgba(255,255,255,.5);
            text-decoration:none;transition:background .2s,color .2s;
        }
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

        .page-header{margin-bottom:28px}
        .page-title{font-family:'DM Serif Display',serif;font-size:1.8rem;letter-spacing:-.02em}
        .page-sub{font-size:.85rem;color:var(--ink-faint);margin-top:4px}

        .filter-bar{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:20px;margin-bottom:24px;display:flex;gap:16px;flex-wrap:wrap}
        .filter-group{flex:1}
        .filter-group label{font-size:.7rem;font-weight:700;display:block;margin-bottom:6px}
        .filter-group select{width:100%;padding:10px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif}
        .btn{background:var(--accent);color:#fff;border:none;padding:10px 24px;border-radius:10px;cursor:pointer;align-self:flex-end}

        .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
        .stat-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:20px}
        .stat-value{font-family:'DM Serif Display',serif;font-size:2rem}
        .stat-label{font-size:.7rem;color:var(--ink-faint);margin-top:4px}

        .chart-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px}
        .chart-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:20px}

        .alert-card{background:var(--accent-lt);border-left:4px solid var(--orange);padding:16px;border-radius:var(--radius);margin-bottom:20px}
        .student-table{width:100%;background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);overflow-x:auto}
        table{width:100%;border-collapse:collapse}
        th,td{padding:14px 16px;text-align:left;border-bottom:1px solid var(--border)}
        th{background:var(--accent-lt);font-weight:600}
        .score-high{color:var(--green);font-weight:600}
        .score-low{color:var(--red);font-weight:600}
        .status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:.75rem;font-weight:600}
        .status-good{background:#e8f5f0;color:var(--green)}
        .status-warning{background:#fef2f2;color:var(--red)}

        @media(max-width:900px){.sidebar{display:none}.main{margin-left:0}.body{padding:20px}.stats-row{grid-template-columns:repeat(2,1fr)}.chart-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand"><div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><div class="sb-name">Smart Learning</div><div class="sb-role">Reports</div></div>
    <ul class="sb-nav">
        <li><a href="../dashboard/<?php echo $role; ?>_dashboard.php"><span class="nav-icon">⊞</span> Dashboard</a></li>
        <li><a href="class_reports.php" class="active"><span class="nav-icon">📊</span> Class Reports</a></li>
        <li><a href="performance_charts.php"><span class="nav-icon">📈</span> My Performance</a></li>
    </ul>
    <div class="sb-footer">
        <div class="sb-user"><div class="sb-avatar"><?php echo $avatarLetter; ?></div><div><div class="sb-user-name"><?php echo htmlspecialchars($fullName); ?></div><div class="sb-user-sub"><?php echo ucfirst($role); ?></div></div></div>
        <a href="../../logout.php" class="sb-logout">→ Sign out</a>
    </div>
</aside>

<div class="main">
    <div class="topbar"><div class="topbar-breadcrumb">Analytics / <span>Class Reports</span></div></div>
    <div class="body">
        <div class="page-header">
            <h1 class="page-title">Class Performance Reports</h1>
            <p class="page-sub">Monitor student progress, identify at-risk learners, and track class averages</p>
        </div>

        <!-- Filters -->
        <div class="filter-bar">
            <form method="GET" action="" style="display:flex; gap:16px; width:100%; flex-wrap:wrap;">
                <div class="filter-group"><label>Filter by Subject</label><select name="subject"><option value="">All Subjects</option><?php foreach ($subjects as $sub): ?><option value="<?php echo htmlspecialchars($sub); ?>" <?php echo $selectedSubject == $sub ? 'selected' : ''; ?>><?php echo htmlspecialchars($sub); ?></option><?php endforeach; ?></select></div>
                <button type="submit" class="btn">Apply Filter</button>
                <?php if (!empty($selectedSubject)): ?><a href="class_reports.php" class="btn" style="background:var(--border);color:var(--ink);">Clear</a><?php endif; ?>
            </form>
        </div>

        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card"><div class="stat-value"><?php echo $totalStudents; ?></div><div class="stat-label">Total Students</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $studentsWithAttempts; ?></div><div class="stat-label">Active Students</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $classAverage; ?>%</div><div class="stat-label">Class Average</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo count($strugglingStudents); ?></div><div class="stat-label">At-Risk Students</div></div>
        </div>

        <!-- Class Performance Chart -->
        <div class="chart-card">
            <h3>📊 Class Performance Distribution</h3>
            <canvas id="classChart" height="100"></canvas>
        </div>

        <!-- Alert for struggling students -->
        <?php if (!empty($strugglingStudents)): ?>
            <div class="alert-card">
                <strong>⚠️ Attention Required</strong><br>
                <?php echo count($strugglingStudents); ?> student(s) are performing below 50% average. Consider additional support or remedial sessions.
            </div>
        <?php endif; ?>

        <!-- Student Performance Table -->
        <div class="student-table">
            <h3 style="padding:16px 16px 0;">📋 Student Performance Overview</h3>
            <table>
                <thead><tr><th>Student Name</th><th>Email</th><th>Quizzes Taken</th><th>Average Score</th><th>Best Score</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($studentPerformance as $perf): 
                        $statusClass = $perf['avgScore'] >= 70 ? 'status-good' : ($perf['avgScore'] >= 50 ? 'status-good' : 'status-warning');
                        $statusText = $perf['avgScore'] >= 70 ? 'Good Standing' : ($perf['avgScore'] >= 50 ? 'Average' : 'At Risk');
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($perf['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($perf['email']); ?></td>
                            <td><?php echo $perf['attempts']; ?></td>
                            <td class="<?php echo $perf['avgScore'] >= 70 ? 'score-high' : ($perf['avgScore'] < 50 ? 'score-low' : ''); ?>"><?php echo $perf['avgScore']; ?>%</td>
                            <td><?php echo $perf['bestScore']; ?>%</td>
                            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                            <td><a href="../dashboard/student_dashboard.php?user=<?php echo array_search($perf, $studentPerformance); ?>" style="color:var(--accent);">View Details →</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Class Performance Distribution Chart
    const ranges = [
        <?php 
            $range0_20 = count(array_filter($studentPerformance, fn($s) => $s['avgScore'] > 0 && $s['avgScore'] < 20));
            $range20_40 = count(array_filter($studentPerformance, fn($s) => $s['avgScore'] >= 20 && $s['avgScore'] < 40));
            $range40_60 = count(array_filter($studentPerformance, fn($s) => $s['avgScore'] >= 40 && $s['avgScore'] < 60));
            $range60_80 = count(array_filter($studentPerformance, fn($s) => $s['avgScore'] >= 60 && $s['avgScore'] < 80));
            $range80_100 = count(array_filter($studentPerformance, fn($s) => $s['avgScore'] >= 80));
        ?>
        <?php echo $range0_20; ?>, <?php echo $range20_40; ?>, <?php echo $range40_60; ?>, <?php echo $range60_80; ?>, <?php echo $range80_100; ?>
    ];
    
    const ctx = document.getElementById('classChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['0-20%', '20-40%', '40-60%', '60-80%', '80-100%'],
            datasets: [{
                label: 'Number of Students',
                data: ranges,
                backgroundColor: '#2563a8',
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: { y: { beginAtZero: true, title: { display: true, text: 'Number of Students' } } }
        }
    });
</script>
</body>
</html>