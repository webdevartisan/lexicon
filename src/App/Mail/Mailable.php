<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Templates\CatalogTemplateSource;
use App\Mail\Templates\HtmlFragment;
use App\Services\TemplateRendererService;
use Closure;

/**
 * Base class for composing emails.
 *
 * provide a fluent interface for building email content. Concrete
 * Mailable classes extend this and implement build() to define their
 * specific email structure.
 *
 * A Mailable supplies data, not markup: build() sets the recipient and
 * subject, then hands its facts to fromTemplate(). How the email looks and
 * what it says around those facts belongs to its template, which admins can
 * edit under Email Templates in the control panel.
 */
abstract class Mailable
{
    /** Hands out the renderer in use; set at bootstrap so Mailables need no constructor injection. */
    private static ?Closure $templateResolver = null;

    /** A renderer installed for the duration of withTemplateRenderer(). */
    private static ?TemplateRendererService $scopedRenderer = null;

    /** Built-in templates only, for code running without the app container (unit tests, scripts). */
    private static ?TemplateRendererService $builtInRenderer = null;

    /** @var array<string, mixed> What this email gave its template */
    private array $templateData = [];

    /** Somebody is blocked waiting on it, so it goes out on its own fast worker. */
    public const TIER_CRITICAL = 'critical';

    /** Prompted by something a person did, but nobody is watching the clock. */
    public const TIER_STANDARD = 'standard';

    /** Fan-out to a list, where throughput matters and a delay costs nothing. */
    public const TIER_BULK = 'bulk';

    /**
     * How urgently this email needs to leave.
     *
     * Deliberately a property of the class rather than something an operator
     * sets. Urgency follows from what the email is: a password reset is
     * time critical because of what it does, not because of a preference, and
     * a setting for it would mostly be a way to break the one email nobody can
     * afford to lose. Operators control pace instead, by scheduling each tier
     * worker at whatever rate their provider tolerates.
     */
    protected string $tier = self::TIER_STANDARD;

    /** @var array<string, string> Address => name */
    protected array $to = [];

    /** @var array<string, string> Address => name */
    protected array $cc = [];

    /** @var array<string, string> Address => name */
    protected array $bcc = [];

    /** @var array{address: string, name: string}|null */
    protected ?array $replyTo = null;

    protected string $subject = '';

    protected string $body = '';

    protected ?string $textBody = null;

    protected bool $isHtml = true;

    /** @var array<int, array{path: string, name: string|null}> */
    protected array $attachments = [];

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

    /**
     * Render the body from this email's template.
     *
     * Call it last in build(), after subject(): the subject set in code is the
     * default the template may override, and is available to it as
     * {{ subject }}. Each key of $data becomes a placeholder the email's
     * wording can use. Strings are escaped; pass an HtmlFragment for anything
     * that is already markup.
     *
     * @param  array<string, mixed>  $data  Placeholder name => value
     * @return $this
     */
    protected function fromTemplate(array $data): static
    {
        $this->templateData = $data;
        $email = self::templates()->renderEmail(static::class, $data, $this->subject);

        $this->subject = $email->subject;

        return $this->html($email->html)->textAlternative($email->text);
    }

    /**
     * Render one block on its own, for content the email repeats or assembles,
     * such as one section per blog. The result can be passed to fromTemplate().
     *
     * @param  array<string, mixed>  $data  Placeholder name => value
     */
    protected function component(string $slug, array $data): HtmlFragment
    {
        return self::templates()->renderComponent($slug, $data);
    }

    /**
     * Use templates from wherever the resolver says, normally the database
     * with the built-in templates underneath.
     *
     * @param  (Closure(): TemplateRendererService)|null  $resolver  null goes back to built-in templates only
     */
    public static function resolveTemplatesUsing(?Closure $resolver): void
    {
        self::$templateResolver = $resolver;
    }

    /**
     * Build Mailables against a particular renderer, e.g. an unsaved draft
     * while the control panel checks or previews it.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withTemplateRenderer(TemplateRendererService $renderer, callable $callback): mixed
    {
        $previous = self::$scopedRenderer;
        self::$scopedRenderer = $renderer;

        try {
            return $callback();
        } finally {
            self::$scopedRenderer = $previous;
        }
    }

    private static function templates(): TemplateRendererService
    {
        if (self::$scopedRenderer !== null) {
            return self::$scopedRenderer;
        }

        if (self::$templateResolver !== null) {
            return (self::$templateResolver)();
        }

        return self::$builtInRenderer ??= new TemplateRendererService(new CatalogTemplateSource());
    }

    /**
     * The data this email gave its template, for the control panel to list
     * what the email's wording can refer to.
     *
     * @return array<string, mixed>
     */
    public function getTemplateData(): array
    {
        return $this->templateData;
    }

    // Getters for MailService to access protected properties

    /**
     * Delivery tier, used to pick which queue worker handles it.
     */
    public function getTier(): string
    {
        return $this->tier;
    }

    /**
     * @return array<string, string> Address => name
     */
    public function getTo(): array
    {
        return $this->to;
    }

    /**
     * @return array<string, string> Address => name
     */
    public function getCc(): array
    {
        return $this->cc;
    }

    /**
     * @return array<string, string> Address => name
     */
    public function getBcc(): array
    {
        return $this->bcc;
    }

    /**
     * @return array{address: string, name: string}|null
     */
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

    /**
     * @return array<int, array{path: string, name: string|null}>
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }
}
