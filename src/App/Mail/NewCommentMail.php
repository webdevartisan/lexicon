<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Announces a new comment to one recipient.
 *
 * The same comment reaches different people for different reasons, so the
 * reason is passed in rather than inferred: a reply to your own comment reads
 * nothing like a moderation request, even though both describe one event.
 * Those reason-specific sentences stay here because they are logic; the rest
 * of the wording lives in the template binding.
 */
class NewCommentMail extends Mailable
{
    public const REASON_REPLY = 'reply';

    public const REASON_AUTHORED = 'authored';

    public const REASON_MODERATION = 'moderation';

    public const REASON_BLOG = 'blog';

    public function __construct(
        private string $toEmail,
        private string $postTitle,
        private string $blogSlug,
        private string $postSlug,
        private string $commenterName,
        private string $commentExcerpt,
        private bool $awaitingModeration,
        private int $commentId = 0,
        private string $reason = self::REASON_BLOG,
        private int $blogId = 0
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject($this->subjectLine())
            ->fromTemplate([
                'post_title' => $this->postTitle,
                'commenter_name' => $this->commenterName,
                'comment_excerpt' => $this->commentExcerpt,
                'lead' => $this->leadLine(),
                'action_label' => $this->callToAction(),
                'comment_url' => $this->targetUrl(),
                // Moderators already know it is held; saying so again adds nothing.
                'moderation_note' => $this->awaitingModeration && $this->reason !== self::REASON_MODERATION
                    ? 'This comment is awaiting moderation before it appears publicly.'
                    : '',
            ]);
    }

    /**
     * Subject framed by why this person is being told.
     */
    private function subjectLine(): string
    {
        return match ($this->reason) {
            self::REASON_REPLY => 'New reply to your comment on: '.$this->postTitle,
            self::REASON_AUTHORED => 'New comment on your post: '.$this->postTitle,
            self::REASON_MODERATION => 'Comment awaiting your approval on: '.$this->postTitle,
            default => 'New comment on: '.$this->postTitle,
        };
    }

    /**
     * Opening sentence, in the same voice as the subject.
     */
    private function leadLine(): string
    {
        return match ($this->reason) {
            self::REASON_REPLY => "{$this->commenterName} replied to your comment on {$this->postTitle}:",
            self::REASON_AUTHORED => "{$this->commenterName} commented on your post {$this->postTitle}:",
            self::REASON_MODERATION => "{$this->commenterName} commented on {$this->postTitle} and it needs your approval:",
            default => "{$this->commenterName} commented on {$this->postTitle}:",
        };
    }

    private function callToAction(): string
    {
        return $this->reason === self::REASON_MODERATION ? 'Review the comment' : 'View the comment';
    }

    /**
     * Where the button goes.
     *
     * Moderators are sent to the queue, since the comment they are being asked
     * to approve is not rendered on the public page yet. Everyone else gets the
     * post, anchored at the comment when it is publicly visible.
     */
    private function targetUrl(): string
    {
        $appUrl = rtrim((string) (env('APP_URL', 'http://localhost')), '/');

        if ($this->reason === self::REASON_MODERATION && $this->blogId > 0) {
            return $appUrl.'/dashboard/blog/'.$this->blogId.'/comments';
        }

        $url = $appUrl.'/blog/'.rawurlencode($this->blogSlug).'/'.rawurlencode($this->postSlug);

        if ($this->commentId > 0 && !$this->awaitingModeration) {
            $url .= '#comment-'.$this->commentId;
        }

        return $url;
    }
}
