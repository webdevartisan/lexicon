<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\Controllers\AppController;
use App\Models\SettingModel;
use App\Services\MailService;
use Framework\Core\Response;

/**
 * Manage site-wide settings.
 *
 * We organize settings into logical sections (identity, content, users, email)
 * to keep the interface manageable as the platform grows.
 */
final class SettingController extends AppController
{
    public function __construct(
        protected Auth $auth,
        private SettingModel $settings,
        private MailService $mail,
    ) {}

    /**
     * Display all settings organized by section.
     */
    public function index(): Response
    {
        return $this->view([
            'settings' => $this->settings->all(),
            'mail_config' => $this->getMailConfig(),
        ]);
    }

    /**
     * Save general settings (identity, content, users).
     */
    public function update(): Response
    {
        // enforce CSRF protection
        csrf()->assertValid($this->request->postParam('_token'));

        // validate all form input using the validation framework
        $validator = $this->validateOrFail([
            // site identity
            'site_name' => 'required|min:2|max:100',
            'site_description' => 'max:255',
            'site_tagline' => 'max:100',
            'admin_email' => 'required|email',
            'timezone' => 'required',

            // content
            'posts_per_page' => 'required|integer|min:1|max:50',
            'excerpt_length' => 'required|integer|min:50|max:500',
            'date_format' => 'required',
            'allow_comments' => 'boolean',

            // registration
            'registration_enabled' => 'boolean',
            'default_user_role' => 'required|integer',
        ]);

        $data = $validator->validated();

        // persist each setting using batch update for better performance
        $this->settings->setMany($data);

        $this->flash('success', 'Settings saved successfully.');

        return $this->redirect('/admin/settings');
    }

    /**
     * Send test email to verify mail configuration.
     */
    public function testEmail(): Response
    {
        // enforce CSRF protection
        csrf()->assertValid($this->request->postParam('_token'));

        $validator = $this->validateOrFail([
            'test_recipient' => 'required|email',
        ]);

        $recipient = $validator->validated()['test_recipient'];

        // throttle to prevent abuse
        $now = time();
        $last = (int) ($this->session->get('_mail_test_last') ?? 0);
        if (($now - $last) < 30) {
            $this->flash('error', 'Please wait 30 seconds between tests.');

            return $this->redirect('/admin/settings');
        }
        $this->session->set('_mail_test_last', $now);

        $ok = $this->mail->test($recipient);

        if ($ok) {
            $this->flash('success', "Test email sent to {$recipient}.");
        } else {
            $this->flash('error', 'Test failed. Check server logs.');
        }

        return $this->redirect('/admin/settings');
    }

    /**
     * Get mail configuration for read-only display.
     *
     * We never expose SMTP credentials in the UI for security.
     */
    private function getMailConfig(): array
    {
        return [
            'driver' => $_ENV['MAIL_DRIVER'] ?? 'not set',
            'host' => $_ENV['MAIL_HOST'] ?? 'not set',
            'port' => $_ENV['MAIL_PORT'] ?? 'not set',
            'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'not set',
            'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'not set',
            'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
            'debug' => filter_var($_ENV['MAIL_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
