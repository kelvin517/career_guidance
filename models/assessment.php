<?php
class Assessment {
    private $conn;
    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getLatestResult($userId) {
        $query = "SELECT holland_code, answers_json, created_at 
                  FROM interest_assessments 
                  WHERE student_id = ? 
                  ORDER BY id DESC LIMIT 1";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        
        if (!$row) {
            return null;
        }
        
        // Generate dummy scores for RIASEC dimensions (in real system, compute from answers_json)
        $scores = [
            'R' => rand(40, 95),
            'I' => rand(40, 95),
            'A' => rand(40, 95),
            'S' => rand(40, 95),
            'E' => rand(40, 95),
            'C' => rand(40, 95)
        ];
        
        return [
            'riasec_code' => $row['holland_code'],
            'scores' => $scores,
            'completed_at' => $row['created_at']
        ];
    }
}