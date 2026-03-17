<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Welcome email sent to new users after registration.
 *
 * We send this email immediately after a user successfully registers
 * to confirm their account creation and provide getting-started guidance.
 */
class WelcomeEmail extends Mailable
{
    public function __construct(private array $user)
    {
        parent::__construct();
    }

    /**
     * Build the email content.
     *
     * We compose a friendly welcome message with personalization and
     * clear next steps for the user.
     */
    public function build(): void
    {
        $firstName = $this->user['first_name'] ?? 'there';
        $username = $this->user['username'] ?? '';

        $this->to($this->user['email'], $firstName)
            ->subject('Welcome to '.($_ENV['APP_NAME'] ?? 'Our Blog Platform'))
            ->html($this->buildHtmlBody($firstName, $username))
            ->textAlternative($this->buildTextBody($firstName, $username));
    }

    /**
     * Generate HTML email body.
     *
     * We keep this as a PRIVATE method to avoid signature conflicts with
     * the base Mailable class. This is purely for internal organization.
     *
     * @param  string  $firstName  User's first name
     * @param  string  $username  User's username
     * @return string HTML content
     */
    private function buildHtmlBody(string $firstName, string $username): string
    {
        $appName = htmlspecialchars($_ENV['APP_NAME'] ?? 'Blog Platform');
        $appUrl = htmlspecialchars($_ENV['APP_URL'] ?? 'http://localhost');
        $dashboardUrl = $appUrl.'/dashboard';

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4F46E5; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .button { display: inline-block; padding: 12px 24px; background: #4F46E5; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Welcome to {$appName}!</h1>
                </div>
                <div class="content">
                    <h2>Hello {$firstName},</h2>
                    <p>Thank you for joining our community! Your account has been successfully created.</p>
                    <p><strong>Username:</strong> @{$username}</p>
                    <p>You can now start writing and sharing your stories with our community.</p>
                    <a href="{$dashboardUrl}" class="button">Go to Dashboard</a>
                    <p>If you have any questions, feel free to reach out to our support team.</p>
                </div>
                <div class="footer">
                    <p>&copy; 2026 {$appName}. All rights reserved.</p>
                    <p>You received this email because you registered an account.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * Generate plain text email body.
     *
     * We provide a text-only version for email clients that don't
     * support HTML or for user preference. Kept as PRIVATE to avoid
     * method signature conflicts with the base class.
     *
     * @param  string  $firstName  User's first name
     * @param  string  $username  User's username
     * @return string Plain text content
     */
    private function buildTextBody(string $firstName, string $username): string
    {
        $appName = $_ENV['APP_NAME'] ?? 'Blog Platform';
        $dashboardUrl = ($_ENV['APP_URL'] ?? 'http://localhost').'/dashboard';

        return <<<TEXT
        Welcome to {$appName}!
        
        Hello {$firstName},
        
        Thank you for joining our community! Your account has been successfully created.
        
        Username: @{$username}
        
        You can now start writing and sharing your stories with our community.
        
        Visit your dashboard: {$dashboardUrl}
        
        If you have any questions, feel free to reach out to our support team.
        
        © 2026 {$appName}. All rights reserved.
        TEXT;
    }
}
