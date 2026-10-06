<?php

declare(strict_types=1);

namespace App\Mail;

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
            ->subject('You were removed from '.$this->blogName)
            ->fromTemplate([
                'blog_name' => $this->blogName,
                'actor_handle' => $this->actorHandle,
            ]);
    }
}
