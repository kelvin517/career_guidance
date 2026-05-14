<?php
/**
 * User Model
 * CRUD operations for the `users` table.
 *
 * Smart Learning Career Guidance System
 */

class User
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CREATE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Insert a new user and return their new ID, or false on failure.
     *
     * @param array $data  Keys: full_name, email, phone, password (plain), role
     * @return int|false
     */
    public function create(array $data): int|false
    {
        $hashed = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt = $this->db->prepare(
            'INSERT INTO users (full_name, email, phone, password, role, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, 1, NOW())'
        );

        $stmt->bind_param(
            'sssss',
            $data['full_name'],
            $data['email'],
            $data['phone'],
            $hashed,
            $data['role']
        );

        return $stmt->execute() ? (int) $this->db->insert_id : false;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // READ
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Fetch a single user by primary key.
     */
    public function findById(int $id): array|null
    {
        $stmt = $this->db->prepare(
            'SELECT id, full_name, email, phone, role, is_active, created_at, updated_at
               FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: null;
    }

    /**
     * Fetch a single user by email address.
     */
    public function findByEmail(string $email): array|null
    {
        $stmt = $this->db->prepare(
            'SELECT id, full_name, email, phone, password, role, is_active, created_at
               FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: null;
    }

    /**
     * Fetch all users, optionally filtered by role.
     *
     * @param string|null $role  'student' | 'counselor' | 'admin' | null
     * @return array
     */
    public function getAll(?string $role = null): array
    {
        if ($role) {
            $stmt = $this->db->prepare(
                'SELECT id, full_name, email, phone, role, is_active, created_at
                   FROM users WHERE role = ? ORDER BY full_name ASC'
            );
            $stmt->bind_param('s', $role);
        } else {
            $stmt = $this->db->prepare(
                'SELECT id, full_name, email, phone, role, is_active, created_at
                   FROM users ORDER BY full_name ASC'
            );
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Paginated user list for admin tables.
     *
     * @return array  ['data' => [...], 'total' => int]
     */
    public function getPaginated(int $page = 1, int $perPage = 20, ?string $role = null): array
    {
        $offset = ($page - 1) * $perPage;

        if ($role) {
            $countStmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE role = ?');
            $countStmt->bind_param('s', $role);
            $countStmt->execute();
            $total = (int) $countStmt->get_result()->fetch_row()[0];

            $stmt = $this->db->prepare(
                'SELECT id, full_name, email, phone, role, is_active, created_at
                   FROM users WHERE role = ? ORDER BY created_at DESC LIMIT ? OFFSET ?'
            );
            $stmt->bind_param('sii', $role, $perPage, $offset);
        } else {
            $countStmt = $this->db->prepare('SELECT COUNT(*) FROM users');
            $countStmt->execute();
            $total = (int) $countStmt->get_result()->fetch_row()[0];

            $stmt = $this->db->prepare(
                'SELECT id, full_name, email, phone, role, is_active, created_at
                   FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?'
            );
            $stmt->bind_param('ii', $perPage, $offset);
        }

        $stmt->execute();

        return [
            'data'  => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
            'total' => $total,
        ];
    }

    /**
     * Search users by name or email.
     */
    public function search(string $query, ?string $role = null): array
    {
        $like = "%{$query}%";

        if ($role) {
            $stmt = $this->db->prepare(
                'SELECT id, full_name, email, phone, role, is_active
                   FROM users
                  WHERE role = ? AND (full_name LIKE ? OR email LIKE ?)
                  ORDER BY full_name ASC LIMIT 50'
            );
            $stmt->bind_param('sss', $role, $like, $like);
        } else {
            $stmt = $this->db->prepare(
                'SELECT id, full_name, email, phone, role, is_active
                   FROM users
                  WHERE full_name LIKE ? OR email LIKE ?
                  ORDER BY full_name ASC LIMIT 50'
            );
            $stmt->bind_param('ss', $like, $like);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // UPDATE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Update basic profile fields.
     *
     * @param array $data  Keys: full_name, email, phone  (all required)
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET full_name = ?, email = ?, phone = ?, updated_at = NOW()
              WHERE id = ?'
        );
        $stmt->bind_param('sssi', $data['full_name'], $data['email'], $data['phone'], $id);
        return $stmt->execute() && $stmt->affected_rows > 0;
    }

    /**
     * Update a user's hashed password.
     */
    public function updatePassword(int $id, string $newPassword): bool
    {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt   = $this->db->prepare(
            'UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->bind_param('si', $hashed, $id);
        return $stmt->execute() && $stmt->affected_rows > 0;
    }

    /**
     * Activate or deactivate a user account.
     */
    public function setActive(int $id, bool $active): bool
    {
        $flag = $active ? 1 : 0;
        $stmt = $this->db->prepare(
            'UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->bind_param('ii', $flag, $id);
        return $stmt->execute();
    }

    /**
     * Store a password-reset token with an expiry timestamp.
     */
    public function storeResetToken(int $id, string $token, int $expiresAt): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET reset_token = ?, reset_token_expires = ?, updated_at = NOW()
              WHERE id = ?'
        );
        $stmt->bind_param('sii', $token, $expiresAt, $id);
        return $stmt->execute();
    }

    /**
     * Find user by valid (non-expired) reset token.
     */
    public function findByResetToken(string $token): array|null
    {
        $now  = time();
        $stmt = $this->db->prepare(
            'SELECT id, full_name, email FROM users
              WHERE reset_token = ? AND reset_token_expires > ? LIMIT 1'
        );
        $stmt->bind_param('si', $token, $now);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: null;
    }

    /**
     * Clear the reset token after use.
     */
    public function clearResetToken(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET reset_token = NULL, reset_token_expires = NULL,
             updated_at = NOW() WHERE id = ?'
        );
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // DELETE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Permanently delete a user record (prefer setActive(false) instead).
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->bind_param('i', $id);
        return $stmt->execute() && $stmt->affected_rows > 0;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Check whether an email is already registered.
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM users WHERE email = ? AND id != ? LIMIT 1'
            );
            $stmt->bind_param('si', $email, $excludeId);
        } else {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM users WHERE email = ? LIMIT 1'
            );
            $stmt->bind_param('s', $email);
        }
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_row();
    }

    /**
     * Count users by role.
     *
     * @return array  e.g. ['student' => 45, 'counselor' => 8, 'admin' => 2]
     */
    public function countByRole(): array
    {
        $result = $this->db->query(
            'SELECT role, COUNT(*) AS total FROM users GROUP BY role'
        );
        $counts = [];
        while ($row = $result->fetch_assoc()) {
            $counts[$row['role']] = (int) $row['total'];
        }
        return $counts;
    }

    /**
     * Verify a plain password against the stored hash.
     */
    public function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}