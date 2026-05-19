<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');

$careerId = (int)($_GET['id'] ?? 0);
if ($careerId === 0) redirect('recommendations.php');

$userId = $_SESSION['user_id'];
$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
$role = $_SESSION['role'];

// Get career details
$career = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM career_paths WHERE id = $careerId"));
if (!$career) redirect('recommendations.php');

// Get student profile for comparison
$profile = mysqli_fetch_assoc(mysqli_query($conn, "SELECT skills, holland_code FROM student_profiles WHERE user_id = $userId"));
$userSkills = explode(',', $profile['skills'] ?? '');
$careerSkills = explode(',', $career['required_skills'] ?? '');
$matchingSkills = array_intersect($userSkills, $careerSkills);

// Check if saved
$isSaved = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM saved_careers WHERE user_id = $userId AND career_id = $careerId")) > 0;

// Get similar careers
$similarCareers = mysqli_query($conn, "SELECT id, career_name, salary_range FROM career_paths WHERE id != $careerId AND (category = '{$career['category']}' OR holland_codes LIKE '%" . substr($profile['holland_code'] ?? '', 0, 2) . "%') LIMIT 4");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($career['career_name']); ?> — Smart Learning</title>
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
        
        .main{margin-left:var(--sidebar);min-height:100vh}
        .topbar{background:var(--white);border-bottom:1px solid var(--border);padding:16px 36px;}
        .topbar-breadcrumb{font-size:.8rem;color:var(--ink-faint)}
        .topbar-breadcrumb a{color:var(--accent);text-decoration:none}
        .topbar-breadcrumb span{color:var(--ink);font-weight:600}
        .body{padding:32px 36px}
        
        .career-container{display:grid;grid-template-columns:1fr 340px;gap:28px}
        .main-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);overflow:hidden}
        .hero-section{background:linear-gradient(135deg,#1e3a5f 0%,#2563a8 100%);color:#fff;padding:32px 36px}
        .hero-section h1{font-family:'DM Serif Display',serif;font-size:2rem;margin-bottom:8px}
        .info-grid{padding:28px 36px}
        .info-row{display:flex;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border)}
        .info-label{font-weight:600;color:var(--ink-soft)}
        .info-value{color:var(--ink);text-align:right}
        .skills-list{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
        .skill-tag{background:var(--accent-lt);padding:6px 14px;border-radius:20px;font-size:.8rem}
        .match-badge{display:inline-block;background:var(--green);color:#fff;padding:6px 16px;border-radius:20px;margin-top:12px}
        
        .sidebar-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:24px;margin-bottom:20px}
        .sidebar-card h3{margin-bottom:16px}
        .btn{display:block;width:100%;padding:12px;text-align:center;border-radius:10px;text-decoration:none;font-weight:600;margin-bottom:10px}
        .btn-primary{background:var(--accent);color:#fff}
        .btn-outline{background:transparent;border:1.5px solid var(--accent);color:var(--accent)}
        .similar-item{padding:12px 0;border-bottom:1px solid var(--border);text-decoration:none;display:block}
        .similar-item:last-child{border-bottom:none}
        
        @media(max-width:900px){.sidebar{display:none}.main{margin-left:0}.body{padding:20px}.career-container{grid-template-columns:1fr}}
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand"><div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><div class="sb-name">Smart Learning</div><div class="sb-role">Career Guide</div></div>
    <ul class="sb-nav"><li><a href="../dashboard/<?php echo $role; ?>_dashboard.php"><span class="nav-icon">⊞</span> Dashboard</a></li><li><a href="recommendations.php"><span class="nav-icon">⭐</span> Recommendations</a></li><li><a href="compare_careers.php"><span class="nav-icon">🔄</span> Compare Careers</a></li></ul>
    <div class="sb-footer"><div class="sb-user"><div class="sb-avatar"><?php echo $avatarLetter; ?></div><div><div class="sb-user-name"><?php echo htmlspecialchars($fullName); ?></div><div class="sb-user-sub"><?php echo ucfirst($role); ?></div></div></div><a href="../../logout.php" class="sb-logout">→ Sign out</a></div>
</aside>

<div class="main">
    <div class="topbar"><div class="topbar-breadcrumb"><a href="recommendations.php">Recommendations</a> / <span><?php echo htmlspecialchars($career['career_name']); ?></span></div></div>
    <div class="body">
        <div class="career-container">
            <div class="main-card">
                <div class="hero-section">
                    <h1><?php echo htmlspecialchars($career['career_name']); ?></h1>
                    <p><?php echo htmlspecialchars($career['description']); ?></p>
                    <?php if (!empty($matchingSkills)): ?>
                        <div class="match-badge">🎯 <?php echo count($matchingSkills); ?> skills match your profile!</div>
                    <?php endif; ?>
                </div>
                <div class="info-grid">
                    <div class="info-row"><span class="info-label">📚 Required Skills & Competencies</span><span class="info-value"><div class="skills-list"><?php foreach (explode(',', $career['required_skills']) as $skill): ?><span class="skill-tag"><?php echo htmlspecialchars(trim($skill)); ?></span><?php endforeach; ?></div></span></div>
                    <div class="info-row"><span class="info-label">💰 Typical Salary Range</span><span class="info-value"><?php echo htmlspecialchars($career['salary_range']); ?></span></div>
                    <div class="info-row"><span class="info-label">📈 Job Growth Outlook</span><span class="info-value"><?php echo htmlspecialchars($career['growth_rate']); ?></span></div>
                    <div class="info-row"><span class="info-label">🎓 Education & Training</span><span class="info-value"><?php echo htmlspecialchars($career['education_required']); ?></span></div>
                    <div class="info-row"><span class="info-label">🧠 Holland Code Match</span><span class="info-value"><?php echo htmlspecialchars($career['holland_codes']); ?></span></div>
                </div>
            </div>
            
            <div>
                <div class="sidebar-card">
                    <h3>Quick Actions</h3>
                    <?php if ($isSaved): ?>
                        <a href="recommendations.php?remove=1&career_id=<?php echo $careerId; ?>" class="btn btn-outline">❤️ Saved to Wishlist</a>
                    <?php else: ?>
                        <a href="recommendations.php?save=1&career_id=<?php echo $careerId; ?>" class="btn btn-primary">🤍 Save to My Careers</a>
                    <?php endif; ?>
                    <a href="compare_careers.php?careers[]=<?php echo $careerId; ?>" class="btn btn-outline">🔄 Add to Compare</a>
                </div>
                
                <?php if (mysqli_num_rows($similarCareers) > 0): ?>
                    <div class="sidebar-card">
                        <h3>Similar Careers</h3>
                        <?php while ($similar = mysqli_fetch_assoc($similarCareers)): ?>
                            <a href="career_detail.php?id=<?php echo $similar['id']; ?>" class="similar-item">
                                <strong><?php echo htmlspecialchars($similar['career_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($similar['salary_range']); ?></small>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>