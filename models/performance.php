<?php
class Performance {
    private $conn;
    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getStudentSummary($userId) {
        // Quiz stats
        $quizQuery = "SELECT COUNT(DISTINCT quiz_id) as completed, 
                             AVG(score) as avg_score, 
                             MAX(score) as best_score,
                             COUNT(*) as total_attempts
                      FROM quiz_attempts WHERE student_id = ?";
        $stmt = mysqli_prepare($this->conn, $quizQuery);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $quizResult = mysqli_stmt_get_result($stmt);
        $quizData = mysqli_fetch_assoc($quizResult) ?: ['completed' => 0, 'avg_score' => 0, 'best_score' => 0, 'total_attempts' => 0];

        // Assessment count
        $assQuery = "SELECT COUNT(*) as completed_assessments FROM interest_assessments WHERE student_id = ?";
        $stmt = mysqli_prepare($this->conn, $assQuery);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $assResult = mysqli_stmt_get_result($stmt);
        $assData = mysqli_fetch_assoc($assResult) ?: ['completed_assessments' => 0];

        // Materials viewed (from a hypothetical user_materials table – create if needed)
        $matQuery = "SELECT COUNT(*) as materials_viewed FROM user_materials WHERE user_id = ?";
        $stmt = mysqli_prepare($this->conn, $matQuery);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $matResult = mysqli_stmt_get_result($stmt);
        $matData = mysqli_fetch_assoc($matResult) ?: ['materials_viewed' => 0];

        return [
            'quiz' => $quizData,
            'assessments' => $assData,
            'materials' => $matData
        ];
    }

    public function getQuizScoreTrend($userId, $limit = 6) {
        $query = "SELECT q.title as quiz_title, qa.score, qa.completed_at 
                  FROM quiz_attempts qa 
                  JOIN quizzes q ON qa.quiz_id = q.id 
                  WHERE qa.student_id = ? 
                  ORDER BY qa.completed_at DESC 
                  LIMIT ?";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $userId, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $trend = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $trend[] = $row;
        }
        return array_reverse($trend); // oldest first for chart
    }
}