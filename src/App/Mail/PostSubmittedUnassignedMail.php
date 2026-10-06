<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Email sent to every owner, editor and reviewer of a blog when a post is
 * submitted for review and nobody is assigned to it yet, so one of them can
 * claim it. Its own class so its wording can say so on its own.
 */
class PostSubmittedUnassignedMail extends PostSubmittedMail
{
    protected function subjectLine(): string
    {
        return 'Waiting for a reviewer: '.$this->postTitle;
    }
}
