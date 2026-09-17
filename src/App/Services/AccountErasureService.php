<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\UploadServiceInterface;
use App\Models\AccountErasureRecordModel;
use App\Models\UserModel;
use Framework\Database;
use InvalidArgumentException;
use RuntimeException;

/**
 * Erases an account: the person's data is deleted, not renamed.
 *
 * The account holder's own posts, comments and blogs are always erased.
 * There is no "keep them, hidden" choice, because the 30-day grace period
 * already covers wanting the account back, and the private accountability
 * trail already covers legal or abuse follow-up. A post goes with its
 * author, comments and all, since a comment has no life apart from the post
 * it is on. A blog goes with its owner the same way, including a departed
 * collaborator's old post: a blog with an active collaborator already blocks
 * erasure (see `blockers()`), so the owner transfers it deliberately through
 * the team page first if it should survive. Renaming the account in place
 * would not count as erasure, since the row would still tie everything back
 * to one person.
 */
class AccountErasureService
{
    private const DELETED_NAME = 'Deleted user';

    public function __construct(
        private Database $database,
        private UserModel $users,
        private BlogDeletionService $blogDeletion,
        private UploadServiceInterface $uploader,
        private MediaUsageResolver $mediaUsage,
        private PublicCacheInvalidator $cacheInvalidator,
        private AccountErasureRecordModel $erasureRecords,
        private string $deletedUserHandle,
    ) {}

    /**
     * What has to be sorted out before this account can be erased.
     *
     * @return array{last_administrator: bool, shared_blogs: list<array{id: int, blog_name: string}>, reported_content: bool}
     */
    public function blockers(int $userId): array
    {
        $isAdministrator = in_array('administrator', $this->users->getUserRoles($userId), true);

        $sharedBlogs = $this->database->query(
            'SELECT b.id, b.blog_name
             FROM blogs b
             WHERE b.owner_id = ?
               AND EXISTS (SELECT 1 FROM blog_users bu WHERE bu.blog_id = b.id AND bu.is_active = 1)
             ORDER BY b.blog_name',
            [$userId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'last_administrator' => $isAdministrator && $this->users->countAdministrators() <= 1,
            'shared_blogs' => array_map(
                fn (array $blog): array => ['id' => (int) $blog['id'], 'blog_name' => (string) $blog['blog_name']],
                $sharedBlogs
            ),
            // A report has no separate "resolved" state here: it stands until the blog
            // team acts on the post or comment itself, so any row at all still counts.
            'reported_content' => (bool) $this->database->query(
                'SELECT EXISTS(
                    SELECT 1 FROM posts WHERE author_id = ? AND reports_count > 0
                    UNION ALL
                    SELECT 1 FROM comments WHERE user_id = ? AND reports_count > 0
                 )',
                [$userId, $userId]
            )->fetchColumn(),
        ];
    }

    public function canErase(int $userId): bool
    {
        $blockers = $this->blockers($userId);

        return !$blockers['last_administrator'] && !$blockers['reported_content'] && $blockers['shared_blogs'] === [];
    }

    public function isDeletedUserAccount(int $userId): bool
    {
        return $userId === $this->deletedUserId();
    }

    /**
     * @param  int|null  $erasedBy  Who triggered it: the account itself, or an administrator
     *
     * @throws InvalidArgumentException For the shared deleted-user account
     * @throws RuntimeException When something still blocks erasure or any step fails
     */
    public function erase(int $userId, ?int $erasedBy = null, ?string $erasedByIp = null): void
    {
        $deletedUserId = $this->deletedUserId();

        if ($userId === $deletedUserId) {
            throw new InvalidArgumentException('The shared deleted-user account cannot be erased.');
        }

        $user = $this->database->query(
            'SELECT id, handle, email, display_name_cached FROM users WHERE id = ?',
            [$userId]
        )->fetch(\PDO::FETCH_ASSOC);

        if ($user === false) {
            throw new RuntimeException("Account {$userId} does not exist.");
        }

        if (!$this->canErase($userId)) {
            throw new RuntimeException("Account {$userId} still owns blogs with collaborators or is the last administrator.");
        }

        // Read before the blogs go: deleting a blog takes the posts and comments
        // inside it, so reading these afterwards would miss everything the account
        // wrote in its own blogs.
        $postIds = array_map('intval', $this->database->query('SELECT id FROM posts WHERE author_id = ?', [$userId])->fetchAll(\PDO::FETCH_COLUMN));
        $commentIds = array_map('intval', $this->database->query('SELECT id FROM comments WHERE user_id = ?', [$userId])->fetchAll(\PDO::FETCH_COLUMN));

        // Active collaborators already blocked erasure above, so nothing here
        // has a stake worth protecting. Each blog deletes in its own
        // transaction with its files, so a retry after a failure carries on
        // with whatever is left.
        foreach ($this->ownedBlogIds($userId) as $blogId) {
            $this->blogDeletion->deleteBlog($blogId, $userId);
        }

        $this->database->transaction(function () use ($user, $userId, $deletedUserId, $postIds, $commentIds, $erasedBy, $erasedByIp): void {
            $this->eraseOwnContent($userId, $deletedUserId);

            $this->erasureRecords->record(
                $userId,
                (string) $user['handle'],
                (string) $user['email'],
                $postIds,
                $commentIds,
                $erasedBy,
                $erasedByIp
            );

            $this->forgetPerson($user);

            $votedComments = $this->database->query(
                'SELECT comment_id FROM comment_votes WHERE user_id = ?',
                [$userId]
            )->fetchAll(\PDO::FETCH_COLUMN);

            $this->database->execute('DELETE FROM users WHERE id = ?', [$userId]);

            $this->recountCommentVotes($votedComments);
        });

        $this->uploader->deleteProfileUploads($userId);
        $this->deleteUnusedBlogUploads($userId);
        $this->uploader->deleteEmptyUserFolder($userId);

        $this->cacheInvalidator->purgeAuthorSurfaces((string) $user['handle']);
        $this->cacheInvalidator->purgeHome();
        $this->cacheInvalidator->purgeExplore();
    }

