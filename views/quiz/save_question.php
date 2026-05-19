<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');
if ($_SESSION['role'] !== 'teacher') redirect_by_role($_SESSION['role']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $quiz_id = (int)$_POST['quiz_id'];
    $question_text = sanitize_input($_POST['question_text']);
    $option_a = sanitize_input($_POST['option_a']);
    $option_b = sanitize_input($_POST['option_b']);
    $option_c = sanitize_input($_POST['option_c']);
    $option_d = sanitize_input($_POST['option_d']);
    $correct_option = strtoupper(sanitize_input($_POST['correct_option']));
    
    // Verify teacher owns this quiz
    $check = mysqli_query($conn, "SELECT id FROM quizzes WHERE id = $quiz_id AND created_by = {$_SESSION['user_id']}");
    if (mysqli_num_rows($check) > 0) {
        $stmt = mysqli_prepare($conn, "INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'issssss', $quiz_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    header("Location: create_quiz.php?id=$quiz_id");
    exit();
}

header("Location: create_quiz.php");
exit();
?>