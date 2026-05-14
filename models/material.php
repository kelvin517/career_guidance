<?php
/**
 * Material Model
 * Manages learning materials (files, links, videos) uploaded by counselors/admins.
 *
 * Smart Learning Career Guidance System
 */

class Material
{
    private mysqli $db;

    // Allowed file types stored in the DB for validation reference
    public const ALLOWED_TYPES = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'mp4', 'link'];

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CREATE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Add a new learning material record.
     *
     * @param array $data  Keys: uploader_id, title, description, type,
     *                           file_path|url, category, is_public
     * @return int|false   New material ID or false on failure
     */
    public function create(array $data): int|false
    {
        $stmt = $this->db->prepare(
            'INSERT INTO materials
                (uploader_id, title, description, type, file_path, url,
                 category, is_public, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        $isPublic = isset($data['is_public']) ? (int) $data['is_public'] : 1;

        $stmt->bind_param(
            'issssssi',
            $data['uploader_id'],
            $data['title'],
            $data['description'] ?? null,
            $data['type'],
            $data['file_path']   ?? null,
            $data['url']         ?? null,
            $data['category']    ?? null,
            $isPublic
        );

        return $stmt->execute() ? (int) $this->db->insert_id : false;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // READ
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Fetch a single material by ID (includes uploader name).
     */
    public function findById(int $id): array|null
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, u.full_name AS uploader_name
               FROM materials m
               JOIN users u ON u.id = m.uploader_id
              WHERE m.id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: null;
    }

    /**
     * Get all public materials, optionally filtered by category or type.
     */
    public function getPublic(?string $category = null, ?string $type = null): array
    {
        $conditions = ['m.is_public = 1'];
        $params     = [];
        $types      = '';

        if ($category) {
            $conditions[] = 'm.category = ?';
            $params[]     = $category;
            $types       .= 's';
        }

        if ($type) {
            $conditions[] = 'm.type = ?';
            $params[]     = $type;
            $types       .= 's';
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $sql   = "SELECT m.*, u.full_name AS uploader_name
                    FROM materials m
                    JOIN users u ON u.id = m.uploader_id
                   {$where}
                   ORDER BY m.created_at DESC";

        $stmt = $this->db->prepare($sql);

        if ($params) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get all materials uploaded by a specific user.
     */
    public function getByUploader(int $uploaderId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, u.full_name AS uploader_name
               FROM materials m
               JOIN users u ON u.id = m.uploader_id
              WHERE m.uploader_id = ?
              ORDER BY m.created_at DESC'
        );
        $stmt->bind_param('i', $uploaderId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Paginated material listing (admin panel).
     */
    public function getPaginated(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->query('SELECT COUNT(*) FROM materials')->fetch_row()[0];

        $stmt = $this->db->prepare(
            'SELECT m.*, u.full_name AS uploader_name
               FROM materials m
               JOIN users u ON u.id = m.uploader_id
              ORDER BY m.created_at DESC
              LIMIT ? OFFSET ?'
        );
        $stmt->bind_param('ii', $perPage, $offset);
        $stmt->execute();

        return [
            'data'  => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
            'total' => $total,
        ];
    }

    /**
     * Full-text search across title and description.
     */
    public function search(string $query): array
    {
        $like = "%{$query}%";
        $stmt = $this->db->prepare(
            'SELECT m.*, u.full_name AS uploader_name
               FROM materials m
               JOIN users u ON u.id = m.uploader_id
              WHERE m.is_public = 1 AND (m.title LIKE ? OR m.description LIKE ?)
              ORDER BY m.created_at DESC LIMIT 50'
        );
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Return distinct category names (for filter dropdowns).
     */
    public function getCategories(): array
    {
        $result = $this->db->query(
            'SELECT DISTINCT category FROM materials
              WHERE category IS NOT NULL ORDER BY category ASC'
        );
        return array_column($result->fetch_all(MYSQLI_ASSOC), 'category');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // UPDATE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Update material metadata.
     *
     * @param array $data  Keys: title, description, category, is_public
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE materials
                SET title = ?, description = ?, category = ?,
                    is_public = ?, updated_at = NOW()
              WHERE id = ?'
        );
        $isPublic = (int) ($data['is_public'] ?? 1);
        $stmt->bind_param(
            'sssii',
            $data['title'],
            $data['description'] ?? null,
            $data['category']    ?? null,
            $isPublic,
            $id
        );
        return $stmt->execute() && $stmt->affected_rows > 0;
    }

    /**
     * Toggle the is_public flag (publish / unpublish).
     */
    public function toggleVisibility(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE materials
                SET is_public = NOT is_public, updated_at = NOW()
              WHERE id = ?'
        );
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    /**
     * Increment the view/download counter.
     */
    public function incrementViews(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE materials SET views = views + 1 WHERE id = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // DELETE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Delete a material record (caller is responsible for removing the physical file).
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM materials WHERE id = ?');
        $stmt->bind_param('i', $id);
        return $stmt->execute() && $stmt->affected_rows > 0;
    }

    /**
     * Delete all materials uploaded by a specific user.
     */
    public function deleteByUploader(int $uploaderId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM materials WHERE uploader_id = ?');
        $stmt->bind_param('i', $uploaderId);
        return $stmt->execute();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // STATS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Summarise material counts grouped by type.
     *
     * @return array  e.g. ['pdf' => 12, 'link' => 5, ...]
     */
    public function countByType(): array
    {
        $result = $this->db->query(
            'SELECT type, COUNT(*) AS total FROM materials GROUP BY type'
        );
        $counts = [];
        while ($row = $result->fetch_assoc()) {
            $counts[$row['type']] = (int) $row['total'];
        }
        return $counts;
    }
}