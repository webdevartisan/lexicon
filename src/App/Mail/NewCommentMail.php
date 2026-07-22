<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Announces a new comment to one recipient.
 *
 * The same comment reaches different people for different reasons, so the
 * reason is passed in rather than inferred: a reply to your own comment reads
 * nothing like a moderation request, even though both describe one event.
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
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
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

    private function buildHtmlBody(): string
    {
        $lead = htmlspecialchars($this->leadLine());
        $excerpt = htmlspecialchars($this->commentExcerpt);
        $url = htmlspecialchars($this->targetUrl());
        $cta = htmlspecialchars($this->callToAction());

        // Moderators already know it is held; saying so again adds nothing.
        $note = $this->awaitingModeration && $this->reason !== self::REASON_MODERATION
            ? '<p style="color:#92400E;">This comment is awaiting moderation before it appears publicly.</p>'
            : '';

        return <<<HTML
        <!DOCTYPE html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                <h2>New comment</h2>
                <p>{$lead}</p>
                <blockquote style="margin:0;padding:12px 16px;background:#F8FAFC;border-left:3px solid #2563EB;">{$excerpt}</blockquote>
                {$note}
                <p><a href="{$url}" style="display:inline-block;padding:12px 24px;background:#2563EB;color:#fff;text-decoration:none;border-radius:5px;">{$cta}</a></p>
            </div>
        </body></html>
        HTML;
    }

    private function buildTextBody(): string
    {
        $note = $this->awaitingModeration && $this->reason !== self::REASON_MODERATION
            ? "Awaiting moderation.\n"
            : '';

        return "{$this->leadLine()}\n\n\"{$this->commentExcerpt}\"\n{$note}{$this->callToAction()}: {$this->targetUrl()}\n";
    }
}
