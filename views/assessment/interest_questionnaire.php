<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');
if ($_SESSION['role'] !== 'student') redirect_by_role($_SESSION['role']);

$userId = $_SESSION['user_id'];
$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
$role = $_SESSION['role'];

// Check if already taken assessment
$checkResult = mysqli_query($conn, "SELECT id, holland_code FROM riasec_results WHERE user_id = $userId ORDER BY taken_at DESC LIMIT 1");
$existingResult = mysqli_fetch_assoc($checkResult);

if ($existingResult && !isset($_GET['retake'])) {
    header("Location: assessment_result.php");
    exit();
}

// RIASEC Questions (6 categories, 10 questions each = 60 questions)
$questions = [
    // Realistic (R) - Hands-on, mechanical, athletic
    ['text' => 'I enjoy working with tools and machinery', 'category' => 'R'],
    ['text' => 'I like to build or repair things', 'category' => 'R'],
    ['text' => 'I prefer outdoor activities over indoor ones', 'category' => 'R'],
    ['text' => 'I enjoy physical activities and sports', 'category' => 'R'],
    ['text' => 'I like working with my hands', 'category' => 'R'],
    ['text' => 'I am interested in how machines work', 'category' => 'R'],
    ['text' => 'I enjoy technical drawings and blueprints', 'category' => 'R'],
    ['text' => 'I prefer practical problems over abstract ones', 'category' => 'R'],
    ['text' => 'I like agriculture and farming', 'category' => 'R'],
    ['text' => 'I enjoy operating vehicles or equipment', 'category' => 'R'],
    
    // Investigative (I) - Scientific, analytical, curious
    ['text' => 'I enjoy solving complex problems', 'category' => 'I'],
    ['text' => 'I like conducting experiments and research', 'category' => 'I'],
    ['text' => 'I am curious about how things work', 'category' => 'I'],
    ['text' => 'I enjoy mathematics and statistics', 'category' => 'I'],
    ['text' => 'I like reading scientific articles', 'category' => 'I'],
    ['text' => 'I prefer working independently on research', 'category' => 'I'],
    ['text' => 'I enjoy analyzing data', 'category' => 'I'],
    ['text' => 'I am interested in medical or health sciences', 'category' => 'I'],
    ['text' => 'I like to question existing theories', 'category' => 'I'],
    ['text' => 'I enjoy learning new technologies', 'category' => 'I'],
    
    // Artistic (A) - Creative, expressive, imaginative
    ['text' => 'I enjoy creative writing and storytelling', 'category' => 'A'],
    ['text' => 'I like drawing, painting, or design', 'category' => 'A'],
    ['text' => 'I enjoy music, theatre, or dance', 'category' => 'A'],
    ['text' => 'I like to express myself through art', 'category' => 'A'],
    ['text' => 'I enjoy photography and videography', 'category' => 'A'],
    ['text' => 'I prefer unstructured, creative environments', 'category' => 'A'],
    ['text' => 'I like designing websites or graphics', 'category' => 'A'],
    ['text' => 'I enjoy fashion and interior design', 'category' => 'A'],
    ['text' => 'I like to think outside the box', 'category' => 'A'],
    ['text' => 'I enjoy performing or public speaking', 'category' => 'A'],
    
    // Social (S) - Helping, teaching, counseling
    ['text' => 'I enjoy helping others solve their problems', 'category' => 'S'],
    ['text' => 'I like teaching or training people', 'category' => 'S'],
    ['text' => 'I prefer teamwork over working alone', 'category' => 'S'],
    ['text' => 'I enjoy volunteering for community service', 'category' => 'S'],
    ['text' => 'I am good at listening and empathizing', 'category' => 'S'],
    ['text' => 'I like working with children or elderly', 'category' => 'S'],
    ['text' => 'I enjoy counseling and mentoring', 'category' => 'S'],
    ['text' => 'I prefer jobs that involve social interaction', 'category' => 'S'],
    ['text' => 'I like organizing group activities', 'category' => 'S'],
    ['text' => 'I am interested in psychology and human behavior', 'category' => 'S'],
    
    // Enterprising (E) - Persuasive, leadership, business
    ['text' => 'I enjoy leading and managing people', 'category' => 'E'],
    ['text' => 'I like to persuade others to my point of view', 'category' => 'E'],
    ['text' => 'I am interested in business and entrepreneurship', 'category' => 'E'],
    ['text' => 'I enjoy sales and marketing activities', 'category' => 'E'],
    ['text' => 'I like taking risks for potential rewards', 'category' => 'E'],
    ['text' => 'I enjoy public speaking and presenting', 'category' => 'E'],
    ['text' => 'I am ambitious and goal-oriented', 'category' => 'E'],
    ['text' => 'I like to start my own projects', 'category' => 'E'],
    ['text' => 'I enjoy negotiating and debating', 'category' => 'E'],
    ['text' => 'I prefer leadership roles in groups', 'category' => 'E'],
    
    // Conventional (C) - Organized, detail-oriented, clerical
    ['text' => 'I enjoy organizing and maintaining records', 'category' => 'C'],
    ['text' => 'I like to follow established procedures', 'category' => 'C'],
    ['text' => 'I am good with numbers and data entry', 'category' => 'C'],
    ['text' => 'I prefer structured, predictable environments', 'category' => 'C'],
    ['text' => 'I enjoy working with spreadsheets and databases', 'category' => 'C'],
    ['text' => 'I like to keep things tidy and organized', 'category' => 'C'],
    ['text' => 'I prefer clear instructions and guidelines', 'category' => 'C'],
    ['text' => 'I enjoy administrative tasks', 'category' => 'C'],
    ['text' => 'I am detail-oriented and thorough', 'category' => 'C'],
    ['text' => 'I like to create systems and processes', 'category' => 'C'],
];

