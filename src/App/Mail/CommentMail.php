<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\CommentAudienceResolver;

/**
 * What every new-comment email has in common.
 *
 * The same comment reaches different people for different reasons: a reply to
 * your own comment, a comment on your post, one waiting for your approval, or
 * activity anywhere on your blog. A comment held for approval is a different
 * situation again. Each is its own email class, so admins can word each one
 * separately; this base only gathers the comment's data and works out where
 * its link goes. for() picks the class.
 */
abstract class CommentMail extends Mailable
{
    /**
     * Comment notification type => [the email when the comment is visible, the
     * email when it is held for approval].
     *
     * @var array<string, array{0: class-string<CommentMail>, 1: class-string<CommentMail>}>
     */
    private const BY_TYPE = [
        CommentAudienceResolver::TYPE_REPLY => [CommentReplyMail::class, CommentReplyPendingMail::class],
        CommentAudienceResolver::TYPE_AUTHORED => [PostCommentMail::class, PostCommentPendingMail::class],
        CommentAudienceResolver::TYPE_MODERATION => [CommentModerationMail::class, CommentModerationMail::class],
        CommentAudienceResolver::TYPE_BLOG => [BlogCommentMail::class, BlogCommentPendingMail::class],
    ];

    public function __construct(
        protected string $toEmail,
        protected string $postTitle,
        protected string $blogSlug,
        protected string $postSlug,
        protected string $commenterName,
        protected string $commentExcerpt,
        protected bool $awaitingModeration,
        protected int $commentId = 0,
        protected int $blogId = 0
    ) {
        parent::__construct();
    }

    /**
     * The email for a comment notification type, held for approval or not.
     *
     * @return class-string<CommentMail>
     */
    public static function for(string $type, bool $awaitingModeration): string
    {
        $classes = self::BY_TYPE[$type] ?? throw new \InvalidArgumentException("No comment email for '{$type}'.");

        return $classes[$awaitingModeration ? 1 : 0];
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->fromTemplate([
                'post_title' => $this->postTitle,
                'commenter_name' => $this->commenterName,
                'comment_excerpt' => $this->commentExcerpt,
                'comment_url' => $this->commentUrl(),
            ]);
    }

    /**
     * The comment on its post, or just the post while the comment is held
     * back, since a held comment has nothing on the page to jump to.
     */
    protected function commentUrl(): string
    {
        $url = $this->url('/blog/'.rawurlencode($this->blogSlug).'/'.rawurlencode($this->postSlug));

        if ($this->commentId > 0 && !$this->awaitingModeration) {
            $url .= '#comment-'.$this->commentId;
        }

        return $url;
    }
}
