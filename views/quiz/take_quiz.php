<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');
if ($_SESSION['role'] !== 'student') redirect_by_role($_SESSION['role']);

$quizId = (int)($_GET['id'] ?? 0);
if ($quizId === 0) redirect('../learning/materials_list.php');

// Get quiz details
$stmt = mysqli_prepare($conn, "SELECT * FROM quizzes WHERE id = ? AND is_published = 1");
mysqli_stmt_bind_param($stmt, 'i', $quizId);
mysqli_stmt_execute($stmt);
$quiz = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$quiz) redirect('../learning/materials_list.php');

// Check if student already took this quiz
$check = mysqli_query($conn, "SELECT id FROM quiz_attempts WHERE quiz_id = $quizId AND student_id = {$_SESSION['user_id']}");
if (mysqli_num_rows($check) > 0) {
    header("Location: quiz_results.php?quiz_id=$quizId");
    exit();
}

// Get questions
$stmt = mysqli_prepare($conn, "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id");
mysqli_stmt_bind_param($stmt, 'i', $quizId);
mysqli_stmt_execute($stmt);
$questions = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
$role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($quiz['title']); ?> — Smart Learning</title>
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
            --green:#1f7a5c;--red:#c0392b;
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
        
        .quiz-container{max-width:900px;margin:0 auto}
        .timer-card{background:linear-gradient(135deg,#1e3a5f 0%,#2563a8 100%);color:#fff;padding:16px 24px;border-radius:var(--radius);margin-bottom:24px;display:flex;justify-content:space-between;align-items:center}
        .timer{font-size:2rem;font-weight:700;font-family:monospace}
        .question-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:24px;margin-bottom:20px}
        .question-number{font-size:.8rem;color:var(--accent);font-weight:600;margin-bottom:12px}
        .question-text{font-size:1.1rem;font-weight:500;margin-bottom:20px}
        .options{display:flex;flex-direction:column;gap:12px}
        .option{display:flex;align-items:center;gap:12px;padding:12px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;transition:background .2s}
        .option:hover{background:var(--accent-lt);border-color:var(--accent)}
        .option input[type="radio"]{width:18px;height:18px;cursor:pointer}
        .nav-buttons{display:flex;justify-content:space-between;margin-top:24px}
        .btn-primary{background:var(--accent);color:#fff;border:none;padding:12px 28px;border-radius:10px;font-weight:600;cursor:pointer}
        .btn-secondary{background:var(--canvas);border:1.5px solid var(--border);padding:12px 28px;border-radius:10px;cursor:pointer}
        .progress{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:16px;margin-bottom:24px}
        .progress-bar{height:8px;background:var(--border);border-radius:4px;overflow:hidden}
        .progress-fill{height:100%;background:var(--accent);border-radius:4px;transition:width .3s}
        
        @media(max-width:900px){.sidebar{display:none}.main{margin-left:0}.body{padding:20px}}
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand"><div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><div class="sb-name">Smart Learning</div><div class="sb-role">Student</div></div>
    <ul class="sb-nav"><li><a href="../dashboard/student_dashboard.php"><span class="nav-icon">⊞</span> Dashboard</a></li></ul>
    <div class="sb-footer"><div class="sb-user"><div class="sb-avatar"><?php echo $avatarLetter; ?></div><div><div class="sb-user-name"><?php echo htmlspecialchars($fullName); ?></div><div class="sb-user-sub">Student</div></div></div><a href="../../logout.php" class="sb-logout">→ Sign out</a></div>
</aside>

<div class="main">
    <div class="topbar"><div class="topbar-breadcrumb">Quizzes / <span><?php echo htmlspecialchars($quiz['title']); ?></span></div></div>
    <div class="body">
        <div class="quiz-container">
            <div class="timer-card"><span>⏱️ Time Remaining</span><span class="timer" id="timer"><?php echo $quiz['time_limit'] * 60; ?></span></div>
            
            <div class="progress"><div class="progress-bar"><div class="progress-fill" id="progressFill" style="width:0%"></div></div><div style="margin-top:8px;"><span id="answeredCount">0</span> of <?php echo count($questions); ?> answered</div></div>
            
            <form method="POST" action="submit_quiz.php" id="quizForm">
                <input type="hidden" name="quiz_id" value="<?php echo $quizId; ?>">
                <?php foreach ($questions as $index => $q): ?>
                    <div class="question-card" data-qid="<?php echo $q['id']; ?>">
                        <div class="question-number">Question <?php echo $index + 1; ?> of <?php echo count($questions); ?></div>
                        <div class="question-text"><?php echo htmlspecialchars($q['question_text']); ?></div>
                        <div class="options">
                            <?php foreach (['A','B','C','D'] as $opt): ?>
                                <label class="option">
                                    <input type="radio" name="q<?php echo $q['id']; ?>" value="<?php echo $opt; ?>" onchange="markAnswered(<?php echo $q['id']; ?>)">
                                    <span><strong><?php echo $opt; ?>.</strong> <?php echo htmlspecialchars($q['option_' . strtolower($opt)]); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="nav-buttons">
                    <button type="button" class="btn-secondary" onclick="previousQuestion()">← Previous</button>
                    <button type="button" class="btn-primary" onclick="nextQuestion()">Next →</button>
                </div>
                <div style="text-align:center; margin-top:24px;">
                    <button type="submit" class="btn-primary" style="background:var(--green);">📤 Submit Quiz</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let currentQuestion = 0;
    const questions = document.querySelectorAll('.question-card');
    const totalQuestions = questions.length;
    const answered = new Set();
    
    function showQuestion(index) {
        questions.forEach((q, i) => q.style.display = i === index ? 'block' : 'none');
        updateProgress();
    }
    
    function nextQuestion() { if (currentQuestion < totalQuestions - 1) currentQuestion++; showQuestion(currentQuestion); }
    function previousQuestion() { if (currentQuestion > 0) currentQuestion--; showQuestion(currentQuestion); }
    
    function markAnswered(qid) {
        answered.add(qid);
        document.getElementById('answeredCount').innerText = answered.size;
        updateProgress();
    }
    
    function updateProgress() {
        const percent = (answered.size / totalQuestions) * 100;
        document.getElementById('progressFill').style.width = percent + '%';
    }
    
    // Timer
    let timeLeft = <?php echo $quiz['time_limit'] * 60; ?>;
    const timerDisplay = document.getElementById('timer');
    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2,'0')}`;
    }
    const timerInterval = setInterval(() => {
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            document.getElementById('quizForm').submit();
        } else {
            timeLeft--;
            timerDisplay.textContent = formatTime(timeLeft);
        }
    }, 1000);
    
    showQuestion(0);
</script>
</body>
</html>