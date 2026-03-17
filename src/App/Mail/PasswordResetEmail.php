<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Password reset email with secure token link.
 *
 * We send this when a user requests a password reset. The email contains
 * a time-limited token link for security.
 */
class PasswordResetEmail extends Mailable
{
    public function __construct(
        private array $user,
        private string $token,
        private int $expiresInMinutes = 60
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $firstName = $this->user['first_name'] ?? 'there';

        $this->to($this->user['email'], $firstName)
            ->subject('Password Reset Request')
            ->html($this->buildHtmlBody($firstName))
            ->textAlternative($this->buildTextBody($firstName));
    }

    /**
     * Generate HTML email body.
     *
     * We keep this PRIVATE to avoid signature conflicts with the base class.
     * This is purely an internal helper for better code organization.
     *
     * @param  string  $firstName  User's first name
     * @return string HTML content
     */
    private function buildHtmlBody(string $firstName): string
    {
        $appName = htmlspecialchars($_ENV['APP_NAME'] ?? 'Blog Platform');
        $appUrl = htmlspecialchars($_ENV['APP_URL'] ?? 'http://localhost');
        $resetUrl = $appUrl.'/password/reset/'.urlencode($this->token);

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .content { padding: 20px; background: #f9f9f9; }
                .button { display: inline-block; padding: 12px 24px; background: #DC2626; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .warning { background: #FEF2F2; border-left: 4px solid #DC2626; padding: 12px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="content">
                    <h2>Password Reset Request</h2>
                    <p>Hello {$firstName},</p>
                    <p>We received a request to reset your password. Click the button below to create a new password:</p>
                    <a href="{$resetUrl}" class="button">Reset Password</a>
                    <p>This link will expire in {$this->expiresInMinutes} minutes.</p>
                    <div class="warning">
                        <strong>Security Notice:</strong> If you didn't request this password reset, please ignore this email. Your password will remain unchanged.
                    </div>
                    <p>For security reasons, we cannot send your existing password. If you're having trouble, contact our support team.</p>
                </div>
                <div class="footer">
                    <p>&copy; 2026 {$appName}. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * Generate plain text email body.
     *
     * We provide a text alternative for accessibility and compatibility.
     * Kept PRIVATE to avoid method signature conflicts.
     *
     * @param  string  $firstName  User's first name
     * @return string Plain text content
     */
    private function buildTextBody(string $firstName): string
    {
        $appName = $_ENV['APP_NAME'] ?? 'Blog Platform';
        $appUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        $resetUrl = $appUrl.'/password/reset/'.$this->token;

        return <<<TEXT
        Password Reset Request
        
        Hello {$firstName},
        
        We received a request to reset your password. Visit the link below to create a new password:
        
        {$resetUrl}
        
        This link will expire in {$this->expiresInMinutes} minutes.
        
        SECURITY NOTICE: If you didn't request this password reset, please ignore this email. Your password will remain unchanged.
        
        For security reasons, we cannot send your existing password. If you're having trouble, contact our support team.
        
        © 2026 {$appName}. All rights reserved.
        TEXT;
    }
}
