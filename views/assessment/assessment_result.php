<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');
if ($_SESSION['role'] !== 'student') redirect_by_role($_SESSION['role']);

$userId = $_SESSION['user_id'];
$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
$role = $_SESSION['role'];

// Get latest assessment results
$result = mysqli_query($conn, "SELECT * FROM riasec_results WHERE user_id = $userId ORDER BY taken_at DESC LIMIT 1");
$assessment = mysqli_fetch_assoc($result);

if (!$assessment) {
    header("Location: interest_questionnaire.php");
    exit();
}

$hollandCode = $assessment['holland_code'];
$scores = json_decode($assessment['scores_json'], true);

// Career recommendations based on Holland Code
$careerRecommendations = [
    'R' => ['Mechanical Engineer', 'Electrician', 'Pilot', 'Architect', 'Civil Engineer', 'Chef', 'Athlete'],
    'I' => ['Data Scientist', 'Research Scientist', 'Doctor', 'Pharmacist', 'Biologist', 'Chemist', 'Physicist'],
    'A' => ['Graphic Designer', 'Writer', 'Musician', 'Animator', 'Fashion Designer', 'Photographer', 'Actor'],
    'S' => ['Teacher', 'Psychologist', 'Social Worker', 'Counselor', 'Nurse', 'HR Manager', 'Therapist'],
    'E' => ['Entrepreneur', 'Marketing Manager', 'Sales Director', 'Lawyer', 'CEO', 'Real Estate Agent', 'Politician'],
    'C' => ['Accountant', 'Financial Analyst', 'Data Entry Specialist', 'Office Manager', 'Banker', 'Auditor', 'Administrator']
];

$topCareers = [];
$codeArray = str_split($hollandCode);
foreach ($codeArray as $code) {
    if (isset($careerRecommendations[$code])) {
        $topCareers = array_merge($topCareers, array_slice($careerRecommendations[$code], 0, 2));
    }
}
$topCareers = array_unique(array_slice($topCareers, 0, 6));

// Category names and colors
$categories = [
    'R' => ['name' => 'Realistic', 'icon' => '🔧', 'color' => '#e74c3c', 'desc' => 'Hands-on, mechanical, athletic, technical'],
    'I' => ['name' => 'Investigative', 'icon' => '🔬', 'color' => '#3498db', 'desc' => 'Scientific, analytical, curious, research-oriented'],
    'A' => ['name' => 'Artistic', 'icon' => '🎨', 'color' => '#e67e22', 'desc' => 'Creative, expressive, imaginative, intuitive'],
    'S' => ['name' => 'Social', 'icon' => '🤝', 'color' => '#2ecc71', 'desc' => 'Helping, teaching, counseling, serving others'],
    'E' => ['name' => 'Enterprising', 'icon' => '💼', 'color' => '#f1c40f', 'desc' => 'Persuasive, leadership, business, ambitious'],
    'C' => ['name' => 'Conventional', 'icon' => '📊', 'color' => '#9b59b6', 'desc' => 'Organized, detail-oriented, clerical, systematic']
];

