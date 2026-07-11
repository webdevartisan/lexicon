<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Per-blog email subscribers for new post notifications.
 */
class BlogSubscriberModel extends AppModel
{
    protected ?string $table = 'blog_subscribers';

    /**
     * Add an email to a blog's subscriber list.
     *
     * Idempotent: re-subscribing an existing address succeeds quietly.
     *
     * @return bool True when subscribed (new or already present)
     */
    public function subscribe(int $blogId, string $email, ?int $userId = null): bool
    {
        try {
            $this->database->query(
                "INSERT INTO {$this->getTable()} (blog_id, user_id, email, token) VALUES (?, ?, ?, ?)",
                [$blogId, $userId, $email, bin2hex(random_bytes(32))]
            );
        } catch (\PDOException $e) {
            // Unique key on (blog_id, email): already subscribed is a success
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByToken(string $token): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE token = ? LIMIT 1";

        $row = $this->database->query($sql, [$token])->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function deleteByToken(string $token): bool
    {
        return $this->database->execute(
            "DELETE FROM {$this->getTable()} WHERE token = ? LIMIT 1",
            [$token]
        ) > 0;
    }

    /**
     * All subscribers of a blog, for the notification fan-out.
     *
     * @return array<int, array{email: string, token: string}>
     */
    public function forBlog(int $blogId): array
    {
        $sql = "SELECT email, token FROM {$this->getTable()} WHERE blog_id = ? ORDER BY id";

        return $this->database->query($sql, [$blogId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countForBlog(int $blogId): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->getTable()} WHERE blog_id = ?";

        return (int) $this->database->query($sql, [$blogId])->fetchColumn();
    }
}
