<?php
/**
 * Profile Model
 * Manages student_profiles and counselor_profiles tables.
 *
 * Smart Learning Career Guidance System
 */

class Profile
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // STUDENT PROFILES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Create a student profile row linked to a user.
     */
    public function createStudentProfile(int $userId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO student_profiles
                (user_id, institution, course_of_study, year_of_study, bio, avatar, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->bind_param(
            'issiis',
            $userId,
            $data['institution'],
            $data['course_of_study'],
            $data['year_of_study']  ?? null,
            $data['bio']            ?? null,
            $data['avatar']         ?? null
        );
        return $stmt->execute();
    }

    /**
     * Get a student profile by user ID.
     */
    public function getStudentProfile(int $userId): array|null
    {
        $stmt = $this->db->prepare(
            'SELECT sp.*, u.full_name, u.email, u.phone
               FROM student_profiles sp
               JOIN users u ON u.id = sp.user_id
              WHERE sp.user_id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: null;
    }

    /**
     * Update a student profile.
     *
     * @param array $data  Keys: institution, course_of_study, year_of_study, bio, avatar
     */
    public function updateStudentProfile(int $userId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE student_profiles
                SET institution = ?, course_of_study = ?, year_of_study = ?,
                    bio = ?, avatar = ?, updated_at = NOW()
              WHERE user_id = ?'
        );
        $stmt->bind_param(
            'ssiisi',
            $data['institution'],
            $data['course_of_study'],
            $data['year_of_study'] ?? null,
            $data['bio']           ?? null,
            $data['avatar']        ?? null,
            $userId
        );
        return $stmt->execute();
    }

    /**
     * List all student profiles (admin view).
     */
    public function getAllStudents(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $countResult = $this->db->query('SELECT COUNT(*) FROM student_profiles');
        $total = (int) $countResult->fetch_row()[0];

        $stmt = $this->db->prepare(
            'SELECT sp.*, u.full_name, u.email, u.is_active
               FROM student_profiles sp
               JOIN users u ON u.id = sp.user_id
              ORDER BY u.full_name ASC
              LIMIT ? OFFSET ?'
        );
        $stmt->bind_param('ii', $perPage, $offset);
        $stmt->execute();

        return [
            'data'  => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
            'total' => $total,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // COUNSELOR PROFILES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Create a counselor profile row linked to a user.
     */
    public function createCounselorProfile(int $userId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO counselor_profiles
                (user_id, specialization, years_experience, bio, avatar,
                 is_verified, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())'
        );
        $stmt->bind_param(
            'isiss',
            $userId,
            $data['specialization'],
            $data['years_experience'] ?? 0,
            $data['bio']              ?? null,
            $data['avatar']           ?? null
        );
        return $stmt->execute();
    }

    /**
     * Get a counselor profile by user ID.
     */
    public function getCounselorProfile(int $userId): array|null
    {
        $stmt = $this->db->prepare(
            'SELECT cp.*, u.full_name, u.email, u.phone
               FROM counselor_profiles cp
               JOIN users u ON u.id = cp.user_id
              WHERE cp.user_id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: null;
    }

    /**
     * Update a counselor profile.
     */
    public function updateCounselorProfile(int $userId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE counselor_profiles
                SET specialization = ?, years_experience = ?, bio = ?,
                    avatar = ?, updated_at = NOW()
              WHERE user_id = ?'
        );
        $stmt->bind_param(
            'siiss',
            $data['specialization'],
            $data['years_experience'] ?? 0,
            $data['bio']              ?? null,
            $data['avatar']           ?? null,
            $userId
        );
        return $stmt->execute();
    }

    /**
     * List verified counselors (for student directory).
     */
    public function getVerifiedCounselors(?string $specialization = null): array
    {
        if ($specialization) {
            $stmt = $this->db->prepare(
                'SELECT cp.*, u.full_name, u.email
                   FROM counselor_profiles cp
                   JOIN users u ON u.id = cp.user_id
                  WHERE cp.is_verified = 1 AND cp.specialization = ?
                  ORDER BY cp.years_experience DESC'
            );
            $stmt->bind_param('s', $specialization);
        } else {
            $stmt = $this->db->prepare(
                'SELECT cp.*, u.full_name, u.email
                   FROM counselor_profiles cp
                   JOIN users u ON u.id = cp.user_id
                  WHERE cp.is_verified = 1
                  ORDER BY cp.years_experience DESC'
            );
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Toggle counselor verification status (admin action).
     */
    public function verifyCounselor(int $userId, bool $verified): bool
    {
        $flag = $verified ? 1 : 0;
        $stmt = $this->db->prepare(
            'UPDATE counselor_profiles
                SET is_verified = ?, updated_at = NOW()
              WHERE user_id = ?'
        );
        $stmt->bind_param('ii', $flag, $userId);
        return $stmt->execute();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SHARED / GENERIC
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Update avatar path for any role's profile table.
     *
     * @param string $role  'student' | 'counselor'
     */
    public function updateAvatar(int $userId, string $role, string $avatarPath): bool
    {
        $table = match ($role) {
            'student'   => 'student_profiles',
            'counselor' => 'counselor_profiles',
            default     => null,
        };

        if (!$table) return false;

        $stmt = $this->db->prepare(
            "UPDATE {$table} SET avatar = ?, updated_at = NOW() WHERE user_id = ?"
        );
        $stmt->bind_param('si', $avatarPath, $userId);
        return $stmt->execute();
    }

    /**
     * Delete all profile records for a user (called before user deletion).
     */
    public function deleteAllProfiles(int $userId): void
    {
        foreach (['student_profiles', 'counselor_profiles'] as $table) {
            $stmt = $this->db->prepare("DELETE FROM {$table} WHERE user_id = ?");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
        }
    }

    /**
     * Get distinct specializations for the counselor filter dropdown.
     */
    public function getCounselorSpecializations(): array
    {
        $result = $this->db->query(
            'SELECT DISTINCT specialization FROM counselor_profiles
              WHERE is_verified = 1 ORDER BY specialization ASC'
        );
        return array_column($result->fetch_all(MYSQLI_ASSOC), 'specialization');
    }
}