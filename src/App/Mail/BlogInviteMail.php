<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\BlogRoleLabels;

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
     * A blog role as the dashboard names it: 'editor' becomes 'Editor', and a
     * custom role like 'co_owner' uses the name the administrator gave it.
     */
    public static function roleLabel(string $role): string
    {
        return app(BlogRoleLabels::class)->label($role);
    }
}