    public function deletedUserId(): int
    {
        $id = $this->database->query('SELECT id FROM users WHERE handle = ?', [$this->deletedUserHandle])->fetchColumn();

        if ($id === false) {
            throw new RuntimeException("The shared account '{$this->deletedUserHandle}' is missing, so reviews and submissions have nowhere to go.");
        }

        return (int) $id;
    }

    /**
     * @return list<int>
     */
    private function ownedBlogIds(int $userId): array
    {
        return array_map(
            'intval',
            $this->database->query('SELECT id FROM blogs WHERE owner_id = ?', [$userId])->fetchAll(\PDO::FETCH_COLUMN)
        );
    }

    private function eraseOwnContent(int $userId, int $deletedUserId): void
    {
        // A comment someone else replied to is emptied instead, so the replies keep their thread.
        $this->database->execute(
            "UPDATE comments c
             JOIN (
                 SELECT DISTINCT parent_comment_id AS id
                 FROM comments
                 WHERE parent_comment_id IS NOT NULL AND (user_id IS NULL OR user_id <> ?)
             ) replied ON replied.id = c.id
             SET c.content = '', c.user_id = NULL, c.deleted_at = COALESCE(c.deleted_at, UTC_TIMESTAMP())
             WHERE c.user_id = ?",
            [$userId, $userId]
        );

        $this->database->execute('DELETE FROM comments WHERE user_id = ?', [$userId]);

        // A post's comments cascade with it (comments.post_id is ON DELETE
        // CASCADE), including anything someone else wrote on it.
        $this->database->execute('DELETE FROM posts WHERE author_id = ?', [$userId]);

        $this->database->execute('UPDATE reviews SET reviewer_id = ? WHERE reviewer_id = ?', [$deletedUserId, $userId]);
        $this->database->execute('UPDATE submissions SET contributor_id = ? WHERE contributor_id = ?', [$deletedUserId, $userId]);
    }

    /**
     * Remove the person from places that are not theirs but still name them.
     *
     * @param  array{id: int|string, handle: string, email: string, display_name_cached: string|null}  $user
     */
    private function forgetPerson(array $user): void
    {
        $userId = (int) $user['id'];
        $email = (string) $user['email'];
        $handle = (string) $user['handle'];
        $names = array_values(array_unique(array_filter([$handle, (string) $user['display_name_cached']])));

        $this->database->execute('DELETE FROM blog_subscribers WHERE user_id = ? OR email = ?', [$userId, $email]);
        $this->database->execute('DELETE FROM blog_invitations WHERE email = ?', [$email]);
        $this->database->execute('DELETE FROM password_resets WHERE email = ?', [$email]);
        $this->database->execute('DELETE FROM mail_queue WHERE to_email = ?', [$email]);
        $this->database->execute('UPDATE comments SET deleted_by = NULL WHERE deleted_by = ?', [$userId]);

        foreach (['actor_handle', 'author_handle'] as $key) {
            $this->database->execute(
                "UPDATE notifications SET data = JSON_SET(data, '$.{$key}', ?)
                 WHERE JSON_UNQUOTE(JSON_EXTRACT(data, '$.{$key}')) = ?",
                [$this->deletedUserHandle, $handle]
            );
        }

        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $this->database->execute(
            "UPDATE notifications SET data = JSON_SET(data, '$.commenter_name', ?, '$.comment_excerpt', '')
             WHERE JSON_UNQUOTE(JSON_EXTRACT(data, '$.commenter_name')) IN ({$placeholders})",
            [self::DELETED_NAME, ...$names]
        );

        // The audit trail keeps what happened, not who it happened to.
        $this->database->execute(
            'UPDATE activity_log SET details = REPLACE(details, ?, ?) WHERE details LIKE ?',
            [$email, '[deleted]', '%'.$email.'%']
        );
        $this->database->execute(
            "UPDATE activity_log SET details = REPLACE(details, ?, ?)
             WHERE (user_id = ? OR (resource_type = 'user' AND resource_id = ?)) AND details LIKE ?",
            [json_encode($handle), '"[deleted]"', $userId, $userId, '%'.$handle.'%']
        );
    }

    /**
     * @param  list<int|string>  $commentIds
     */
    private function recountCommentVotes(array $commentIds): void
    {
        if ($commentIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));

        $this->database->execute(
            "UPDATE comments c
             SET c.upvotes = (SELECT COUNT(*) FROM comment_votes v WHERE v.comment_id = c.id AND v.value = 1),
                 c.downvotes = (SELECT COUNT(*) FROM comment_votes v WHERE v.comment_id = c.id AND v.value = -1)
             WHERE c.id IN ({$placeholders})",
            array_map('intval', $commentIds)
        );
    }

    /**
     * Their deleted posts are gone, so files they uploaded that nothing else uses go too.
     * Images still shown by someone else's post or a blog's branding stay.
     */
    private function deleteUnusedBlogUploads(int $userId): void
    {
        foreach ($this->uploader->blogUploadsBy($userId) as $blogId => $urls) {
            foreach ($urls as $url) {
                if ($this->mediaUsage->usages($blogId, $url) !== []) {
                    continue;
                }

                $this->uploader->deleteUpload($url);
                $this->database->execute('DELETE FROM media WHERE disk_path = ?', [ltrim($url, '/')]);
            }
        }
    }
}
