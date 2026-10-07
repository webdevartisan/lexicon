<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Tells a collaborator their role on a blog was changed, and by whom.
 */
class CollaboratorRoleChangedMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private string $blogName,
        private string $newRole,
        private string $actorHandle
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->fromTemplate([
                'blog_name' => $this->blogName,
                'new_role' => BlogInviteMail::roleLabel($this->newRole),
                'actor_handle' => $this->actorHandle,
                'dashboard_url' => $this->url('/dashboard'),
            ]);
    }
}
