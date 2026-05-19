<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');

$quizId = (int)($_GET['quiz_id'] ?? 0);
if ($quizId === 0) redirect('../learning/materials_list.php');

$studentId = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get quiz details
$quiz = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM quizzes WHERE id = $quizId"));

// Get attempt
$attempt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM quiz_attempts WHERE quiz_id = $quizId AND student_id = $studentId ORDER BY attempted_at DESC LIMIT 1"));

if (!$attempt) redirect('take_quiz.php?id=' . $quizId);

$percentage = ($attempt['score'] / $attempt['total_questions']) * 100;
$passing = $percentage >= 50;

$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results — Smart Learning</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'DM Sans',sans-serif;background:#f5f7f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .results-card{max-width:600px;width:100%;background:#fff;border-radius:20px;padding:40px;text-align:center;box-shadow:0 20px 40px rgba(0,0,0,.08)}
        .score-circle{width:180px;height:180px;margin:0 auto 24px;background:conic-gradient(#2563a8 0deg <?php echo $percentage * 3.6; ?>deg, #e4e8ed <?php echo $percentage * 3.6; ?>deg 360deg);border-radius:50%;display:flex;align-items:center;justify-content:center;position:relative}
        .score-inner{width:140px;height:140px;background:#fff;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center}
        .score-number{font-size:3rem;font-weight:700;color:#0f1117}
        .score-label{color:#4a4d5a;font-size:.9rem}
        .pass{border-left:4px solid #1f7a5c;background:#e8f5f0;padding:16px;margin:24px 0}
        .fail{border-left:4px solid #c0392b;background:#fef2f2;padding:16px;margin:24px 0}
        .btn{display:inline-block;padding:12px 28px;background:#2563a8;color:#fff;border-radius:12px;text-decoration:none;font-weight:600;margin:8px}
        .btn-outline{background:transparent;border:2px solid #2563a8;color:#2563a8}
        h1{font-family:'DM Serif Display',serif;margin-bottom:8px}
    </style>
</head>
<body>
<div class="results-card">
    <h1>Quiz Results</h1>
    <p style="color:#4a4d5a; margin-bottom:24px;"><?php echo htmlspecialchars($quiz['title']); ?></p>
    
    <div class="score-circle"><div class="score-inner"><div class="score-number"><?php echo $attempt['score']; ?>/<?php echo $attempt['total_questions']; ?></div><div class="score-label">Correct Answers</div></div></div>
    
    <div style="font-size:2rem; font-weight:700; margin:8px 0;"><?php echo round($percentage, 1); ?>%</div>
    
    <?php if ($passing): ?>
        <div class="pass">✅ Great job! You passed the quiz. Keep up the good work!</div>
    <?php else: ?>
        <div class="fail">📚 You didn't pass this time. Review the material and try again!</div>
    <?php endif; ?>
    
    <div>
        <a href="../dashboard/student_dashboard.php" class="btn">🏠 Back to Dashboard</a>
        <a href="../learning/materials_list.php" class="btn btn-outline">📚 Browse Materials</a>
    </div>
</div>
</body>
</html>