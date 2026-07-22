<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\Mailable;
use Exception;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Mail service for sending transactional emails.
 *
 * wrap PHPMailer to provide a clean, testable interface that integrates
 * with our framework's patterns. This service handles SMTP configuration,
 * email queueing (optional), and provides a fluent API for composing emails.
 *
 * @see https://github.com/PHPMailer/PHPMailer
 */
class MailService
{
    private string $driver;

    /** @var array<string, mixed> */
    private array $config;

    /**
     * inject mail configuration from environment variables.
     *
     * Configuration is validated at construction time to fail fast if
     * mail settings are misconfigured.
     *
     * @param  array<string, mixed>  $config  Mail configuration array
     *
     * @throws Exception If required configuration is missing
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->driver = $config['driver'] ?? 'smtp';

        // validate required configuration to prevent runtime errors
        $this->validateConfig();
    }

    /**
     * Send an email using a Mailable class.
     *
     * accept Mailable objects that encapsulate email content and
     * configuration, promoting reusable and testable email templates.
     *
     * Never throws. Mail is a side effect of whatever the user was actually
     * doing — publishing a post, accepting an invite, registering — and an
     * unreachable SMTP server must not take that action down with it. Callers
     * check the return value and decide how much they care. Use sendOrFail()
     * when the reason for a failure matters.
     *
     * Returns true when mail is disabled: suppression is a deliberate
     * configuration choice, not a delivery failure, and treating it as one
     * would make every dev environment report errors to the user.
     *
     * @param  Mailable  $mailable  Email to send
     * @return bool True on success or when mail is disabled, false on failure
     */
    public function send(Mailable $mailable): bool
    {
        if (!$this->enabled()) {
            error_log('Mail suppressed (MAIL_ENABLED=false): '.$mailable->getSubject());

            return true;
        }

        try {
            $this->deliver($mailable);

            return true;
        } catch (Exception $e) {
            error_log('Mail sending failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Send an email, throwing when it cannot be delivered.
     *
     * For the admin mail diagnostics, where "it failed" is useless without the
     * transport's own message.
     *
     * Unlike send(), this treats a disabled mailer as an error: an admin
     * testing delivery needs to be told the master switch is off, not handed a
     * success for an email that never left.
     *
     * @param  Mailable  $mailable  Email to send
     * @return bool Always true; failure arrives as an exception
     *
     * @throws Exception If mail is disabled, or sending fails
     */
    public function sendOrFail(Mailable $mailable): bool
    {
        if (!$this->enabled()) {
            throw new Exception('Mail is disabled. Set MAIL_ENABLED=true in .env to send.');
        }

        $this->deliver($mailable);

        return true;
    }

    /**
     * Whether outgoing mail is switched on.
     *
     * Defaults to false so a missing key can never start mailing real people
     * from an environment that never meant to.
     */
    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    /**
     * Build and hand one Mailable to PHPMailer.
     *
     * The single delivery path behind both send() and sendOrFail(), so the two
     * can never drift apart in how they construct or transmit a message.
     *
     * @param  Mailable  $mailable  Email to send
     *
     * @throws Exception If PHPMailer rejects or cannot transmit the message
     */
    private function deliver(Mailable $mailable): void
    {
        try {
            $mail = $this->createMailer();

            // build the email from the Mailable configuration
            $this->buildEmail($mail, $mailable);

            if (!$mail->send()) {
                // A refusal with no exception; ErrorInfo holds the reason.
                throw new Exception('Failed to send email: '.$mail->ErrorInfo);
            }
        } catch (PHPMailerException $e) {
            throw new Exception('Failed to send email: '.$e->getMessage());
        }
    }

    /**
     * Create and configure a PHPMailer instance.
     *
     * centralize PHPMailer setup to ensure consistent configuration
     * across all emails and make testing easier.
     *
     * @return PHPMailer Configured mailer instance
     *
     * @throws PHPMailerException If PHPMailer configuration fails
     */
    private function createMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);

        // configure based on the selected driver
        switch ($this->driver) {
            case 'smtp':
                $this->configureSMTP($mail);
                break;

            case 'sendmail':
                $mail->isSendmail();
                break;

            case 'mail':
                $mail->isMail();
                break;

            default:
                throw new Exception("Unsupported mail driver: {$this->driver}");
        }

        // set common defaults for all emails
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom(
            $this->config['from']['address'],
            $this->config['from']['name']
        );

        return $mail;
    }

    /**
     * Configure PHPMailer for SMTP delivery.
     *
     * set up SMTP authentication and encryption based on environment
     * configuration. Debug mode can be enabled for troubleshooting.
     *
     * @param  PHPMailer  $mail  Mailer instance to configure
     */
    private function configureSMTP(PHPMailer $mail): void
    {
        $mail->isSMTP();
        $mail->Host = $this->config['smtp']['host'];
        $mail->Port = (int) $this->config['smtp']['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $this->config['smtp']['username'];
        $mail->Password = $this->config['smtp']['password'];

        // set encryption method (tls or ssl)
        $encryption = $this->config['smtp']['encryption'] ?? 'tls';
        if ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        // enable debug output only in development
        if (!empty($this->config['debug'])) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = 'error_log';
        }
    }

    /**
     * Build email content from a Mailable instance.
     *
     * extract all email components from the Mailable and apply them
     * to the PHPMailer instance. This separation allows Mailables to focus
     * on content while this service handles delivery mechanics.
     *
     * @param  PHPMailer  $mail  Mailer instance to configure
     * @param  Mailable  $mailable  Email content and configuration
     *
     * @throws PHPMailerException If email building fails
     */
    private function buildEmail(PHPMailer $mail, Mailable $mailable): void
    {
        // set recipients
        foreach ($mailable->getTo() as $address => $name) {
            $mail->addAddress($address, $name);
        }

        // set CC recipients if any
        foreach ($mailable->getCc() as $address => $name) {
            $mail->addCC($address, $name);
        }

        // set BCC recipients if any
        foreach ($mailable->getBcc() as $address => $name) {
            $mail->addBCC($address, $name);
        }

        // set reply-to if specified
        $replyTo = $mailable->getReplyTo();
        if ($replyTo) {
            $mail->addReplyTo($replyTo['address'], $replyTo['name']);
        }

        // set email subject and body
        $mail->Subject = $mailable->getSubject();

        // support both HTML and plain text emails
        if ($mailable->isHtml()) {
            $mail->isHTML(true);
            $mail->Body = $mailable->getBody();

            // set plain text alternative if provided
            $altBody = $mailable->getTextBody();
            if ($altBody) {
                $mail->AltBody = $altBody;
            }
        } else {
            $mail->isHTML(false);
            $mail->Body = $mailable->getBody();
        }

        // attach files if any
        foreach ($mailable->getAttachments() as $attachment) {
            $mail->addAttachment(
                $attachment['path'],
                $attachment['name'] ?? ''
            );
        }
    }

    /**
     * Validate mail configuration at service initialization.
     *
     * check for required configuration keys to fail fast during
     * bootstrap rather than at runtime when sending emails.
     *
     * @throws Exception If configuration is invalid
     */
    private function validateConfig(): void
    {
        // Nothing will be transmitted, so incomplete credentials are not a
        // problem worth refusing to boot over. Lets an environment run with
        // MAIL_ENABLED=false and no SMTP secrets at all.
        if (!$this->enabled()) {
            return;
        }

        // require 'from' configuration for all emails
        if (empty($this->config['from']['address'])) {
            throw new Exception("Mail configuration missing 'from.address'");
        }

        // validate SMTP-specific configuration
        if ($this->driver === 'smtp') {
            $required = ['host', 'port', 'username', 'password'];
            foreach ($required as $key) {
                if (empty($this->config['smtp'][$key])) {
                    throw new Exception("SMTP configuration missing '{$key}'");
                }
            }
        }
    }

    /**
     * Test mail configuration by sending a test email.
     *
     * provide a diagnostic method to verify mail settings without
     * affecting production email flow.
     *
     * @param  string  $recipient  Test recipient email address
     * @return bool True if test email sent successfully
     *
     * @throws Exception If sending fails, so the admin sees the real reason
     */
    public function test(string $recipient): bool
    {

        try {
            $testMail = new class($recipient) extends Mailable
            {
                private string $recipient;

                public function __construct(string $recipient)
                {
                    $this->recipient = $recipient;
                    // must call parent constructor to trigger build()
                    parent::__construct();
                }

                public function build(): void
                {
                    $this->to($this->recipient)
                        ->subject('Test Email from '.(env('APP_NAME', 'Blog Platform')))
                        ->html('<p>This is a test email. If you received this, your mail configuration is working correctly.</p>');
                }
            };

            // sendOrFail, not send: an admin testing mail configuration needs
            // the transport's actual complaint, not a bare false.
            return $this->sendOrFail($testMail);

        } catch (Exception $e) {
            error_log('Mail test failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Render email HTML without sending.
     *
     * extract the built email content for preview purposes,
     * allowing developers to see how templates look without
     * actually sending emails.
     *
     * @param  Mailable  $mailable  Email template to render
     * @return array<string, mixed> Email details (subject, body, recipients, etc.)
     *
     * @throws Exception If email building fails
     */
    public function preview(Mailable $mailable): array
    {
        try {
            // return all email properties for preview display
            return [
                'to' => $mailable->getTo(),
                'cc' => $mailable->getCc(),
                'bcc' => $mailable->getBcc(),
                'reply_to' => $mailable->getReplyTo(),
                'subject' => $mailable->getSubject(),
                'body' => $mailable->getBody(),
                'text_body' => $mailable->getTextBody(),
                'is_html' => $mailable->isHtml(),
                'attachments' => $mailable->getAttachments(),
                'from' => [
                    'address' => $this->config['from']['address'],
                    'name' => $this->config['from']['name'],
                ],
            ];
        } catch (Exception $e) {
            error_log('Email preview failed: '.$e->getMessage());
            throw new Exception('Failed to preview email: '.$e->getMessage());
        }
    }

    /**
     * Send test email to specific recipient.
     *
     * wrap any Mailable and override its recipient for testing
     * purposes, allowing admins to verify email delivery and rendering
     * in real email clients.
     *
     * @param  Mailable  $mailable  Email template to send
     * @param  string  $testRecipient  Override recipient address
     * @return bool True if sent successfully
     *
     * @throws Exception If sending fails
     */
    public function sendTest(Mailable $mailable, string $testRecipient): bool
    {
        // validate the test recipient email before attempting to send
        if (!filter_var($testRecipient, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid test recipient email address');
        }

        try {
            // capture the original email data
            $subject = $mailable->getSubject();
            $body = $mailable->getBody();
            $isHtml = $mailable->isHtml();

            // create a new test mailable with overridden recipient
            $testMail = new class($testRecipient, $subject, $body, $isHtml) extends Mailable
            {
                private string $recipient;

                private string $emailSubject;

                private string $emailBody;

                private bool $htmlMode;

                public function __construct(string $recipient, string $subject, string $body, bool $isHtml)
                {
                    $this->recipient = $recipient;
                    $this->emailSubject = $subject;
                    $this->emailBody = $body;
                    $this->htmlMode = $isHtml;
                    parent::__construct();
                }

                public function build(): void
                {
                    $this->to($this->recipient)
                        ->subject('[TEST] '.$this->emailSubject);

                    // prefix the body to indicate this is a test
                    $testNotice = '<div style="background:#fff3cd;border:1px solid #ffc107;padding:12px;margin-bottom:20px;">'.
                                '<strong>⚠️ TEST EMAIL</strong> - This is a test of the email template. '.
                                'In production, this would be sent to the actual recipient.'.
                                '</div>';

                    if ($this->htmlMode) {
                        $this->html($testNotice.$this->emailBody);
                    } else {
                        $this->text("=== TEST EMAIL ===\n\n".$this->emailBody);
                    }
                }
            };

            // sendOrFail, not send: an admin testing mail configuration needs
            // the transport's actual complaint, not a bare false.
            return $this->sendOrFail($testMail);

        } catch (Exception $e) {
            error_log('Test email failed: '.$e->getMessage());
            throw $e;
        }
    }
}