// Shuffle questions for variety
shuffle($questions);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assessment'])) {
    $scores = ['R' => 0, 'I' => 0, 'A' => 0, 'S' => 0, 'E' => 0, 'C' => 0];
    $answers = [];
    
    foreach ($questions as $index => $q) {
        $answer = (int)($_POST['q_' . $index] ?? 0);
        $answers[$index] = $answer;
        $scores[$q['category']] += $answer;
    }
    
    // Calculate Holland Code (top 3 categories)
    arsort($scores);
    $hollandCode = implode('', array_slice(array_keys($scores), 0, 3));
    $answersJson = json_encode($answers);
    
    // Delete old results
    mysqli_query($conn, "DELETE FROM riasec_results WHERE user_id = $userId");
    
    // Save new results
    $stmt = mysqli_prepare($conn, "INSERT INTO riasec_results (user_id, holland_code, answers_json, scores_json, taken_at) VALUES (?, ?, ?, ?, NOW())");
    $scoresJson = json_encode($scores);
    mysqli_stmt_bind_param($stmt, 'isss', $userId, $hollandCode, $answersJson, $scoresJson);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    header("Location: assessment_result.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interest Assessment — Smart Learning</title>
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
        .topbar-breadcrumb span{color:var(--ink);font-weight:600}
        .body{padding:32px 36px}
        
        .assessment-container{max-width:800px;margin:0 auto}
        .progress-header{background:var(--white);border-radius:var(--radius);padding:20px;margin-bottom:24px;border:1.5px solid var(--border)}
        .progress-bar{height:8px;background:var(--border);border-radius:4px;overflow:hidden;margin:12px 0}
        .progress-fill{height:100%;background:var(--accent);border-radius:4px;transition:width .3s}
        .question-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:28px;margin-bottom:20px;display:none}
        .question-card.active{display:block;animation:fadeIn .3s ease}
        .question-number{font-size:.8rem;color:var(--accent);font-weight:600;margin-bottom:12px}
        .question-text{font-size:1.1rem;font-weight:500;margin-bottom:24px;line-height:1.5}
        .options{display:flex;flex-direction:column;gap:12px}
        .option{display:flex;align-items:center;gap:12px;padding:14px 16px;border:1.5px solid var(--border);border-radius:12px;cursor:pointer;transition:all .2s}
        .option:hover{background:var(--accent-lt);border-color:var(--accent)}
        .option input[type="radio"]{width:18px;height:18px;cursor:pointer;accent-color:var(--accent)}
        .option.selected{background:var(--accent-lt);border-color:var(--accent)}
        .likert{display:flex;justify-content:space-between;margin-top:12px;font-size:.7rem;color:var(--ink-faint);padding:0 8px}
        .nav-buttons{display:flex;justify-content:space-between;margin-top:24px}
        .btn-primary{background:var(--accent);color:#fff;border:none;padding:12px 28px;border-radius:10px;font-weight:600;cursor:pointer}
        .btn-secondary{background:var(--canvas);border:1.5px solid var(--border);padding:12px 28px;border-radius:10px;cursor:pointer}
        
        @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        @media(max-width:900px){.sidebar{display:none}.main{margin-left:0}.body{padding:20px}}
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand"><div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><div class="sb-name">Smart Learning</div><div class="sb-role">Assessment</div></div>
    <ul class="sb-nav"><li><a href="../dashboard/student_dashboard.php"><span class="nav-icon">⊞</span> Dashboard</a></li><li><a href="interest_questionnaire.php" class="active"><span class="nav-icon">📋</span> Take Assessment</a></li></ul>
    <div class="sb-footer"><div class="sb-user"><div class="sb-avatar"><?php echo $avatarLetter; ?></div><div><div class="sb-user-name"><?php echo htmlspecialchars($fullName); ?></div><div class="sb-user-sub">Student</div></div></div><a href="../../logout.php" class="sb-logout">→ Sign out</a></div>
</aside>

<div class="main">
    <div class="topbar"><div class="topbar-breadcrumb">Assessment / <span>RIASEC Interest Inventory</span></div></div>
    <div class="body">
        <div class="assessment-container">
            <div class="progress-header">
                <h2 style="font-family:'DM Serif Display',serif;">Career Interest Assessment</h2>
                <p style="color:var(--ink-faint); margin-top:4px;">Rate how much you agree with each statement to discover your Holland Code.</p>
                <div class="progress-bar"><div class="progress-fill" id="progressFill" style="width:0%"></div></div>
                <div style="display:flex; justify-content:space-between; margin-top:8px;"><span>Question <span id="currentQ">1</span> of <span id="totalQ"><?php echo count($questions); ?></span></span><span id="answeredCount">0 answered</span></div>
            </div>
            
            <form method="POST" id="assessmentForm">
                <?php foreach ($questions as $index => $q): ?>
                    <div class="question-card" data-qidx="<?php echo $index; ?>" data-category="<?php echo $q['category']; ?>">
                        <div class="question-number">Category: <?php 
                            $catNames = ['R'=>'🔧 Realistic','I'=>'🔬 Investigative','A'=>'🎨 Artistic','S'=>'🤝 Social','E'=>'💼 Enterprising','C'=>'📊 Conventional'];
                            echo $catNames[$q['category']];
                        ?></div>
                        <div class="question-text"><?php echo htmlspecialchars($q['text']); ?></div>
                        <div class="options">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label class="option">
                                    <input type="radio" name="q_<?php echo $index; ?>" value="<?php echo $i; ?>" onchange="markAnswered(<?php echo $index; ?>)">
                                    <span><?php echo $i; ?> - 
                                        <?php 
                                            if ($i == 1) echo 'Strongly Disagree';
                                            elseif ($i == 2) echo 'Disagree';
                                            elseif ($i == 3) echo 'Neutral';
                                            elseif ($i == 4) echo 'Agree';
                                            else echo 'Strongly Agree';
                                        ?>
                                    </span>
                                </label>
                            <?php endfor; ?>
                        </div>
                        <div class="likert"><span>Strongly<br>Disagree</span><span>Neutral</span><span>Strongly<br>Agree</span></div>
                    </div>
                <?php endforeach; ?>
                
                <div class="nav-buttons">
                    <button type="button" class="btn-secondary" id="prevBtn" onclick="prevQuestion()">← Previous</button>
                    <button type="button" class="btn-primary" id="nextBtn" onclick="nextQuestion()">Next →</button>
                </div>
                <div style="text-align:center; margin-top:24px;">
                    <button type="submit" name="submit_assessment" class="btn-primary" id="submitBtn" style="background:var(--green); display:none;">📊 Submit & See Results</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const totalQuestions = <?php echo count($questions); ?>;
    let currentIndex = 0;
    let answers = new Array(totalQuestions).fill(null);
    const questionsDiv = document.querySelectorAll('.question-card');
    
    function showQuestion(index) {
        questionsDiv.forEach((q, i) => {
            q.classList.toggle('active', i === index);
        });
        document.getElementById('currentQ').innerText = index + 1;
        updateProgress();
    }
    
    function markAnswered(idx) {
        const selected = document.querySelector(`input[name="q_${idx}"]:checked`);
        if (selected) {
            answers[idx] = parseInt(selected.value);
        }
        const answeredCount = answers.filter(a => a !== null).length;
        document.getElementById('answeredCount').innerText = answeredCount;
        updateProgress();
        
        // Show submit button when all answered
        if (answeredCount === totalQuestions) {
            document.getElementById('submitBtn').style.display = 'block';
            document.getElementById('nextBtn').style.display = 'none';
        }
    }
    
    function updateProgress() {
        const answered = answers.filter(a => a !== null).length;
        const percent = (answered / totalQuestions) * 100;
        document.getElementById('progressFill').style.width = percent + '%';
    }
    
    function nextQuestion() {
        if (currentIndex < totalQuestions - 1) {
            currentIndex++;
            showQuestion(currentIndex);
        }
    }
    
    function prevQuestion() {
        if (currentIndex > 0) {
            currentIndex--;
            showQuestion(currentIndex);
        }
    }
    
    // Restore answers if navigating back
    function restoreAnswers() {
        for (let i = 0; i < totalQuestions; i++) {
            if (answers[i] !== null) {
                const radio = document.querySelector(`input[name="q_${i}"][value="${answers[i]}"]`);
                if (radio) radio.checked = true;
            }
        }
    }
    
    showQuestion(0);
    setInterval(restoreAnswers, 100);
</script>
</body>
</html>