// Update career recommendations in career_paths table based on Holland code
$updateCareers = mysqli_query($conn, "UPDATE student_profiles SET holland_code = '$hollandCode' WHERE user_id = $userId");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Assessment Results — Smart Learning</title>
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
            --green:#1f7a5c;
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
        .sb-logout:hover{color:#fff}
        
        .main{margin-left:var(--sidebar);min-height:100vh}
        .topbar{background:var(--white);border-bottom:1px solid var(--border);padding:16px 36px;}
        .body{padding:32px 36px}
        
        .results-container{max-width:1100px;margin:0 auto}
        .hero-card{background:linear-gradient(135deg,#1e3a5f 0%,#2563a8 100%);color:#fff;border-radius:var(--radius);padding:32px 40px;margin-bottom:28px;text-align:center}
        .hero-code{font-family:'DM Serif Display',serif;font-size:4rem;letter-spacing:8px;margin:16px 0}
        .chart-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:24px;margin-bottom:28px}
        .career-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-top:20px}
        .career-tag{background:var(--accent-lt);padding:10px 16px;border-radius:10px;text-align:center;font-weight:500}
        .btn{display:inline-block;padding:12px 28px;background:var(--accent);color:#fff;border-radius:10px;text-decoration:none;font-weight:600;margin:8px}
        .btn-outline{background:transparent;border:2px solid var(--accent);color:var(--accent)}
        
        @media(max-width:900px){.sidebar{display:none}.main{margin-left:0}.body{padding:20px}}
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand"><div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><div class="sb-name">Smart Learning</div><div class="sb-role">Results</div></div>
    <ul class="sb-nav"><li><a href="../dashboard/student_dashboard.php"><span class="nav-icon">⊞</span> Dashboard</a></li><li><a href="interest_questionnaire.php"><span class="nav-icon">📋</span> Retake Assessment</a></li></ul>
    <div class="sb-footer"><div class="sb-user"><div class="sb-avatar"><?php echo $avatarLetter; ?></div><div><div class="sb-user-name"><?php echo htmlspecialchars($fullName); ?></div><div class="sb-user-sub">Student</div></div></div><a href="../../logout.php" class="sb-logout">→ Sign out</a></div>
</aside>

<div class="main">
    <div class="topbar"><div class="topbar-breadcrumb">Assessment / <span>Your Results</span></div></div>
    <div class="body">
        <div class="results-container">
            <div class="hero-card">
                <h1 style="font-family:'DM Serif Display',serif;">Your Holland Code</h1>
                <div class="hero-code"><?php echo $hollandCode; ?></div>
                <p>This 3-letter code represents your dominant personality types according to Holland's RIASEC theory.</p>
            </div>
            
            <div class="chart-card">
                <h3 style="margin-bottom:16px;">Your RIASEC Profile Scores</h3>
                <canvas id="riasecChart" height="200"></canvas>
            </div>
            
            <div class="chart-card">
                <h3 style="margin-bottom:16px;">Understanding Your Code</h3>
                <?php foreach ($codeArray as $code): ?>
                    <div style="margin-bottom:20px; padding:16px; background:var(--canvas); border-radius:12px; border-left:4px solid <?php echo $categories[$code]['color']; ?>">
                        <h4 style="color:<?php echo $categories[$code]['color']; ?>"><?php echo $categories[$code]['icon']; ?> <?php echo $categories[$code]['name']; ?> (<?php echo $code; ?>)</h4>
                        <p style="margin-top:6px; color:var(--ink-soft);"><?php echo $categories[$code]['desc']; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="chart-card">
                <h3 style="margin-bottom:16px;">🎯 Suggested Career Paths for <?php echo $hollandCode; ?></h3>
                <div class="career-grid">
                    <?php foreach ($topCareers as $career): ?>
                        <div class="career-tag">💼 <?php echo htmlspecialchars($career); ?></div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:20px; text-align:center;">
                    <a href="../career/recommendations.php" class="btn">View Detailed Career Recommendations →</a>
                    <a href="interest_questionnaire.php?retake=1" class="btn btn-outline">⟳ Retake Assessment</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('riasecChart').getContext('2d');
    new Chart(ctx, {
        type: 'radar',
        data: {
            labels: ['Realistic (R)', 'Investigative (I)', 'Artistic (A)', 'Social (S)', 'Enterprising (E)', 'Conventional (C)'],
            datasets: [{
                label: 'Your Score',
                data: [
                    <?php echo $scores['R'] ?? 0; ?>,
                    <?php echo $scores['I'] ?? 0; ?>,
                    <?php echo $scores['A'] ?? 0; ?>,
                    <?php echo $scores['S'] ?? 0; ?>,
                    <?php echo $scores['E'] ?? 0; ?>,
                    <?php echo $scores['C'] ?? 0; ?>
                ],
                backgroundColor: 'rgba(37, 99, 168, 0.2)',
                borderColor: '#2563a8',
                borderWidth: 2,
                pointBackgroundColor: '#2563a8',
                pointBorderColor: '#fff',
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 50,
                    ticks: { stepSize: 10 }
                }
            }
        }
    });
</script>
</body>
</html>