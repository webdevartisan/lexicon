<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Services\EmailTemplateRegistry;
use App\Services\MailService;
use Exception;
use Framework\Core\Response;

/**
 * Email Testing Controller
 *
 * provide admin tools for previewing and testing email templates
 * without affecting production email flow. This is essential for
 * verifying template rendering and delivery before deployment.
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
        private EmailTemplateRegistry $registry,
    ) {}

    /**
     * Display email testing dashboard.
     *
     * list all available email templates with preview and
     * test options, providing a central hub for email development.
     */
    public function index(): Response
    {
        return $this->view([
            'groupedTemplates' => $this->registry->getGrouped(),
            'unregistered' => $this->registry->unregisteredClasses(),
            'mailConfig' => $this->getMailConfigSummary(),
            'pageTitle' => 'Email Templates',
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
     * Preview email template in browser.
     *
     * render the email HTML without sending, allowing developers
     * to see exactly how the template looks with sample data.
     */
    public function preview(): Response
    {
        $templateKey = (string) $this->request->getParam('template', '');

        if (!$templateKey) {
            $this->flash('error', 'No template specified');

            return $this->redirect('/admin/email-test');
        }

        try {
            // instantiate the template with sample data
            $mailable = $this->registry->instantiate($templateKey);

            // preview the email without sending
            $preview = $this->mailService->preview($mailable);

            // get template metadata for display
            $template = $this->registry->get($templateKey);

            return $this->view([
                'template' => $template,
                'preview' => $preview,
                'templateKey' => $templateKey,
                'pageTitle' => 'Preview: '.$template['name'],
            ]);

        } catch (Exception $e) {
            error_log('Email preview failed: '.$e->getMessage());
            $this->flash('error', 'Failed to preview email: '.$e->getMessage());

            return $this->redirect('/admin/email-test');
        }
    }

    /**
     * Render email HTML in iframe.
     *
     * output raw HTML for iframe rendering, isolating email
     * styles from the admin panel to prevent CSS conflicts.
     */
    public function renderHtml(): Response
    {
        $templateKey = (string) $this->request->getParam('template', '');

        if (!$templateKey) {
            return $this->response->html('<p>Template not specified</p>');
        }

        try {
            // instantiate and preview the template
            $mailable = $this->registry->instantiate($templateKey);
            $preview = $this->mailService->preview($mailable);

            // output raw HTML without any layout
            return $this->response->html($preview['body']);

        } catch (Exception $e) {
            error_log('Email HTML render failed: '.$e->getMessage());

            return $this->response->html(
                '<p style="color:red;">Error: '.e($e->getMessage()).'</p>'
            );
        }
    }

    /**
     * Send test email to specified recipient.
     *
     * validate CSRF and recipient email, then send a test version
     * of the template prefixed with [TEST] for easy identification.
     */
    public function sendTest(): Response
    {
        // enforce CSRF protection for all state-changing operations
        csrf()->assertValid($this->request->postParam('_token'));

        $templateKey = (string) $this->request->postParam('template', '');
        $recipient = (string) $this->request->postParam('recipient', '');

        // validate inputs before attempting to send
        if (!$templateKey || !$recipient) {
            $this->flash('error', 'Template and recipient are required');

            return $this->redirect('/admin/email-test');
        }

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'Invalid email address');

            return $this->redirect('/admin/email-test/preview?template='.urlencode($templateKey));
        }

        try {
            // instantiate the template and send to test recipient
            $mailable = $this->registry->instantiate($templateKey);
            $sent = $this->mailService->sendTest($mailable, $recipient);

            if ($sent) {
                $this->flash('success', "Test email sent successfully to {$recipient}");
            } else {
                $this->flash('error', 'Failed to send test email');
            }

        } catch (Exception $e) {
            error_log('Test email send failed: '.$e->getMessage());
            $this->flash('error', 'Error: '.$e->getMessage());
        }

        return $this->redirect('/admin/email-test/preview?template='.urlencode($templateKey));
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
