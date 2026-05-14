<?php
class Career {
    private $conn;
    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getRecommendations($userId) {
        // First get student's RIASEC code from latest assessment
        $query = "SELECT holland_code FROM interest_assessments WHERE student_id = ? ORDER BY id DESC LIMIT 1";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        
        if (!$row || empty($row['holland_code'])) {
            return [];
        }
        
        $code = $row['holland_code'];
        // Match careers where holland_codes contains any of the letters (simple rule)
        $careerQuery = "SELECT id, career_name as title, description as industry, growth_rate 
                        FROM career_paths 
                        WHERE holland_codes LIKE ? 
                        LIMIT 5";
        $like = "%" . $code[0] . "%"; // match first letter for demo
        $stmt = mysqli_prepare($this->conn, $careerQuery);
        mysqli_stmt_bind_param($stmt, "s", $like);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $recommendations = [];
        while ($career = mysqli_fetch_assoc($result)) {
            $recommendations[] = $career;
        }
        return $recommendations;
    }
}