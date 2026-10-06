<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Tells a blog owner a reader commented somewhere on their blog.
 */
class BlogCommentMail extends CommentMail
{
    protected function subjectLine(): string
    {
        return 'New comment on: '.$this->postTitle;
    }
}
