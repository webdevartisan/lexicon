<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Templates\HtmlFragment;
use App\Mail\Templates\ShippedEmailSource;
use App\Services\EmailRenderer;
use App\Services\LocaleRegistry;
use Closure;

/**
 * Base class for composing emails.
 *
 * provide a fluent interface for building email content. Concrete
 * Mailable classes extend this and implement build() to define their
 * specific email structure.
 *
 * A Mailable supplies data, never words or markup: build() sets the
 * recipient and hands its facts to fromTemplate(). Everything the reader sees,
 * the subject included, is the email's content: shipped in English as
 * resources/mail/emails/{ClassName}.html and editable, and translatable, under
 * Email Templates in the control panel. Each key of the data is a placeholder
 * that content can use. Where an email needs different words for a different
 * situation, it is a different class, so each can be worded on its own.
 *
 * An email is written in its recipient's language, fixed when it is built. The
 * code that knows the recipient builds it inside inLocale(), usually with the
 * language RecipientLocale picked; outside one, the site's default is used. If
 * the email has no words in that language yet, the site default is used, then
 * English.
 */
abstract class Mailable
{
    /** Hands out the renderer in use; set at bootstrap so Mailables need no constructor injection. */
    private static ?Closure $templateResolver = null;

    /** A renderer installed for the duration of withTemplateRenderer(). */
    private static ?EmailRenderer $scopedRenderer = null;

    /** Shipped emails only, for code running without the app container (unit tests, scripts). */
    private static ?EmailRenderer $builtInRenderer = null;

    /** The language set for the duration of inLocale(). */
    private static ?string $scopedLocale = null;

    /** The language this email is written in, fixed when it is constructed. */
    private string $locale;

    /** @var array<string, mixed> What this email gave its template */
    private array $templateData = [];

    /** @var array<string, mixed> What one repetition of its repeated section was given, for listing */
    private array $repeatData = [];

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
        $this->locale = self::templates()->localeFor(static::class, self::$scopedLocale ?? LocaleRegistry::instance()->default());
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
     * Render the email from its content in this email's language: the
     * subject, the HTML and the plain text. Call it last in build().
     *
     * Each key of $data becomes a placeholder the content can use. Strings are
     * escaped; pass an HtmlFragment for anything that is already markup.
     *
     * @param  array<string, mixed>  $data  Placeholder => value
     * @return $this
     */
    protected function fromTemplate(array $data): static
    {
        $this->templateData = $data;
        $email = self::templates()->renderEmail(static::class, $data, $this->locale);

        return $this->subject($email->subject)->html($email->html)->textAlternative($email->text);
    }

    /**
     * The email's repeated section, once per row, for content the email
     * repeats such as one section per blog. Pass the result to fromTemplate().
     *
     * @param  list<array<string, mixed>>  $rows  Placeholder => value, per repetition
     */
    protected function repeat(array $rows): HtmlFragment
    {
        $this->repeatData = $rows[0] ?? [];

        return self::templates()->renderRepeat(static::class, $rows, $this->locale);
    }

    /**
     * An absolute address on this site, for links in the email.
     */
    protected function url(string $path): string
    {
        return rtrim((string) env('APP_URL', 'http://localhost'), '/').$path;
    }

    /**
     * A number as this email's language writes it (1,240 or 1.240).
     */
    protected function number(int|float $value): string
    {
        return (string) (new \NumberFormatter($this->locale, \NumberFormatter::DECIMAL))->format($value);
    }

    /**
     * A date as this email's language writes it, from an ICU skeleton such as
     * 'yMMMd' (day, short month and year) or 'yMMMdjm' (with the time). The
     * skeleton says what to show; the language decides the order and the words.
     */
    protected function date(\DateTimeInterface $date, string $skeleton): string
    {
        $pattern = (new \IntlDatePatternGenerator($this->locale))->getBestPattern($skeleton);
        $formatter = new \IntlDateFormatter($this->locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $date->getTimezone(), null, (string) $pattern);

        return (string) $formatter->format($date);
    }

    /**
     * Render emails with whatever the resolver hands out, normally what was
     * saved in the control panel with the shipped files underneath.
     *
     * @param  (Closure(): EmailRenderer)|null  $resolver  null goes back to the shipped emails only
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
    public static function withTemplateRenderer(EmailRenderer $renderer, callable $callback): mixed
    {
        $previous = self::$scopedRenderer;
        self::$scopedRenderer = $renderer;

        try {
            return $callback();
        } finally {
            self::$scopedRenderer = $previous;
        }
    }

    /**
     * Build Mailables in a given language, normally the recipient's:
     *
     *     $mail = Mailable::inLocale($recipientLocale->forUser($id), fn () => new PostApprovedMail(...));
     *
     * A language the site does not offer falls back to the site default, and
     * a language the email has no words in yet falls back as localeFor() says.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function inLocale(string $locale, callable $callback): mixed
    {
        $registry = LocaleRegistry::instance();
        $previous = self::$scopedLocale;
        self::$scopedLocale = $registry->normalize($locale) ?? $registry->default();

        try {
            return $callback();
        } finally {
            self::$scopedLocale = $previous;
        }
    }

    private static function templates(): EmailRenderer
    {
        if (self::$scopedRenderer !== null) {
            return self::$scopedRenderer;
        }

        if (self::$templateResolver !== null) {
            return (self::$templateResolver)();
        }

        return self::$builtInRenderer ??= new EmailRenderer(new ShippedEmailSource());
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

    /**
     * What one repetition of the email's repeated section was given, for the
     * control panel to list what that section can refer to. Empty for emails
     * that repeat nothing.
     *
     * @return array<string, mixed>
     */
    public function getRepeatData(): array
    {
        return $this->repeatData;
    }

    // Getters for MailService to access protected properties

    /**
     * The language this email is written in: the one asked for with
     * inLocale() when the email has words in it, else as localeFor() says.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

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
