<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');
if ($_SESSION['role'] !== 'student') redirect_by_role($_SESSION['role']);

$quizId = (int)$_POST['quiz_id'];
$studentId = $_SESSION['user_id'];

// Check if already submitted
$check = mysqli_query($conn, "SELECT id FROM quiz_attempts WHERE quiz_id = $quizId AND student_id = $studentId");
if (mysqli_num_rows($check) > 0) {
    header("Location: quiz_results.php?quiz_id=$quizId");
    exit();
}

// Get all questions
$questions = mysqli_query($conn, "SELECT id, correct_option FROM quiz_questions WHERE quiz_id = $quizId");
$score = 0;
$total = 0;

while ($q = mysqli_fetch_assoc($questions)) {
    $total++;
    $userAnswer = $_POST['q' . $q['id']] ?? '';
    if (!empty($userAnswer) && $userAnswer === $q['correct_option']) {
        $score++;
    }
}

// Save attempt
$stmt = mysqli_prepare($conn, "INSERT INTO quiz_attempts (quiz_id, student_id, score, total_questions) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, 'iiii', $quizId, $studentId, $score, $total);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header("Location: quiz_results.php?quiz_id=$quizId");
exit();
?>