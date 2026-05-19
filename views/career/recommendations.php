<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');

$userId = $_SESSION['user_id'];
$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
$role = $_SESSION['role'];

// Get student profile and Holland code
$profile = mysqli_fetch_assoc(mysqli_query($conn, "SELECT skills, interests, holland_code FROM student_profiles WHERE user_id = $userId"));
$hollandCode = $profile['holland_code'] ?? '';

// Get career recommendations based on skills and Holland code
$recommendations = getCareerRecommendations($userId);

// Also get careers matching Holland code
$hollandCareers = [];
if (!empty($hollandCode)) {
    $hollandQuery = "SELECT * FROM career_paths WHERE holland_codes LIKE '%$hollandCode%' OR holland_codes LIKE '%" . substr($hollandCode, 0, 2) . "%' ORDER BY id LIMIT 10";
    $hollandResult = mysqli_query($conn, $hollandQuery);
    while ($career = mysqli_fetch_assoc($hollandResult)) {
        $hollandCareers[] = $career;
    }
}

// Save a career to wishlist
if (isset($_GET['save']) && isset($_GET['career_id'])) {
    $careerId = (int)$_GET['career_id'];
    $check = mysqli_query($conn, "SELECT id FROM saved_careers WHERE user_id = $userId AND career_id = $careerId");
    if (mysqli_num_rows($check) == 0) {
        mysqli_query($conn, "INSERT INTO saved_careers (user_id, career_id) VALUES ($userId, $careerId)");
    }
    header("Location: recommendations.php?saved=1");
    exit();
}

// Remove from wishlist
if (isset($_GET['remove']) && isset($_GET['career_id'])) {
    $careerId = (int)$_GET['career_id'];
    mysqli_query($conn, "DELETE FROM saved_careers WHERE user_id = $userId AND career_id = $careerId");
    header("Location: recommendations.php?removed=1");
    exit();
}

// Get saved careers
$savedCareers = [];
$savedQuery = mysqli_query($conn, "SELECT career_id FROM saved_careers WHERE user_id = $userId");
while ($saved = mysqli_fetch_assoc($savedQuery)) {
    $savedCareers[] = $saved['career_id'];
}

