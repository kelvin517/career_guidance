<?php
/**
 * Admin Model
 * System settings, configuration, audit logging, and admin-only user management.
 *
 * Tables: system_settings, activity_log, audit_log
 *
 * Smart Learning Career Guidance System
 */

class Admin
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SYSTEM SETTINGS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Get a single setting value by key.
     *
     * @param mixed $default  Returned when key is not found
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        $stmt = $this->db->prepare(
            'SELECT value FROM system_settings WHERE `key` = ? LIMIT 1'
        );
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        return $row ? $row[0] : $default;
    }

    /**
     * Fetch all settings as a key => value associative array.
     */
    public function getAllSettings(): array
    {
        $result = $this->db->query('SELECT `key`, value FROM system_settings ORDER BY `key` ASC');
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['key']] = $row['value'];
        }
        return $settings;
    }

    /**
     * Fetch settings belonging to a named group (e.g. 'email', 'appearance').
     */
    public function getSettingGroup(string $group): array
    {
        $stmt = $this->db->prepare(
            'SELECT `key`, value FROM system_settings WHERE `group` = ? ORDER BY `key` ASC'
        );
        $stmt->bind_param('s', $group);
        $stmt->execute();
        $settings = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $settings[$row['key']] = $row['value'];
        }
        return $settings;
    }

    /**
     * Upsert a single setting.
     */
    public function setSetting(string $key, string $value, ?string $group = null): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO system_settings (`key`, value, `group`, updated_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()'
        );
        $stmt->bind_param('sss', $key, $value, $group);
        return $stmt->execute();
    }

    /**
     * Bulk-update settings from a key => value array.
     *
     * @param array  $settings  ['site_name' => 'Smart Learning', ...]
     * @param string $group     Optional group tag applied to all keys
     */
    public function bulkSetSettings(array $settings, string $group = 'general'): bool
    {
        $this->db->begin_transaction();
        try {
            foreach ($settings as $key => $value) {
                $this->setSetting($key, (string) $value, $group);
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Admin::bulkSetSettings — ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a setting by key.
     */
    public function deleteSetting(string $key): bool
    {
        $stmt = $this->db->prepare('DELETE FROM system_settings WHERE `key` = ?');
        $stmt->bind_param('s', $key);
        return $stmt->execute();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // USER MANAGEMENT (admin-level operations)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Change a user's role (admin action only).
     */
    public function changeUserRole(int $userId, string $newRole): bool
    {
        $allowed = ['student', 'counselor', 'admin'];
        if (!in_array($newRole, $allowed, true)) return false;

        $stmt = $this->db->prepare(
            'UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->bind_param('si', $newRole, $userId);
        return $stmt->execute();
    }

    /**
     * Force-reset a user's password (generates a random password; returns plain text
     * so it can be emailed to the user, then discards it).
     *
     * @return string  The temporary plain-text password
     */
    public function forceResetPassword(int $userId): string
    {
        $plain  = bin2hex(random_bytes(6)); // 12-char hex temp password
        $hashed = password_hash($plain, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare(
            'UPDATE users SET password = ?, reset_token = NULL,
             reset_token_expires = NULL, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->bind_param('si', $hashed, $userId);
        $stmt->execute();

        return $plain;
    }

    /**
     * Hard-delete a user and all related records in a transaction.
     * Prefer soft-delete (setActive) in most cases.
     */
    public function deleteUserPermanently(int $userId): bool
    {
        $this->db->begin_transaction();
        try {
            $tables = [
                'activity_log'            => 'user_id',
                'notifications'           => 'user_id',
                'material_views'          => 'student_id',
                'quiz_answers'            => null,   // handled via attempts
                'quiz_attempts'           => 'student_id',
                'assessment_responses'    => null,   // handled via results
                'assessment_results'      => 'student_id',
                'career_recommendations'  => 'student_id',
                'student_profiles'        => 'user_id',
                'counselor_profiles'      => 'user_id',
            ];

            // Delete quiz_answers via their attempts
            $this->db->query(
                "DELETE qa FROM quiz_answers qa
                  JOIN quiz_attempts a ON a.id = qa.attempt_id
                 WHERE a.student_id = {$userId}"
            );

            // Delete assessment_responses via their results
            $this->db->query(
                "DELETE ar FROM assessment_responses ar
                  JOIN assessment_results res ON res.id = ar.session_id
                 WHERE res.student_id = {$userId}"
            );

            foreach ($tables as $table => $col) {
                if ($col === null) continue; // already handled above
                $stmt = $this->db->prepare("DELETE FROM {$table} WHERE {$col} = ?");
                $stmt->bind_param('i', $userId);
                $stmt->execute();
            }

            // Messages: soft-delete both sides or hard-delete
            $this->db->prepare('DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?')
                     ->bind_param('ii', $userId, $userId);

            // Finally delete the user
            $del = $this->db->prepare('DELETE FROM users WHERE id = ?');
            $del->bind_param('i', $userId);
            $del->execute();

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Admin::deleteUserPermanently — ' . $e->getMessage());
            return false;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ACTIVITY & AUDIT LOGGING
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Write an entry to the activity log.
     *
     * @param int         $userId
     * @param string      $action       e.g. 'login', 'delete_user', 'update_setting'
     * @param string|null $description  Human-readable detail
     * @param string|null $ip           Request IP address
     */
    public function logActivity(int $userId, string $action, ?string $description = null, ?string $ip = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO activity_log (user_id, action, description, ip_address, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->bind_param('isss', $userId, $action, $description, $ip);
        $stmt->execute();
    }

    /**
     * Write an entry to the audit log (admin-sensitive changes).
     *
     * @param array $data  Keys: admin_id, action, target_type, target_id,
     *                           old_value (JSON), new_value (JSON)
     */
    public function auditLog(array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_log
                (admin_id, action, target_type, target_id, old_value, new_value, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $oldJson = isset($data['old_value']) ? json_encode($data['old_value']) : null;
        $newJson = isset($data['new_value']) ? json_encode($data['new_value']) : null;

        $stmt->bind_param(
            'ississs',   // Note: target_id can be null
            $data['admin_id'],
            $data['action'],
            $data['target_type'] ?? null,
            $data['target_id']   ?? null,
            $oldJson,
            $newJson
        );
        $stmt->execute();
    }

    /**
     * Paginated activity log (admin panel).
     *
     * @param int|null    $userId   Filter by specific user (null = all)
     * @param string|null $action   Filter by action type
     */
    public function getActivityLog(?int $userId = null, ?string $action = null, int $page = 1, int $perPage = 50): array
    {
        $conditions = [];
        $params     = [];
        $types      = '';

        if ($userId) {
            $conditions[] = 'al.user_id = ?';
            $params[]     = $userId;
            $types       .= 'i';
        }
        if ($action) {
            $conditions[] = 'al.action = ?';
            $params[]     = $action;
            $types       .= 's';
        }

        $where  = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $offset = ($page - 1) * $perPage;

        $countSql = "SELECT COUNT(*) FROM activity_log al {$where}";
        if ($params) {
            $cs = $this->db->prepare($countSql);
            $cs->bind_param($types, ...$params);
            $cs->execute();
            $total = (int) $cs->get_result()->fetch_row()[0];
        } else {
            $total = (int) $this->db->query($countSql)->fetch_row()[0];
        }

        $sql  = "SELECT al.*, u.full_name, u.role
                   FROM activity_log al
                   JOIN users u ON u.id = al.user_id
                  {$where}
                  ORDER BY al.created_at DESC
                  LIMIT ? OFFSET ?";

        $allParams = array_merge($params, [$perPage, $offset]);
        $allTypes  = $types . 'ii';

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($allTypes, ...$allParams);
        $stmt->execute();

        return [
            'data'  => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
            'total' => $total,
        ];
    }

    /**
     * Prune activity log entries older than N days.
     */
    public function pruneActivityLog(int $olderThanDays = 90): int
    {
        $stmt = $this->db->prepare(
            'DELETE FROM activity_log
              WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
        );
        $stmt->bind_param('i', $olderThanDays);
        $stmt->execute();
        return $stmt->affected_rows;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // DASHBOARD WIDGETS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Recent signups (last 10 registrations).
     */
    public function getRecentSignups(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, full_name, email, role, created_at
               FROM users
              ORDER BY created_at DESC
              LIMIT ?'
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Users who have not logged in for more than N days (dormant accounts).
     */
    public function getDormantUsers(int $inactiveDays = 30): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.full_name, u.email, u.role, MAX(al.created_at) AS last_login
               FROM users u
               LEFT JOIN activity_log al ON al.user_id = u.id AND al.action = "login"
              WHERE u.is_active = 1
              GROUP BY u.id
             HAVING last_login < DATE_SUB(NOW(), INTERVAL ? DAY)
                 OR last_login IS NULL
              ORDER BY last_login ASC'
        );
        $stmt->bind_param('i', $inactiveDays);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Overall system health snapshot for the admin dashboard.
     *
     * @return array  Keyed metrics
     */
    public function getSystemHealth(): array
    {
        $metrics = [];

        // DB size (information_schema — may require privileges)
        $dbName = $this->db->query('SELECT DATABASE()')->fetch_row()[0];
        $sizeRow = $this->db->query(
            "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
               FROM information_schema.TABLES
              WHERE table_schema = '{$dbName}'"
        )->fetch_assoc();
        $metrics['db_size_mb'] = $sizeRow['size_mb'] ?? null;

        // Active sessions (users active in last 15 min via last_active timestamp)
        $metrics['active_sessions'] = (int) $this->db->query(
            "SELECT COUNT(*) FROM users WHERE last_active > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        )->fetch_row()[0];

        // Log entries today
        $metrics['log_entries_today'] = (int) $this->db->query(
            "SELECT COUNT(*) FROM activity_log WHERE DATE(created_at) = CURDATE()"
        )->fetch_row()[0];

        // Pending counselor verifications
        $metrics['pending_verifications'] = (int) $this->db->query(
            "SELECT COUNT(*) FROM counselor_profiles WHERE is_verified = 0"
        )->fetch_row()[0];

        // Unread messages platform-wide
        $metrics['unread_messages'] = (int) $this->db->query(
            "SELECT COUNT(*) FROM messages WHERE is_read = 0"
        )->fetch_row()[0];

        return $metrics;
    }
}