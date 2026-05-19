<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');
if ($_SESSION['role'] !== 'teacher') redirect_by_role($_SESSION['role']);

$userId = $_SESSION['user_id'];
$quizzes = mysqli_query($conn, "SELECT q.*, (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count, (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id) as attempt_count FROM quizzes q WHERE q.created_by = $userId ORDER BY q.created_at DESC");
$fullName = $_SESSION['full_name'];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
?>
<!DOCTYPE html>
<html>
<head><title>My Quizzes — Smart Learning</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'DM Sans',sans-serif;background:#f5f7f9;color:#0f1117}
    .container{max-width:1200px;margin:0 auto;padding:40px 20px}
    h1{font-family:'DM Serif Display',serif;margin-bottom:8px}
    .quiz-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;margin-top:28px}
    .quiz-card{background:#fff;border-radius:16px;border:1px solid #e4e8ed;padding:20px;transition:transform .2s}
    .quiz-card:hover{transform:translateY(-4px);box-shadow:0 12px 24px rgba(0,0,0,.08)}
    .quiz-title{font-size:1.1rem;font-weight:600;margin-bottom:8px}
    .quiz-meta{font-size:.8rem;color:#4a4d5a;margin-bottom:12px}
    .stats{display:flex;gap:16px;margin:16px 0;font-size:.85rem}
    .badge{padding:4px 10px;border-radius:20px;font-size:.75rem;font-weight:600;display:inline-block}
    .published{background:#e8f5f0;color:#1f7a5c}
    .draft{background:#fef2f2;color:#c0392b}
    .btn{display:inline-block;padding:8px 16px;background:#2563a8;color:#fff;border-radius:8px;text-decoration:none;font-size:.8rem;margin-right:8px}
    .btn-outline{background:transparent;border:1px solid #2563a8;color:#2563a8}
    .btn-sm{padding:6px 12px;font-size:.75rem}
    .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
    .create-btn{background:#2563a8;color:#fff;padding:10px 20px;border-radius:10px;text-decoration:none}
</style>
</head>
<body>
<div class="container">
    <div class="header"><div><h1>My Quizzes</h1><p style="color:#4a4d5a">Manage your quizzes and track student performance</p></div><a href="create_quiz.php" class="create-btn">+ Create New Quiz</a></div>
    <div class="quiz-grid">
        <?php while ($q = mysqli_fetch_assoc($quizzes)): ?>
            <div class="quiz-card">
                <div class="quiz-title"><?php echo htmlspecialchars($q['title']); ?></div>
                <div class="quiz-meta">📖 <?php echo htmlspecialchars($q['subject']); ?> · ⏱️ <?php echo $q['time_limit']; ?> min</div>
                <div class="stats"><span>📝 <?php echo $q['question_count']; ?> questions</span><span>✅ <?php echo $q['attempt_count']; ?> attempts</span></div>
                <div><span class="badge <?php echo $q['is_published'] ? 'published' : 'draft'; ?>"><?php echo $q['is_published'] ? 'Published' : 'Draft'; ?></span></div>
                <div style="margin-top:16px"><a href="create_quiz.php?id=<?php echo $q['id']; ?>" class="btn btn-sm">✏ Edit</a><a href="quiz_results_teacher.php?quiz_id=<?php echo $q['id']; ?>" class="btn btn-outline btn-sm">📊 View Results</a></div>
            </div>
        <?php endwhile; ?>
    </div>
</div>
</body>
</html>