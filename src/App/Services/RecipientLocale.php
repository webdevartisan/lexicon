<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Database;

/**
 * Picks the language an email is written in, for whoever receives it.
 *
 * Every place that sends an email asks here rather than guessing, because the
 * person who triggers an email is often not the one who reads it: a reviewer's
 * approval goes to the author, an admin's password reset to the account owner.
 * So the language of the page being viewed only counts when the reader is the
 * person viewing it, and callers say so by passing it as $readingNow.
 *
 * In order, the first that applies:
 *
 *   1. the language the person chose in their preferences;
 *   2. the page they are reading right now, when they are the one acting;
 *   3. what is known about the address: the page a subscription was made
 *      from, or the page the account last signed in from;
 *   4. the blog's language, for mail about a blog;
 *   5. the site default.
 *
 * Only languages the site still offers are returned, so a code left behind by
 * a removed locale falls through to the next rule.
 */
class RecipientLocale
{
    public function __construct(
        private Database $database,
        private LocaleRegistry $registry,
    ) {}

    /**
     * A signed-up user.
     *
     * @param  string|null  $readingNow  The current page's language, only when the user is the one acting
     */
    public function forUser(int $userId, ?string $readingNow = null): string
    {
        $row = $this->database->query(
            'SELECT p.locale AS preferred, u.last_locale
               FROM users u
               LEFT JOIN user_preferences p ON p.user_id = u.id
              WHERE u.id = ?',
            [$userId]
        )->fetch(\PDO::FETCH_ASSOC) ?: [];

        return $this->first($row['preferred'] ?? null, $readingNow, $row['last_locale'] ?? null);
    }

    /**
     * An address that may or may not belong to an account, such as an
     * invitation. An account's language wins; otherwise $fallback, usually the
     * blog the mail is about.
     */
    public function forAddress(string $email, ?string $fallback = null): string
    {
        $id = $this->database->query(
            'SELECT id FROM users WHERE email = ? AND deleted_at IS NULL',
            [$email]
        )->fetchColumn();

        return $id !== false ? $this->forUser((int) $id) : $this->first($fallback);
    }

    /**
     * A blog subscriber, from a row of BlogSubscriberModel::forBlog(), which
     * carries the account's preference and last language alongside the
     * subscription's own, so a fan-out needs no query per subscriber.
     *
     * The subscription's language comes before the account's last sign-in,
     * since it was chosen while reading this blog.
     *
     * @param  array<string, mixed>  $subscriber  With preferred_locale, locale and last_locale
     */
    public function forSubscriber(array $subscriber, ?string $blogLocale = null): string
    {
        return $this->first(
            $subscriber['preferred_locale'] ?? null,
            $subscriber['locale'] ?? null,
            $subscriber['last_locale'] ?? null,
            $blogLocale
        );
    }

    /**
     * A blog's own language, from its settings.
     */
    public function forBlog(int $blogId): string
    {
        $locale = $this->database->query(
            'SELECT default_locale FROM blog_settings WHERE blog_id = ?',
            [$blogId]
        )->fetchColumn();

        return $this->first($locale === false ? null : (string) $locale);
    }

    /**
     * The language of the page being served, for mail read by the person
     * viewing it. On the console this is the site default.
     */
    public function current(): string
    {
        return $this->first(LocaleState::get()->chromeLocale);
    }

    public function siteDefault(): string
    {
        return $this->registry->default();
    }

    /**
     * The first candidate the site still offers, or the site default.
     */
    private function first(?string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $locale = $this->registry->normalize($candidate);

            if ($locale !== null) {
                return $locale;
            }
        }

        return $this->registry->default();
    }
}
