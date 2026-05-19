<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');
if ($_SESSION['role'] !== 'teacher') redirect_by_role($_SESSION['role']);

$userId = $_SESSION['user_id'];
$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
$error = '';
$success = '';
$quizId = null;

// Handle quiz creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize_input($_POST['title'] ?? '');
    $subject = sanitize_input($_POST['subject'] ?? '');
    $time_limit = (int)($_POST['time_limit'] ?? 30);
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    $quiz_id = (int)($_POST['quiz_id'] ?? 0);
    
    if (empty($title)) {
        $error = 'Quiz title is required.';
    } elseif (empty($subject)) {
        $error = 'Subject is required.';
    } else {
        if ($quiz_id > 0) {
            // Update existing quiz
            $stmt = mysqli_prepare($conn, "UPDATE quizzes SET title = ?, subject = ?, time_limit = ?, is_published = ? WHERE id = ? AND created_by = ?");
            mysqli_stmt_bind_param($stmt, 'sssiii', $title, $subject, $time_limit, $is_published, $quiz_id, $userId);
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Quiz updated successfully!';
            } else {
                $error = 'Failed to update quiz.';
            }
        } else {
            // Create new quiz
            $stmt = mysqli_prepare($conn, "INSERT INTO quizzes (title, subject, time_limit, is_published, created_by) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sssii', $title, $subject, $time_limit, $is_published, $userId);
            if (mysqli_stmt_execute($stmt)) {
                $quizId = mysqli_insert_id($conn);
                $success = 'Quiz created! Now add questions.';
            } else {
                $error = 'Failed to create quiz.';
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// Get quiz data if editing
$editQuiz = null;
if (isset($_GET['id'])) {
    $editId = (int)$_GET['id'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM quizzes WHERE id = ? AND created_by = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $editId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $editQuiz = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($editQuiz) {
        $quizId = $editQuiz['id'];
    }
}

// Get existing questions if editing
$questions = [];
if ($quizId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id");
    mysqli_stmt_bind_param($stmt, 'i', $quizId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $questions = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quiz — Smart Learning</title>
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
            --green:#1f7a5c;--red:#c0392b;--amber:#b87c10;
        }
        html,body{min-height:100vh;font-family:'DM Sans',sans-serif;background:var(--canvas);color:var(--ink)}

        .sidebar{
            position:fixed;top:0;left:0;height:100vh;width:var(--sidebar);
            background:#1a2235;display:flex;flex-direction:column;z-index:100;
            overflow-y:auto;
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

        .sb-footer{margin-top:auto;padding:16px 10px;border-top:1px solid rgba(255,255,255,.06)}
        .sb-user{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:9px;}
        .sb-avatar{width:36px;height:36px;border-radius:10px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;color:#fff}
        .sb-user-name{font-size:.85rem;font-weight:600;color:#fff}
        .sb-user-sub{font-size:.7rem;color:rgba(255,255,255,.3)}
        .sb-logout{color:rgba(255,255,255,.3);text-decoration:none;font-size:.8rem;padding:8px 14px;display:block;border-radius:8px;margin-top:8px}
        .sb-logout:hover{color:#fff}

        .main{margin-left:var(--sidebar);min-height:100vh}
        .topbar{
            background:var(--white);border-bottom:1px solid var(--border);
            padding:16px 36px;display:flex;align-items:center;
            position:sticky;top:0;z-index:50;
        }
        .topbar-breadcrumb{font-size:.8rem;color:var(--ink-faint)}
        .topbar-breadcrumb span{color:var(--ink);font-weight:600}
        .body{padding:32px 36px}

        .form-container{max-width:900px;margin:0 auto}
        .form-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:32px;margin-bottom:28px}
        .form-header{margin-bottom:24px}
        .form-header h1{font-family:'DM Serif Display',serif;font-size:1.8rem;margin-bottom:8px}
        .form-header p{color:var(--ink-faint)}

        .form-group{margin-bottom:20px}
        label{display:block;font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-faint);margin-bottom:8px}
        .form-control{
            width:100%;padding:12px 14px;border:1.5px solid var(--border);border-radius:10px;
            font-family:'DM Sans',sans-serif;font-size:.9rem;background:var(--canvas);
            transition:border-color .2s;
        }
        .form-control:focus{outline:none;border-color:var(--accent)}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:20px}

        .alert{padding:14px 18px;border-radius:12px;margin-bottom:24px}
        .alert-success{background:#e8f5f0;border:1px solid #c5e0d4;color:var(--green)}
        .alert-danger{background:#fef2f2;border:1px solid #fecaca;color:var(--red)}

        .btn-primary{background:var(--accent);color:#fff;border:none;padding:12px 24px;border-radius:10px;font-weight:600;cursor:pointer}
        .btn-primary:hover{background:#1a4f8f}
        .btn-secondary{background:var(--canvas);border:1.5px solid var(--border);padding:12px 24px;border-radius:10px;font-weight:600;cursor:pointer}
        .btn-danger{background:var(--red);color:#fff;border:none;padding:6px 12px;border-radius:6px;cursor:pointer}
        
        .question-card{background:var(--canvas);border-radius:12px;padding:20px;margin-bottom:16px;border:1.5px solid var(--border)}
        .question-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
        .question-title{font-weight:600}
        
        .options-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:12px 0}
        .option-input{padding:10px;border:1.5px solid var(--border);border-radius:8px}
        .correct-label{display:flex;align-items:center;gap:8px;margin-top:12px}
        
        @media(max-width:900px){
            .sidebar{display:none}.main{margin-left:0}
            .body{padding:20px}
            .row{grid-template-columns:1fr}
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand">
        <div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div>
        <div class="sb-name">Smart Learning</div>
        <div class="sb-role">Teacher Portal</div>
    </div>
    <ul class="sb-nav">
        <li><a href="../dashboard/teacher_dashboard.php"><span class="nav-icon">⊞</span> Dashboard</a></li>
        <li><a href="create_quiz.php" class="active"><span class="nav-icon">📝</span> Create Quiz</a></li>
        <li><a href="quizzes.php"><span class="nav-icon">📋</span> My Quizzes</a></li>
    </ul>
    <div class="sb-footer">
        <div class="sb-user"><div class="sb-avatar"><?php echo $avatarLetter; ?></div><div><div class="sb-user-name"><?php echo htmlspecialchars($fullName); ?></div><div class="sb-user-sub">Teacher</div></div></div>
        <a href="../../logout.php" class="sb-logout">→ Sign out</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div class="topbar-breadcrumb">Teaching / <span><?php echo $editQuiz ? 'Edit Quiz' : 'Create Quiz'; ?></span></div>
    </div>
    <div class="body">
        <div class="form-container">
            <!-- Quiz Form -->
            <div class="form-card">
                <div class="form-header">
                    <h1><?php echo $editQuiz ? 'Edit Quiz' : 'Create New Quiz'; ?></h1>
                    <p>Set up your quiz details and add questions</p>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger">⚠ <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="quiz_id" value="<?php echo $editQuiz['id'] ?? 0; ?>">
                    <div class="form-group">
                        <label>Quiz Title *</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($editQuiz['title'] ?? ''); ?>" required>
                    </div>
                    <div class="row">
                        <div class="form-group">
                            <label>Subject *</label>
                            <select name="subject" class="form-control" required>
                                <option value="">Select Subject</option>
                                <?php $subjects = ['Mathematics','Physics','Chemistry','Biology','Computer Science','Business','English','History','Geography']; ?>
                                <?php foreach ($subjects as $sub): ?>
                                    <option value="<?php echo $sub; ?>" <?php echo (($editQuiz['subject'] ?? '') === $sub) ? 'selected' : ''; ?>><?php echo $sub; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Time Limit (minutes)</label>
                            <input type="number" name="time_limit" class="form-control" value="<?php echo $editQuiz['time_limit'] ?? 30; ?>" min="1" max="180">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="correct-label">
                            <input type="checkbox" name="is_published" value="1" <?php echo (($editQuiz['is_published'] ?? 0) == 1) ? 'checked' : ''; ?>>
                            Publish immediately (students can take this quiz)
                        </label>
                    </div>
                    <button type="submit" class="btn-primary"><?php echo $editQuiz ? 'Update Quiz' : 'Create Quiz'; ?></button>
                </form>
            </div>
            
            <!-- Questions Section (only if quiz exists) -->
            <?php if ($quizId > 0): ?>
            <div class="form-card">
                <div class="form-header">
                    <h2>Quiz Questions</h2>
                    <p>Add multiple choice questions to your quiz</p>
                </div>
                
                <!-- Add Question Form -->
                <form method="POST" action="save_question.php" style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid var(--border);">
                    <input type="hidden" name="quiz_id" value="<?php echo $quizId; ?>">
                    <div class="form-group">
                        <label>Question Text</label>
                        <textarea name="question_text" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="options-grid">
                        <div><label>A:</label> <input type="text" name="option_a" class="option-input" required></div>
                        <div><label>B:</label> <input type="text" name="option_b" class="option-input" required></div>
                        <div><label>C:</label> <input type="text" name="option_c" class="option-input" required></div>
                        <div><label>D:</label> <input type="text" name="option_d" class="option-input" required></div>
                    </div>
                    <div class="correct-label">
                        <label>Correct Option:</label>
                        <select name="correct_option" required>
                            <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>
                        </select>
                    </div>
                    <button type="submit" name="add_question" class="btn-primary" style="margin-top:16px;">+ Add Question</button>
                </form>
                
                <!-- Existing Questions List -->
                <?php if (!empty($questions)): ?>
                    <h3 style="margin-bottom:16px;">Questions (<?php echo count($questions); ?>)</h3>
                    <?php foreach ($questions as $index => $q): ?>
                        <div class="question-card">
                            <div class="question-header">
                                <span class="question-title">Q<?php echo $index + 1; ?>: <?php echo htmlspecialchars($q['question_text']); ?></span>
                                <form method="POST" action="delete_question.php" style="display:inline;" onsubmit="return confirm('Delete this question?');">
                                    <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                                    <input type="hidden" name="quiz_id" value="<?php echo $quizId; ?>">
                                    <button type="submit" name="delete_question" class="btn-danger">🗑 Delete</button>
                                </form>
                            </div>
                            <div class="options-grid">
                                <div>A: <?php echo htmlspecialchars($q['option_a']); ?></div>
                                <div>B: <?php echo htmlspecialchars($q['option_b']); ?></div>
                                <div>C: <?php echo htmlspecialchars($q['option_c']); ?></div>
                                <div>D: <?php echo htmlspecialchars($q['option_d']); ?></div>
                            </div>
                            <div style="margin-top:8px; color:var(--green);">✅ Correct: Option <?php echo strtoupper($q['correct_option']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert" style="background:var(--accent-lt); color:var(--accent);">📝 No questions added yet. Add your first question above.</div>
                <?php endif; ?>
                
                <div style="margin-top:20px; text-align:right;">
                    <a href="quizzes.php" class="btn-secondary" style="text-decoration:none; padding:12px 24px; display:inline-block;">← Back to My Quizzes</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>