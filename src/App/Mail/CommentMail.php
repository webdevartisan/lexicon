<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * What every new-comment email has in common.
 *
 * The same comment reaches different people for different reasons: a reply to
 * your own comment, a comment on your post, one waiting for your approval, or
 * activity anywhere on your blog. Each reason is its own email class with its
 * own binding, so admins can word each one separately; this base only gathers
 * the comment's data and works out where its link goes.
 */
abstract class CommentMail extends Mailable
{
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
     * The default subject, framed by why this person is being told.
     */
    abstract protected function subjectLine(): string;

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject($this->subjectLine())
            ->fromTemplate([
                'post_title' => $this->postTitle,
                'commenter_name' => $this->commenterName,
                'comment_excerpt' => $this->commentExcerpt,
                'comment_url' => $this->commentUrl(),
                'moderation_note' => $this->moderationNote(),
            ]);
    }

    /**
     * Where the button goes: the post, anchored at the comment once it is
     * publicly visible (a held comment is not on the page yet).
     */
    protected function commentUrl(): string
    {
        $url = $this->appUrl().'/blog/'.rawurlencode($this->blogSlug).'/'.rawurlencode($this->postSlug);

        if ($this->commentId > 0 && !$this->awaitingModeration) {
            $url .= '#comment-'.$this->commentId;
        }

        return $url;
    }

    /**
     * Why the comment is not on the page yet, or '' so the note is left out.
     */
    protected function moderationNote(): string
    {
        return $this->awaitingModeration ? 'This comment is awaiting moderation before it appears publicly.' : '';
    }

    protected function appUrl(): string
    {
        return rtrim((string) env('APP_URL', 'http://localhost'), '/');
    }
}
