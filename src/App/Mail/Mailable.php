<?php

namespace App\Mail;

/**
 * Base class for composing emails.
 *
 * provide a fluent interface for building email content. Concrete
 * Mailable classes extend this and implement build() to define their
 * specific email structure.
 *
 * This pattern (inspired by Laravel's Mailables) promotes:
 * - Reusable email templates
 * - Testable email logic
 * - Clear separation between content and delivery
 */
abstract class Mailable
{
    protected array $to = [];

    protected array $cc = [];

    protected array $bcc = [];

    protected ?array $replyTo = null;

    protected string $subject = '';

    protected string $body = '';

    protected ?string $textBody = null;

    protected bool $isHtml = true;

    protected array $attachments = [];

    protected array $data = [];

    /**
     * Build the email content.
     *
     * Concrete implementations must override this method to define
     * their email structure using the fluent methods below.
     */
    abstract public function build(): void;

    /**
     * initialize the mailable by calling build() automatically.
     */
    public function __construct()
    {
        $this->build();
    }

    /**
     * Add a recipient to the email.
     *
     * @param  string  $address  Email address
     * @param  string  $name  Recipient name (optional)
     * @return $this
     */
    protected function to(string $address, string $name = ''): static
    {
        $this->to[$address] = $name;

        return $this;
    }

    /**
     * Add a CC recipient.
     *
     * @param  string  $address  Email address
     * @param  string  $name  Recipient name (optional)
     * @return $this
     */
    protected function cc(string $address, string $name = ''): static
    {
        $this->cc[$address] = $name;

        return $this;
    }

    /**
     * Add a BCC recipient.
     *
     * @param  string  $address  Email address
     * @param  string  $name  Recipient name (optional)
     * @return $this
     */
    protected function bcc(string $address, string $name = ''): static
    {
        $this->bcc[$address] = $name;

        return $this;
    }

    /**
     * Set reply-to address.
     *
     * @param  string  $address  Email address
     * @param  string  $name  Name (optional)
     * @return $this
     */
    protected function replyTo(string $address, string $name = ''): static
    {
        $this->replyTo = ['address' => $address, 'name' => $name];

        return $this;
    }

    /**
     * Set email subject.
     *
     * @return $this
     */
    protected function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Set HTML email body.
     *
     * @param  string  $body  HTML content
     * @return $this
     */
    protected function html(string $body): static
    {
        $this->body = $body;
        $this->isHtml = true;

        return $this;
    }

    /**
     * Set plain text email body.
     *
     * @param  string  $body  Plain text content
     * @return $this
     */
    protected function text(string $body): static
    {
        $this->body = $body;
        $this->isHtml = false;

        return $this;
    }

    /**
     * Set plain text alternative for HTML emails.
     *
     * @param  string  $textBody  Plain text version
     * @return $this
     */
    protected function textAlternative(string $textBody): static
    {
        $this->textBody = $textBody;

        return $this;
    }

    /**
     * Attach a file to the email.
     *
     * @param  string  $path  Full path to file
     * @param  string|null  $name  Display name (optional)
     * @return $this
     */
    protected function attach(string $path, ?string $name = null): static
    {
        $this->attachments[] = [
            'path' => $path,
            'name' => $name,
        ];

        return $this;
    }

    // Getters for MailService to access protected properties
    public function getTo(): array
    {
        return $this->to;
    }

    public function getCc(): array
    {
        return $this->cc;
    }

    public function getBcc(): array
    {
        return $this->bcc;
    }

    public function getReplyTo(): ?array
    {
        return $this->replyTo;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getTextBody(): ?string
    {
        return $this->textBody;
    }

    public function isHtml(): bool
    {
        return $this->isHtml;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }
}
