<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogModel;

/**
 * Works out who hears about a new comment, and in what capacity.
 *
 * One person often wears several hats at once: a solo blogger owns the blog,
 * wrote the post, and may have written the comment being replied to. This
 * resolver returns every reason a recipient qualifies for, most personal
 * first. NotificationService then records one in-app row for the most personal
 * reason and emails at most once, under the most important reason still switched
 * on, so muting one category never silences a broader one the user still wants.
 */
class CommentAudienceResolver
{
    /**
     * A reply to something you wrote.
     */
    public const TYPE_REPLY = 'comment.reply';

    /**
     * A comment on a post you are the author of.
     */
    public const TYPE_AUTHORED = 'comment.on_your_post';

    /**
     * A comment sitting in a moderation queue you are allowed to clear.
     */
    public const TYPE_MODERATION = 'comment.awaiting_moderation';

    /**
     * Any comment on a blog you own.
     */
    public const TYPE_BLOG = 'comment.on_blog';

    /**
     * Most personal reason first. The first type a recipient qualifies for is
     * the one they get, so muting the blog-wide firehose can never also mute a
     * direct reply.
     */
    private const PRECEDENCE = [
        self::TYPE_REPLY,
        self::TYPE_AUTHORED,
        self::TYPE_MODERATION,
        self::TYPE_BLOG,
    ];

    public function __construct(private BlogModel $blogModel) {}

    /**
     * Map every recipient of a comment to the types they qualify for.
     *
     * Each recipient's list is ordered most personal first, so the caller can
     * take the head for the in-app row and walk the list for the first email
     * gate that is on.
     *
     * @param  object  $blog  Blog entity the post belongs to
     * @param  int  $postAuthorId  Author of the commented post, 0 when unknown
     * @param  int|null  $repliedToUserId  Author of the comment being replied to,
     *                                     null for top-level comments and for
     *                                     replies to a guest's comment
     * @param  int|null  $commenterId  The person who just commented, null for guests
     * @param  bool  $awaitingModeration  Whether the comment is held for approval
     * @return array<int, string[]> Recipient user ID => qualifying types, most personal first
     */
    public function resolve(
        object $blog,
        int $postAuthorId,
        ?int $repliedToUserId,
        ?int $commenterId,
        bool $awaitingModeration
    ): array {
        $candidates = [];

        if ($repliedToUserId !== null && $repliedToUserId > 0) {
            $candidates[self::TYPE_REPLY] = [$repliedToUserId];
        }

        if ($postAuthorId > 0) {
            $candidates[self::TYPE_AUTHORED] = [$postAuthorId];
        }

        // A held comment is the only comment notification with a task attached,
        // so it goes to everyone allowed to clear the queue. That is the owner
        // and active editors, matching BlogPolicy::update.
        if ($awaitingModeration) {
            $candidates[self::TYPE_MODERATION] = $this->moderators($blog);
        }

        // The blog-wide firehose is an ownership concern, not a permission one:
        // editors get the moderation duty above without being signed up for
        // every published comment on posts they had nothing to do with.
        $candidates[self::TYPE_BLOG] = [$blog->ownerId()];

        $audience = [];

        // Iterating in precedence order builds each recipient's list already
        // ordered from most to least personal.
        foreach (self::PRECEDENCE as $type) {
            foreach ($candidates[$type] ?? [] as $userId) {
                $userId = (int) $userId;

                // Skip the commenter and rows with no real user behind them.
                if ($userId <= 0 || $userId === $commenterId) {
                    continue;
                }

                if (!isset($audience[$userId])) {
                    $audience[$userId] = [];
                }

                if (!in_array($type, $audience[$userId], true)) {
                    $audience[$userId][] = $type;
                }
            }
        }

        return $audience;
    }

    /**
     * Users who may approve a comment on this blog: the owner and active editors.
     *
     * @param  object  $blog  Blog entity
     * @return int[] User IDs
     */
    private function moderators(object $blog): array
    {
        $moderators = [(int) $blog->ownerId()];

        foreach ($blog->users() as $member) {
            if ((int) ($member['is_active'] ?? 0) !== 1) {
                continue;
            }

            if ($this->blogModel->baseRoleFor((string) ($member['role'] ?? '')) === 'editor') {
                $moderators[] = (int) $member['user_id'];
            }
        }

        return $moderators;
    }
}
