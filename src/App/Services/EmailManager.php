<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\Mailable;
use App\Mail\Templates\EmailSource;
use App\Mail\Templates\HtmlFragment;
use App\Mail\Templates\TemplateDataException;
use Throwable;

/**
 * What the control panel knows about the emails the site sends: the list,
 * the data each one provides, and whether each one builds as saved.
 *
 * Emails are addressed by short class name, checked against the registry, so
 * nothing here ever builds an arbitrary class.
 *
 * @phpstan-type Email array{class: string, short: string, name: string, description: string, group: string, sample: string}
 */
class EmailManager
{
    public function __construct(
        private EmailContentRepository $repository,
        private EmailTemplateRegistry $registry,
    ) {}

    public function repository(): EmailContentRepository
    {
        return $this->repository;
    }

    /**
     * Every email the site sends, one entry per Mailable class, keyed by its
     * short class name (the form the control panel URLs use).
     *
     * @return array<string, Email>
     */
    public function emails(): array
    {
        $emails = [];

        foreach ($this->registry->getAll() as $key => $meta) {
            $class = (string) $meta['class'];
            $short = TemplateDataException::shortName($class);

            // The registry holds each class once (EmailTemplateRegistryTest checks it).
            $emails[$short] ??= [
                'class' => $class,
                'short' => $short,
                'name' => (string) $meta['name'],
                'description' => (string) $meta['description'],
                'group' => (string) ($meta['group'] ?? 'Other'),
                'sample' => (string) $key,
            ];
        }

        return $emails;
    }

    /**
     * @return Email|null
     */
    public function email(string $short): ?array
    {
        return $this->emails()[$short] ?? null;
    }

    /**
     * An email built from its sample data in one language.
     *
     * @param  Email  $email
     * @param  bool  $strict  false shows unfilled placeholders instead of failing, for previews
     */
    public function buildSample(array $email, string $locale, bool $strict = true, ?EmailSource $source = null): Mailable
    {
        return Mailable::withTemplateRenderer(
            new EmailRenderer($source ?? $this->repository, $strict),
            fn (): Mailable => Mailable::inLocale($locale, fn (): Mailable => $this->registry->build($email['sample']))
        );
    }

    /**
     * What an email can put in its words, as display text from its sample:
     * the email's data, then what one repetition of its repeated section gets.
     *
     * Built from the shipped files, so the list works even while a saved
     * version is broken.
     *
     * @param  Email  $email
     * @return array{data: array<string, string>, repeat: array<string, string>}
     */
    public function sampleData(array $email): array
    {
        $mailable = $this->buildSample($email, EmailRenderer::BASE_LOCALE, true, $this->repository->shipped());
        $asText = static fn (mixed $value): string => $value instanceof HtmlFragment ? $value->text : (is_scalar($value) ? (string) $value : '');

        return [
            'data' => array_map($asText, $mailable->getTemplateData()),
            'repeat' => array_map($asText, $mailable->getRepeatData()),
        ];
    }

    /**
     * What stops an email from being sent as saved, per language it has.
     * An email with a problem goes out as shipped, in English, instead.
     *
     * @param  list<string>|null  $onlyClasses  Limit the check to these Mailables
     * @return array<string, array<string, string>> Mailable class => language => problem
     */
    public function problems(?array $onlyClasses = null): array
    {
        $problems = [];

        foreach ($this->emails() as $email) {
            if ($onlyClasses !== null && !in_array($email['class'], $onlyClasses, true)) {
                continue;
            }

            foreach ($this->repository->locales($email['class']) as $locale) {
                try {
                    $this->buildSample($email, $locale);
                } catch (Throwable $e) {
                    $problems[$email['class']][$locale] = $e->getMessage();
                }
            }
        }

        return $problems;
    }

    /**
     * Mailable classes in the codebase that the registry does not list, so
     * they have no entry under Emails.
     *
     * @return string[]
     */
    public function unregisteredClasses(): array
    {
        return $this->registry->unregisteredClasses();
    }
}
