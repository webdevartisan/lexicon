<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Email sent to a new or existing user when invited to collaborate on a blog.
 *
 * The link points to the invite landing page (GET /invite/{token}), where the
 * recipient confirms accept/decline via a CSRF-protected form. The raw token
 * travels only in this link — never persisted.
 */
class BlogInviteMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private string $rawToken,
        private string $blogName,
        private string $role
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->fromTemplate([
                'blog_name' => $this->blogName,
                'role' => self::roleLabel($this->role),
                'invite_url' => $this->url('/invite/'.urlencode($this->rawToken)),
            ]);
    }

    /**
     * A blog role as the dashboard names it: 'editor' becomes 'Editor'.
     */
    public static function roleLabel(string $role): string
    {
        return ucfirst($role);
    }
}
