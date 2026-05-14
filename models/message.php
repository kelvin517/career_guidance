<?php
class Message {
    private $conn;
    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function countUnread($userId) {
        $query = "SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        return $row ? (int)$row['unread'] : 0;
    }

    public function getNotifications($userId, $onlyUnread = true, $limit = 5) {
        $sql = "SELECT id, title, message, created_at, is_read 
                FROM notifications 
                WHERE user_id = ?";
        if ($onlyUnread) {
            $sql .= " AND is_read = 0";
        }
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $userId, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $notifications = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = $row;
        }
        return $notifications;
    }
}