<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Services\MailService;
use Exception;
use Framework\Core\Response;

/**
 * Email Delivery: whether the site can send mail at all.
 *
 * Shows the mail server settings from the environment and sends a plain
 * message to check them. What each email says and looks like, and test sends
 * of a particular email, belong to that email's editor under Email Templates.
 *
 * Security: All methods are protected by admin authentication
 * middleware defined in routes configuration.
 */
class EmailTestController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageSettings';

    public function __construct(
        private MailService $mailService,
    ) {}

    /**
     * The mail server settings and the connection test form.
     */
    public function index(): Response
    {
        return $this->view([
            'mailConfig' => $this->getMailConfigSummary(),
            'pageTitle' => 'Email Delivery',
        ]);
    }

    /**
     * Mail configuration facts for read-only display.
     *
     * We never expose SMTP credentials in the UI for security.
     *
     * @return array<string, mixed> Sanitized mail settings
     */
    private function getMailConfigSummary(): array
    {
        return [
            'enabled' => (bool) env('MAIL_ENABLED', false),
            'driver' => (string) env('MAIL_DRIVER', 'not set'),
            'host' => (string) env('MAIL_HOST', 'not set'),
            'port' => (string) env('MAIL_PORT', 'not set'),
            'from_address' => (string) env('MAIL_FROM_ADDRESS', 'not set'),
            'from_name' => (string) env('MAIL_FROM_NAME', 'not set'),
            'encryption' => (string) env('MAIL_ENCRYPTION', 'tls'),
        ];
    }

    /**
     * Test mail configuration with simple test email.
     *
     * send a basic test email to verify SMTP settings are correct
     * before testing complex templates.
     */
    public function testConfig(): Response
    {
        // enforce CSRF protection
        csrf()->assertValid($this->request->postParam('_token'));

        $recipient = (string) $this->request->postParam('recipient', '');

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'Invalid email address');

            return $this->redirect('/admin/email-test');
        }

        try {
            $sent = $this->mailService->test($recipient);

            if ($sent) {
                $this->flash('success', "Configuration test email sent to {$recipient}");
            } else {
                $this->flash('error', 'Failed to send test email - check mail configuration');
            }

        } catch (Exception $e) {
            error_log('Mail config test failed: '.$e->getMessage());
            $this->flash('error', 'Mail error: '.$e->getMessage());
        }

        return $this->redirect('/admin/email-test');
    }
}
