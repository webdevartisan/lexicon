<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogSubscriberModel;
use App\Models\CommentModel;
use App\Models\NotificationModel;
use App\Models\PostBookmarkModel;
use App\Models\PostVoteModel;

/**
 * The reader's own things: what they saved, liked, follow, and said.
 *
 * Every list is a personal slice of a table somebody else's feature owns, so
 * the queries stay in the models and this holds the decisions: which events
 * count as a reply, how big a page is, and when a subscription gets adopted by
 * the account that turns out to own it.
 */
final class ReaderService
{
    /**
     * Rows per page on every reader list.
     *
     * These lists are personal and small. Twenty keeps a page short enough to
     * scan and a `?page=3` worth linking to.
     */
    public const PER_PAGE = 20;

    /**
     * Notification types that belong in Replies.
     *
     * Exactly one, and it is the resolver's constant rather than the string, so
     * renaming the event breaks the build instead of quietly emptying the
     * inbox. Replies is not a general notification list: the other comment
     * types are a creator's own content, a moderation queue, and a blog
     * firehose, and all three are dashboard concerns.
     *
     * @var array<int, string>
     */
    public const REPLY_TYPES = [CommentAudienceResolver::TYPE_REPLY];

    /**
     * Memoised badge count, so a page rendering the menu twice costs one query.
     *
     * @var array<int, int>
     */
    private array $unreadReplies = [];

    public function __construct(
        private PostBookmarkModel $bookmarks,
        private PostVoteModel $votes,
        private NotificationModel $notifications,
        private CommentModel $comments,
        private BlogSubscriberModel $subscribers
    ) {}

    /**
     * Unread replies for the masthead badge.
     */
    public function unreadReplyCount(int $userId): int
    {
        return $this->unreadReplies[$userId] ??= $this->notifications->unreadReplyCount($userId, self::REPLY_TYPES);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int}
     */
    public function saved(int $userId, int $page): array
    {
        return $this->bookmarks->pageForUser($userId, $page, self::PER_PAGE);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int}
     */
    public function liked(int $userId, int $page): array
    {
        return $this->votes->pageOfLikesForUser($userId, $page, self::PER_PAGE);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int}
     */
    public function replies(int $userId, int $page): array
    {
        return $this->notifications->pageOfRepliesForUser($userId, self::REPLY_TYPES, $page, self::PER_PAGE);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int}
     */
    public function myComments(int $userId, int $page): array
    {
        return $this->comments->pageForAuthor($userId, $page, self::PER_PAGE);
    }

    /**
     * The blogs the reader follows, adopting any they own only by email first.
     *
     * The backfill runs here rather than at signup because there is no moment
     * at signup when the address is known to be confirmed, and because doing it
     * on sight means an address changed later heals on the next visit too.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int}
     */
    public function subscriptions(int $userId, string $email, int $page): array
    {
        $this->subscribers->claimOrphansForUser($userId, $email);

        return $this->subscribers->pageForUser($userId, $email, $page, self::PER_PAGE);
    }

    /**
     * Mark the replies rendered on a page as read.
     *
     * Also sweeps up replies the list can never show: a notification whose
     * comment was since deleted, unapproved, or whose post or blog came down
     * stays unread forever otherwise, leaving the reader a badge with no way to
     * clear it. Both happen here so the badge always agrees with what a visit
     * to Replies can actually settle.
     *
     * @param  array<int, int|string>  $ids  Notification ids the page carried
     * @return int Rows marked
     */
    public function markRepliesRead(int $userId, array $ids): int
    {
        $marked = $this->notifications->markReadForUser($userId, $ids);
        $marked += $this->notifications->markUnreachableRepliesRead($userId, self::REPLY_TYPES);

        // The memoised count is now stale, and the caller reads it straight
        // after to answer the badge.
        unset($this->unreadReplies[$userId]);

        return $marked;
    }
}
