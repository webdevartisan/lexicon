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
            ->subject('You have been invited to collaborate on '.$this->blogName)
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
    }

    /**
     * Build the invite landing URL from the configured app URL.
     *
     * We use APP_URL directly (not lurl()) because email is composed outside
     * a request context, matching the PasswordResetEmail convention.
     */
    private function inviteUrl(): string
    {
        $appUrl = rtrim((string) ($_ENV['APP_URL'] ?? 'http://localhost'), '/');

        return $appUrl.'/invite/'.urlencode($this->rawToken);
    }

    private function buildHtmlBody(): string
    {
        $appName = htmlspecialchars((string) ($_ENV['APP_NAME'] ?? 'Blog Platform'));
        $blogName = htmlspecialchars($this->blogName);
        $role = htmlspecialchars($this->role);
        $url = htmlspecialchars($this->inviteUrl());

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2>You've been invited to collaborate</h2>
                <p>You've been invited to join <strong>{$blogName}</strong> as <strong>{$role}</strong>.</p>
                <p>
                    <a href="{$url}" style="display:inline-block;padding:12px 24px;background:#2563EB;color:#fff;text-decoration:none;border-radius:5px;">
                        View invitation
                    </a>
                </p>
                <p>This invitation expires in 7 days. If you don't have an account yet, you'll be guided through registration first.</p>
                <p style="font-size:12px;color:#666;">&copy; 2026 {$appName}</p>
            </div>
        </body>
        </html>
        HTML;
    }

    private function buildTextBody(): string
    {
        $url = $this->inviteUrl();

        return <<<TEXT
        You've been invited to collaborate on {$this->blogName} as {$this->role}.

        View the invitation here:
        {$url}

        This invitation expires in 7 days. If you don't have an account yet, you'll be guided through registration first.
        TEXT;
    }
}
