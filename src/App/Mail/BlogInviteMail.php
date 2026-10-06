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
            ->subject($this->t('subjects.BlogInviteMail', ['blog_name' => $this->blogName]))
            ->fromTemplate([
                'blog_name' => $this->blogName,
                'role' => $this->roleName($this->role),
                'invite_url' => $this->inviteUrl(),
            ]);
    }

    /**
     * Build the invite landing URL from the configured app URL.
     *
     * We use APP_URL directly (not lurl()) because email is composed outside
     * a request context, matching the PasswordResetEmail convention.
     */
    private function inviteUrl(): string
    {
        $appUrl = rtrim((string) (env('APP_URL', 'http://localhost')), '/');

        return $appUrl.'/invite/'.urlencode($this->rawToken);
    }
}
