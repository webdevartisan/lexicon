<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Email sent to a post's assigned reviewers when its author submits it for
 * review. When nobody is assigned yet, PostSubmittedUnassignedMail goes to
 * everyone who could pick it up instead.
 */
class PostSubmittedMail extends Mailable
{
    public function __construct(
        protected string $toEmail,
        protected int $postId,
        protected string $postTitle,
        protected string $authorHandle
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject($this->subjectLine())
            ->fromTemplate([
                'post_title' => $this->postTitle,
                'author_handle' => $this->authorHandle,
                'review_url' => $this->reviewUrl(),
            ]);
    }

    /**
     * The default subject, before any control panel override.
     */
    protected function subjectLine(): string
    {
        return $this->t('subjects.PostSubmittedMail', ['post_title' => $this->postTitle]);
    }

    private function reviewUrl(): string
    {
        $appUrl = rtrim((string) (env('APP_URL', 'http://localhost')), '/');

        return $appUrl.'/dashboard/posts/'.$this->postId.'/review';
    }
}