$greeting = date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Recommendations — Smart Learning</title>
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
        .topbar{background:var(--white);border-bottom:1px solid var(--border);padding:16px 36px;display:flex;align-items:center;position:sticky;top:0;z-index:50}
        .topbar-breadcrumb{font-size:.8rem;color:var(--ink-faint)}
        .topbar-breadcrumb span{color:var(--ink);font-weight:600}
        .body{padding:32px 36px}

        .page-header{margin-bottom:28px}
        .page-title{font-family:'DM Serif Display',serif;font-size:1.8rem;letter-spacing:-.02em}
        .page-sub{font-size:.85rem;color:var(--ink-faint);margin-top:4px}

        .alert{padding:14px 18px;border-radius:12px;margin-bottom:24px}
        .alert-success{background:#e8f5f0;border:1px solid #c5e0d4;color:var(--green)}
        .alert-info{background:var(--accent-lt);border:1px solid #c5d8f0;color:var(--accent)}

        .holland-badge{background:linear-gradient(135deg,#1e3a5f 0%,#2563a8 100%);color:#fff;padding:12px 20px;border-radius:var(--radius);margin-bottom:28px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap}
        .holland-code{font-family:'DM Serif Display',serif;font-size:2rem;letter-spacing:4px}
        .career-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;margin-bottom:32px}
        .career-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);overflow:hidden;transition:transform .2s,box-shadow .2s}
        .career-card:hover{transform:translateY(-4px);box-shadow:0 12px 24px rgba(0,0,0,.08)}
        .card-header{background:var(--accent-lt);padding:16px 20px;border-bottom:1.5px solid var(--border)}
        .card-header h3{font-size:1.1rem;font-weight:600;margin-bottom:4px}
        .card-body{padding:20px}
        .match-score{display:inline-block;background:var(--green);color:#fff;padding:4px 12px;border-radius:20px;font-size:.75rem;font-weight:600;margin-bottom:12px}
        .info-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:.85rem}
        .info-label{font-weight:600;color:var(--ink-soft)}
        .info-value{color:var(--ink)}
        .card-actions{display:flex;gap:10px;margin-top:16px}
        .btn-sm{padding:8px 16px;border-radius:8px;text-decoration:none;font-size:.78rem;font-weight:600}
        .btn-primary{background:var(--accent);color:#fff}
        .btn-outline{background:transparent;border:1.5px solid var(--accent);color:var(--accent)}
        .btn-saved{background:var(--green);color:#fff}
        
        .section-title{font-size:.85rem;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px}
        .empty-state{text-align:center;padding:60px 20px;color:var(--ink-faint)}
        .empty-state .icon{font-size:3rem;margin-bottom:16px}
        
        @media(max-width:900px){.sidebar{display:none}.main{margin-left:0}.body{padding:20px}.career-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand"><div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><div class="sb-name">Smart Learning</div><div class="sb-role">Career Guidance</div></div>
    <div class="sb-section">Explore</div>
    <ul class="sb-nav">
        <li><a href="../dashboard/<?php echo $role; ?>_dashboard.php"><span class="nav-icon">⊞</span> Dashboard</a></li>
        <li><a href="recommendations.php" class="active"><span class="nav-icon">⭐</span> Recommendations</a></li>
        <li><a href="compare_careers.php"><span class="nav-icon">🔄</span> Compare Careers</a></li>
        <li><a href="../assessment/interest_questionnaire.php"><span class="nav-icon">📋</span> Take Assessment</a></li>
    </ul>
    <div class="sb-footer">
        <div class="sb-user"><div class="sb-avatar"><?php echo $avatarLetter; ?></div><div><div class="sb-user-name"><?php echo htmlspecialchars($fullName); ?></div><div class="sb-user-sub"><?php echo ucfirst($role); ?></div></div></div>
        <a href="../../logout.php" class="sb-logout">→ Sign out</a>
    </div>
</aside>

<div class="main">
    <div class="topbar"><div class="topbar-breadcrumb">Career / <span>Recommendations</span></div></div>
    <div class="body">
        <div class="page-header">
            <h1 class="page-title">Career Recommendations</h1>
            <p class="page-sub">Personalized career matches based on your skills, interests, and personality</p>
        </div>

        <?php if (isset($_GET['saved'])): ?>
            <div class="alert alert-success">✅ Career saved to your wishlist!</div>
        <?php endif; ?>
        <?php if (isset($_GET['removed'])): ?>
            <div class="alert alert-info">🗑 Career removed from your wishlist.</div>
        <?php endif; ?>
        <?php if (empty($profile['skills']) && empty($profile['holland_code'])): ?>
            <div class="alert alert-info">📝 Complete your profile and take the RIASEC assessment for better recommendations. <a href="../profile.php" style="color:var(--accent);">Update Profile →</a></div>
        <?php endif; ?>
        <?php if (!empty($hollandCode)): ?>
            <div class="holland-badge">
                <div><strong>Your Holland Code</strong><br>Based on your RIASEC assessment</div>
                <div class="holland-code"><?php echo $hollandCode; ?></div>
            </div>
        <?php endif; ?>

        <!-- AI-Powered Recommendations -->
        <div class="section-title">🎯 AI-Powered Matches (Based on Your Skills)</div>
        <?php if (!empty($recommendations)): ?>
            <div class="career-grid">
                <?php foreach ($recommendations as $rec): 
                    $career = $rec['career'];
                    $score = $rec['score'];
                    $isSaved = in_array($career['id'], $savedCareers);
                ?>
                    <div class="career-card">
                        <div class="card-header"><h3><?php echo htmlspecialchars($career['career_name']); ?></h3></div>
                        <div class="card-body">
                            <div class="match-score"><?php echo $score; ?>% Match</div>
                            <div class="info-row"><span class="info-label">📚 Required Skills</span><span class="info-value"><?php echo htmlspecialchars($career['required_skills']); ?></span></div>
                            <div class="info-row"><span class="info-label">💰 Salary Range</span><span class="info-value"><?php echo htmlspecialchars($career['salary_range']); ?></span></div>
                            <div class="info-row"><span class="info-label">📈 Growth Rate</span><span class="info-value"><?php echo htmlspecialchars($career['growth_rate']); ?></span></div>
                            <div class="info-row"><span class="info-label">🎓 Education</span><span class="info-value"><?php echo htmlspecialchars($career['education_required']); ?></span></div>
                            <div class="card-actions">
                                <a href="career_detail.php?id=<?php echo $career['id']; ?>" class="btn-sm btn-primary">View Details →</a>
                                <?php if ($isSaved): ?>
                                    <a href="recommendations.php?remove=1&career_id=<?php echo $career['id']; ?>" class="btn-sm btn-outline">❤️ Saved</a>
                                <?php else: ?>
                                    <a href="recommendations.php?save=1&career_id=<?php echo $career['id']; ?>" class="btn-sm btn-outline">🤍 Save</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><div class="icon">🎯</div><p>No recommendations yet.<br><a href="../profile.php" style="color:var(--accent);">Complete your profile</a> to get matched.</p></div>
        <?php endif; ?>

        <!-- Holland Code Based Recommendations -->
        <?php if (!empty($hollandCareers)): ?>
            <div class="section-title">🧠 Personality-Based Matches (Holland Code: <?php echo $hollandCode; ?>)</div>
            <div class="career-grid">
                <?php foreach ($hollandCareers as $career): 
                    $isSaved = in_array($career['id'], $savedCareers);
                ?>
                    <div class="career-card">
                        <div class="card-header"><h3><?php echo htmlspecialchars($career['career_name']); ?></h3></div>
                        <div class="card-body">
                            <div class="info-row"><span class="info-label">💰 Salary Range</span><span class="info-value"><?php echo htmlspecialchars($career['salary_range']); ?></span></div>
                            <div class="info-row"><span class="info-label">📈 Growth Rate</span><span class="info-value"><?php echo htmlspecialchars($career['growth_rate']); ?></span></div>
                            <div class="card-actions">
                                <a href="career_detail.php?id=<?php echo $career['id']; ?>" class="btn-sm btn-primary">View Details →</a>
                                <?php if ($isSaved): ?>
                                    <a href="recommendations.php?remove=1&career_id=<?php echo $career['id']; ?>" class="btn-sm btn-outline">❤️ Saved</a>
                                <?php else: ?>
                                    <a href="recommendations.php?save=1&career_id=<?php echo $career['id']; ?>" class="btn-sm btn-outline">🤍 Save</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>