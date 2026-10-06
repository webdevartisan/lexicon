<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Tells someone a reader replied to a comment they wrote.
 */
class CommentReplyMail extends CommentMail
{
    protected function subjectLine(): string
    {
        return 'New reply to your comment on: '.$this->postTitle;
    }
}
