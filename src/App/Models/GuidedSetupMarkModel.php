<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

/**
 * The parts of guided setup progress that can't be derived from other data,
 * stored per person and per blog.
 */
class GuidedSetupMarkModel extends AppModel
{
    public const MARKS = ['hidden', 'dismissed', 'link_copied', 'look_chosen', 'subscribers_seen', 'profile_saved'];

    protected ?string $table = 'guided_setup_marks';

    /**
     * Set a mark. Setting one that already exists changes nothing.
     *
     * @throws InvalidArgumentException When the mark isn't one of MARKS
     */
    public function mark(int $userId, int $blogId, string $mark): void
    {
        $this->assertKnown($mark);

        $this->database->execute(
            "INSERT IGNORE INTO {$this->getTable()} (user_id, blog_id, mark) VALUES (?, ?, ?)",
            [$userId, $blogId, $mark]
        );
    }

    /**
     * Set a mark on every blog the person writes for as a collaborator, for
     * account-wide actions such as saving their profile.
     *
     * @throws InvalidArgumentException When the mark isn't one of MARKS
     */
    public function markMemberBlogs(int $userId, string $mark): void
    {
        $this->assertKnown($mark);

        $this->database->execute(
            "INSERT IGNORE INTO {$this->getTable()} (user_id, blog_id, mark)
             SELECT user_id, blog_id, ? FROM blog_users WHERE user_id = ? AND is_active = 1",
            [$mark, $userId]
        );
    }

    /**
     * Remove a mark if it is set.
     *
     * @throws InvalidArgumentException When the mark isn't one of MARKS
     */
    public function unmark(int $userId, int $blogId, string $mark): void
    {
        $this->assertKnown($mark);

        $this->database->execute(
            "DELETE FROM {$this->getTable()} WHERE user_id = ? AND blog_id = ? AND mark = ?",
            [$userId, $blogId, $mark]
        );
    }

    /**
     * The marks one person has on one blog.
     *
     * @return array<string, true> Keyed by mark name
     */
    public function marksFor(int $userId, int $blogId): array
    {
        $names = $this->database->query(
            "SELECT mark FROM {$this->getTable()} WHERE user_id = ? AND blog_id = ? ORDER BY mark",
            [$userId, $blogId]
        )->fetchAll(\PDO::FETCH_COLUMN);

        return array_fill_keys($names, true);
    }

    private function assertKnown(string $mark): void
    {
        if (!in_array($mark, self::MARKS, true)) {
            throw new InvalidArgumentException("Unknown guided setup mark: {$mark}");
        }
    }
}
