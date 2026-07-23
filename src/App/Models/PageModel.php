<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Admin-managed static pages (about, contact, legal texts, guides).
 *
 * Pages exist per locale with English as the fallback, so an untranslated
 * page still renders instead of returning a 404 for non-English visitors.
 */
class PageModel extends AppModel
{
    protected ?string $table = 'pages';

    /**
     * Locale used when a page has no row for the visitor's locale.
     */
    public const FALLBACK_LOCALE = 'en';

    /**
     * Locales this page has a published row for.
     *
     * A page translated into two languages is a genuine pair of alternates. One
     * that exists only in English is not, and must not advertise otherwise.
     *
     * @return string[]
     */
    public function localesForSlug(string $slug): array
    {
        $rows = $this->database->query(
            "SELECT DISTINCT locale FROM {$this->getTable()}
             WHERE slug = ? AND is_published = 1
             ORDER BY locale",
            [$slug]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): string => (string) $row['locale'], $rows);
    }

    /**
     * A published page for the visitor, falling back to English.
     *
     * @param  string  $slug  Page slug, e.g. 'about'
     * @param  string  $locale  Visitor locale
     * @return array<string, mixed>|null Page row, or null when unpublished/missing
     */
    public function findPublished(string $slug, string $locale): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()}
                WHERE slug = ? AND locale IN (?, ?) AND is_published = 1
                ORDER BY locale = ? DESC
                LIMIT 1";

        $row = $this->database
            ->query($sql, [$slug, $locale, self::FALLBACK_LOCALE, $locale])
            ->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Several published pages at once, keyed by slug (guide listings).
     *
     * @param  array<int, string>  $slugs  Slugs to load, also defines the result order
     * @param  string  $locale  Visitor locale
     * @return array<int, array<string, mixed>> Page rows in the requested slug order
     */
    public function findManyPublished(array $slugs, string $locale): array
    {
        $pages = [];

        foreach ($slugs as $slug) {
            $page = $this->findPublished($slug, $locale);
            if ($page !== null) {
                $pages[] = $page;
            }
        }

        return $pages;
    }

    /**
     * A page row by slug and exact locale, published or not (admin use).
     *
     * @return array<string, mixed>|null
     */
    public function findBySlugAndLocale(string $slug, string $locale): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE slug = ? AND locale = ? LIMIT 1";

        $row = $this->database->query($sql, [$slug, $locale])->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Every page row for the admin list, grouped by slug.
     *
     * @return array<string, array<int, array<string, mixed>>> slug => rows per locale
     */
    public function allGroupedBySlug(): array
    {
        $sql = "SELECT * FROM {$this->getTable()} ORDER BY slug ASC, locale ASC";
        $rows = $this->database->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['slug']][] = $row;
        }

        return $grouped;
    }
}
