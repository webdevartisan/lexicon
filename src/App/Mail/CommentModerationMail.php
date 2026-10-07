<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Asks a blog owner or editor to approve a comment held for moderation.
 */
class CommentModerationMail extends CommentMail
{
    /**
     * The blog's comment queue, where the comment can be approved.
     */
    protected function commentUrl(): string
    {
        return $this->blogId > 0
            ? $this->url('/dashboard/blog/'.$this->blogId.'/comments')
            : parent::commentUrl();
    }
}
