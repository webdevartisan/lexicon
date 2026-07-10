<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * A visitor's message from the public contact form, sent to the site admin.
 *
 * Reply-to is set to the visitor so the admin can answer directly from
 * their mail client.
 */
class ContactMessageMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private string $senderName,
        private string $senderEmail,
        private string $messageSubject,
        private string $messageBody
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->replyTo($this->senderEmail, $this->senderName)
            ->subject('[Contact] '.$this->messageSubject)
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
    }

    private function buildHtmlBody(): string
    {
        $name = htmlspecialchars($this->senderName);
        $email = htmlspecialchars($this->senderEmail);
        $subject = htmlspecialchars($this->messageSubject);
        $message = nl2br(htmlspecialchars($this->messageBody));

        return <<<HTML
        <!DOCTYPE html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                <h2>New contact message</h2>
                <p><strong>From:</strong> {$name} &lt;{$email}&gt;</p>
                <p><strong>Subject:</strong> {$subject}</p>
                <hr style="border:none;border-top:1px solid #ddd;">
                <p>{$message}</p>
            </div>
        </body></html>
        HTML;
    }

    private function buildTextBody(): string
    {
        return "New contact message\n"
            ."From: {$this->senderName} <{$this->senderEmail}>\n"
            ."Subject: {$this->messageSubject}\n\n"
            .$this->messageBody."\n";
    }
}
