<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Tells a post's author a reader commented on it.
 */
class PostCommentMail extends CommentMail
{
    protected function subjectLine(): string
    {
        return $this->t('subjects.PostCommentMail', ['post_title' => $this->postTitle]);
    }
}
