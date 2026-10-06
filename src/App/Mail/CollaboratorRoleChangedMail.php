<?php

declare(strict_types=1);

namespace App\Mail;

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
            ->subject('Your role on '.$this->blogName.' changed to '.$this->newRole)
            ->fromTemplate([
                'blog_name' => $this->blogName,
                'new_role' => $this->newRole,
                'actor_handle' => $this->actorHandle,
            ]);
    }
}
