<?php
/**
 * Quiz Model
 * Manages quizzes, questions, answer options, and student attempts.
 *
 * Tables: quizzes, quiz_questions, quiz_options, quiz_attempts, quiz_answers
 *
 * Smart Learning Career Guidance System
 */

class Quiz
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // QUIZZES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Create a new quiz and return its ID.
     *
     * @param array $data  Keys: creator_id, title, description, time_limit_minutes,
     *                           pass_mark, is_published, material_id (optional)
     */
    public function createQuiz(array $data): int|false
    {
        $stmt = $this->db->prepare(
            'INSERT INTO quizzes
                (creator_id, material_id, title, description,
                 time_limit_minutes, pass_mark, is_published, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        $isPublished = (int) ($data['is_published'] ?? 0);

        $stmt->bind_param(
            'iissiii',
            $data['creator_id'],
            $data['material_id']         ?? null,
            $data['title'],
            $data['description']         ?? null,
            $data['time_limit_minutes']  ?? null,
            $data['pass_mark']           ?? 50,
            $isPublished
        );

        return $stmt->execute() ? (int) $this->db->insert_id : false;
    }

    /**
     * Fetch quiz metadata by ID.
     */
    public function getQuiz(int $quizId): array|null
    {
        $stmt = $this->db->prepare(
            'SELECT q.*, u.full_name AS creator_name,
                    (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) AS question_count
               FROM quizzes q
               JOIN users u ON u.id = q.creator_id
              WHERE q.id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $quizId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: null;
    }

    /**
     * Get all published quizzes (student view).
     */
    public function getPublished(): array
    {
        return $this->db->query(
            'SELECT q.*, u.full_name AS creator_name,
                    (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) AS question_count
               FROM quizzes q
               JOIN users u ON u.id = q.creator_id
              WHERE q.is_published = 1
              ORDER BY q.created_at DESC'
        )->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get all quizzes created by a specific counselor/admin.
     */
    public function getByCreator(int $creatorId): array
    {
        $stmt = $this->db->prepare(
            'SELECT q.*,
                    (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) AS question_count,
                    (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id) AS attempt_count
               FROM quizzes q
              WHERE q.creator_id = ?
              ORDER BY q.created_at DESC'
        );
        $stmt->bind_param('i', $creatorId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Publish or unpublish a quiz.
     */
    public function setPublished(int $quizId, bool $published): bool
    {
        $flag = $published ? 1 : 0;
        $stmt = $this->db->prepare(
            'UPDATE quizzes SET is_published = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->bind_param('ii', $flag, $quizId);
        return $stmt->execute();
    }

    /**
     * Update quiz metadata.
     */
    public function updateQuiz(int $quizId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE quizzes
                SET title = ?, description = ?, time_limit_minutes = ?,
                    pass_mark = ?, updated_at = NOW()
              WHERE id = ?'
        );
        $stmt->bind_param(
            'ssiii',
            $data['title'],
            $data['description']        ?? null,
            $data['time_limit_minutes'] ?? null,
            $data['pass_mark']          ?? 50,
            $quizId
        );
        return $stmt->execute();
    }

    /**
     * Delete a quiz and cascade its questions, options, and attempts.
     */
    public function deleteQuiz(int $quizId): bool
    {
        // FK constraints or manual cleanup
        foreach (['quiz_answers', 'quiz_attempts', 'quiz_options', 'quiz_questions'] as $t) {
            $col  = ($t === 'quiz_answers') ? 'attempt_id' : 'quiz_id';
            if ($t === 'quiz_answers') {
                $this->db->query(
                    "DELETE qa FROM quiz_answers qa
                     JOIN quiz_attempts att ON att.id = qa.attempt_id
                     WHERE att.quiz_id = {$quizId}"
                );
            } else {
                $stmt = $this->db->prepare("DELETE FROM {$t} WHERE {$col} = ?");
                $stmt->bind_param('i', $quizId);
                $stmt->execute();
            }
        }
        $stmt = $this->db->prepare('DELETE FROM quizzes WHERE id = ?');
        $stmt->bind_param('i', $quizId);
        return $stmt->execute();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // QUESTIONS & OPTIONS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Add a question to a quiz.
     *
     * @param array $data  Keys: question_text, question_type ('mcq'|'true_false'|'short'),
     *                           marks, order_index
     * @return int|false   New question ID
     */
    public function addQuestion(int $quizId, array $data): int|false
    {
        $stmt = $this->db->prepare(
            'INSERT INTO quiz_questions
                (quiz_id, question_text, question_type, marks, order_index, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->bind_param(
            'issii',
            $quizId,
            $data['question_text'],
            $data['question_type'] ?? 'mcq',
            $data['marks']         ?? 1,
            $data['order_index']   ?? 0
        );
        return $stmt->execute() ? (int) $this->db->insert_id : false;
    }

    /**
     * Add an answer option to a question.
     *
     * @param array $data  Keys: option_text, is_correct
     * @return int|false
     */
    public function addOption(int $questionId, array $data): int|false
    {
        $isCorrect = (int) ($data['is_correct'] ?? 0);
        $stmt = $this->db->prepare(
            'INSERT INTO quiz_options (question_id, option_text, is_correct)
             VALUES (?, ?, ?)'
        );
        $stmt->bind_param('ssi', $questionId, $data['option_text'], $isCorrect);
        return $stmt->execute() ? (int) $this->db->insert_id : false;
    }

    /**
     * Fetch all questions and their options for a quiz.
     */
    public function getQuestions(int $quizId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_index ASC'
        );
        $stmt->bind_param('i', $quizId);
        $stmt->execute();
        $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($questions as &$q) {
            $optStmt = $this->db->prepare(
                'SELECT id, option_text FROM quiz_options WHERE question_id = ?'
            );
            $optStmt->bind_param('i', $q['id']);
            $optStmt->execute();
            $q['options'] = $optStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        return $questions;
    }

    /**
     * Same as getQuestions() but includes is_correct (for grading / review).
     */
    public function getQuestionsWithAnswers(int $quizId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_index ASC'
        );
        $stmt->bind_param('i', $quizId);
        $stmt->execute();
        $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($questions as &$q) {
            $optStmt = $this->db->prepare(
                'SELECT id, option_text, is_correct FROM quiz_options WHERE question_id = ?'
            );
            $optStmt->bind_param('i', $q['id']);
            $optStmt->execute();
            $q['options'] = $optStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        return $questions;
    }

    /**
     * Delete a question and all its options.
     */
    public function deleteQuestion(int $questionId): bool
    {
        $this->db->prepare('DELETE FROM quiz_options WHERE question_id = ?')
                 ->bind_param('i', $questionId);
        $stmt = $this->db->prepare('DELETE FROM quiz_questions WHERE id = ?');
        $stmt->bind_param('i', $questionId);
        return $stmt->execute();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ATTEMPTS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Start a new quiz attempt and return attempt ID.
     */
    public function startAttempt(int $quizId, int $studentId): int|false
    {
        $stmt = $this->db->prepare(
            'INSERT INTO quiz_attempts (quiz_id, student_id, started_at)
             VALUES (?, ?, NOW())'
        );
        $stmt->bind_param('ii', $quizId, $studentId);
        return $stmt->execute() ? (int) $this->db->insert_id : false;
    }

    /**
     * Save a single answer during the attempt.
     *
     * @param array $data  Keys: question_id, selected_option_id, answer_text (for short answer)
     */
    public function saveAnswer(int $attemptId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO quiz_answers
                (attempt_id, question_id, selected_option_id, answer_text)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                selected_option_id = VALUES(selected_option_id),
                answer_text = VALUES(answer_text)'
        );
        $stmt->bind_param(
            'iiis',
            $attemptId,
            $data['question_id'],
            $data['selected_option_id'] ?? null,
            $data['answer_text']        ?? null
        );
        return $stmt->execute();
    }

    /**
     * Grade and finalise an attempt; returns score percentage.
     */
    public function submitAttempt(int $attemptId): float
    {
        // Fetch attempt quiz
        $attempt = $this->db->prepare(
            'SELECT quiz_id FROM quiz_attempts WHERE id = ? LIMIT 1'
        );
        $attempt->bind_param('i', $attemptId);
        $attempt->execute();
        $quizId = (int) $attempt->get_result()->fetch_row()[0];

        // Total marks available
        $totalRow = $this->db->prepare(
            'SELECT SUM(marks) FROM quiz_questions WHERE quiz_id = ?'
        );
        $totalRow->bind_param('i', $quizId);
        $totalRow->execute();
        $totalMarks = (float) $totalRow->get_result()->fetch_row()[0];

        if ($totalMarks === 0.0) {
            return 0.0;
        }

        // Earned marks (MCQ / true-false only; short answers left ungraded for now)
        $earned = $this->db->prepare(
            'SELECT SUM(qq.marks) AS earned
               FROM quiz_answers qa
               JOIN quiz_options qo ON qo.id = qa.selected_option_id
               JOIN quiz_questions qq ON qq.id = qa.question_id
              WHERE qa.attempt_id = ? AND qo.is_correct = 1'
        );
        $earned->bind_param('i', $attemptId);
        $earned->execute();
        $earnedMarks = (float) $earned->get_result()->fetch_row()[0];

        $score = round(($earnedMarks / $totalMarks) * 100, 2);

        // Persist result
        $update = $this->db->prepare(
            'UPDATE quiz_attempts
                SET score = ?, completed_at = NOW(), is_completed = 1
              WHERE id = ?'
        );
        $update->bind_param('di', $score, $attemptId);
        $update->execute();

        return $score;
    }

    /**
     * Get all attempts for a student on a specific quiz.
     */
    public function getStudentAttempts(int $quizId, int $studentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM quiz_attempts
              WHERE quiz_id = ? AND student_id = ?
              ORDER BY started_at DESC'
        );
        $stmt->bind_param('ii', $quizId, $studentId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Check whether a student has already completed a quiz.
     */
    public function hasCompleted(int $quizId, int $studentId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM quiz_attempts
              WHERE quiz_id = ? AND student_id = ? AND is_completed = 1 LIMIT 1'
        );
        $stmt->bind_param('ii', $quizId, $studentId);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_row();
    }

    /**
     * Fetch detailed attempt result with per-question breakdown.
     */
    public function getAttemptResult(int $attemptId): array|null
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, q.title AS quiz_title, q.pass_mark,
                    u.full_name AS student_name
               FROM quiz_attempts a
               JOIN quizzes q ON q.id = a.quiz_id
               JOIN users u ON u.id = a.student_id
              WHERE a.id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $attemptId);
        $stmt->execute();
        $attempt = $stmt->get_result()->fetch_assoc();

        if (!$attempt) return null;

        // Append per-question answers
        $ansStmt = $this->db->prepare(
            'SELECT qa.question_id, qq.question_text, qq.marks,
                    qa.answer_text, qa.selected_option_id,
                    qo.option_text AS selected_option, qo.is_correct
               FROM quiz_answers qa
               JOIN quiz_questions qq ON qq.id = qa.question_id
               LEFT JOIN quiz_options qo ON qo.id = qa.selected_option_id
              WHERE qa.attempt_id = ?
              ORDER BY qq.order_index ASC'
        );
        $ansStmt->bind_param('i', $attemptId);
        $ansStmt->execute();
        $attempt['answers'] = $ansStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return $attempt;
    }

    /**
     * Leaderboard for a quiz (top 10 scores).
     */
    public function getLeaderboard(int $quizId): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.full_name, MAX(a.score) AS best_score, MIN(a.score) AS lowest_score,
                    COUNT(a.id) AS attempts
               FROM quiz_attempts a
               JOIN users u ON u.id = a.student_id
              WHERE a.quiz_id = ? AND a.is_completed = 1
              GROUP BY a.student_id
              ORDER BY best_score DESC
              LIMIT 10'
        );
        $stmt->bind_param('i', $quizId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}