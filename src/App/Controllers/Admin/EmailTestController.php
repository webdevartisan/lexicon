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
        // fetch all registered email templates
        $templates = $this->registry->getAll();

        return $this->view([
            'templates' => $templates,
            'pageTitle' => 'Email Template Testing',
        ]);
    }

    /**
     * Preview email template in browser.
     *
     * render the email HTML without sending, allowing developers
     * to see exactly how the template looks with sample data.
     */
    public function preview(): Response
    {
        $templateKey = $this->request->get['template'] ?? '';

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
        $templateKey = $this->request->get['template'] ?? '';

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
                '<p style="color:red;">Error: '.htmlspecialchars($e->getMessage()).'</p>'
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
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $templateKey = $this->request->post['template'] ?? '';
        $recipient = $this->request->post['recipient'] ?? '';

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
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $recipient = $this->request->post['recipient'] ?? '';

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
