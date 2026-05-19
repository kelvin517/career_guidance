<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');
if ($_SESSION['role'] !== 'teacher') redirect_by_role($_SESSION['role']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_question'])) {
    $question_id = (int)$_POST['question_id'];
    $quiz_id = (int)$_POST['quiz_id'];
    
    // Verify teacher owns this quiz
    $check = mysqli_query($conn, "SELECT q.id FROM quizzes q WHERE q.id = $quiz_id AND q.created_by = {$_SESSION['user_id']}");
    if (mysqli_num_rows($check) > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM quiz_questions WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $question_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    header("Location: create_quiz.php?id=$quiz_id");
    exit();
}

header("Location: create_quiz.php");
exit();
?>