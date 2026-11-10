<?php

declare(strict_types=1);

namespace App\Presenters;

/**
 * Small decisions the post editor's layout needs: which dialog holds which
 * field, which dialog to open after a rejected save, and which tags to offer.
 */
final class PostEditorPresenter
{
    /** Fields shown on the page itself; their errors appear right under them. */
    private const PAGE_FIELDS = ['title', 'content'];

    private const PUBLISH_FIELDS = ['published_at', 'timezone', 'visibility'];

    private const QUICK_TAG_LIMIT = 8;

    /**
     * The editor's dialogs and the fields each one holds.
     *
     * The publish date and visibility sit in the publish check when the main
     * button publishes, and with the other options when it does not.
     *
     * @param  bool  $hasPublishCheck  Whether the main button opens the publish check
     * @return array<string, list<string>> Field names keyed by dialog id
     */
    public static function dialogs(bool $hasPublishCheck): array
    {
        $options = ['author_id', 'comments_enabled', 'is_featured'];

        return [
            'postLinkDialog' => ['slug'],
            'postCoverDialog' => ['featured_image', 'featured_image_library_url'],
            'postSummaryDialog' => ['excerpt'],
            'postOrganizeDialog' => ['category_id', 'tags'],
            'postPublishDialog' => $hasPublishCheck ? self::PUBLISH_FIELDS : [],
            'postOptionsDialog' => $hasPublishCheck ? $options : [...$options, ...self::PUBLISH_FIELDS],
            'postSearchDialog' => ['focus_keyword', 'meta_title', 'meta_description', 'canonical_url'],
            'postSocialDialog' => [
                'og_title', 'og_description', 'og_image', 'og_image_alt',
                'twitter_card_type', 'twitter_title', 'twitter_description', 'twitter_image',
            ],
        ];
    }

    /**
     * The first dialog holding a field the server rejected, so it can open on load.
     *
     * @param  array<string, mixed>  $errors  Validation messages keyed by field
     * @param  bool  $hasPublishCheck  Whether the main button opens the publish check
     * @return string|null Dialog id, or null when no dialog field failed
     */
    public static function dialogWithError(array $errors, bool $hasPublishCheck): ?string
    {
        foreach (self::dialogs($hasPublishCheck) as $dialog => $fields) {
            if (array_intersect($fields, array_keys($errors)) !== []) {
                return $dialog;
            }
        }

        return null;
    }

    /**
     * Messages for fields the page has no place for, shown as a list at the top.
     *
     * @param  array<string, mixed>  $errors  Validation messages keyed by field
     * @param  bool  $hasPublishCheck  Whether the main button opens the publish check
     * @return list<string>
     */
    public static function unplacedErrors(array $errors, bool $hasPublishCheck): array
    {
        $placed = array_merge(self::PAGE_FIELDS, ...array_values(self::dialogs($hasPublishCheck)));
        $messages = [];

        foreach ($errors as $field => $fieldMessages) {
            if (in_array($field, $placed, true)) {
                continue;
            }
            foreach ((array) $fieldMessages as $message) {
                $messages[] = (string) $message;
            }
        }

        return $messages;
    }

    /**
     * The blog's most used tags, for one-tap adding.
     *
     * @param  list<array<string, mixed>>  $tags  Tag rows carrying name and post_count
     * @return list<string> Tag names, most used first
     */
    public static function quickTags(array $tags): array
    {
        $used = array_filter($tags, static function (array $tag): bool {
            return (int) ($tag['post_count'] ?? 0) > 0;
        });

        usort($used, static function (array $a, array $b): int {
            return [(int) $b['post_count'], (string) $a['name']] <=> [(int) $a['post_count'], (string) $b['name']];
        });

        return array_map(static function (array $tag): string {
            return (string) $tag['name'];
        }, array_slice($used, 0, self::QUICK_TAG_LIMIT));
    }
}
