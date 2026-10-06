<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Asks a blog owner or editor to approve a comment held for moderation.
 */
class CommentModerationMail extends CommentMail
{
    protected function subjectLine(): string
    {
        return $this->t('subjects.CommentModerationMail', ['post_title' => $this->postTitle]);
    }

    /**
     * The moderation queue, since the comment they are asked to approve is not
     * on the public page yet. Without a blog to point at, the post will do.
     */
    protected function commentUrl(): string
    {
        return $this->blogId > 0
            ? $this->appUrl().'/dashboard/blog/'.$this->blogId.'/comments'
            : parent::commentUrl();
    }

    /**
     * Moderators already know it is held; saying so again adds nothing.
     */
    protected function moderationNote(): string
    {
        return '';
    }
}
