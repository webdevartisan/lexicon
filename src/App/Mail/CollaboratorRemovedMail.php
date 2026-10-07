<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Tells a collaborator they were removed from a blog, and by whom.
 */
class CollaboratorRemovedMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private string $blogName,
        private string $actorHandle
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->fromTemplate([
                'blog_name' => $this->blogName,
                'actor_handle' => $this->actorHandle,
            ]);
    }
}
