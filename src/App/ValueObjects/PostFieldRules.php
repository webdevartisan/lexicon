<?php

declare(strict_types=1);

namespace App\ValueObjects;

/**
 * Validation rules for post fields that more than one entry point enforces.
 *
 * The editor and the archive importer both accept these fields, and an import
 * must never store something the editor would have refused.
 */
final class PostFieldRules
{
    /**
     * The optional SEO and social sharing fields.
     */
    public const SEO = [
        'focus_keyword' => 'max:100',
        'meta_title' => 'max:70',
        'meta_description' => 'max:200',
        'canonical_url' => 'url|max:255',
        'meta_noindex' => 'boolean',
        'meta_nofollow' => 'boolean',
        'og_title' => 'max:70',
        'og_description' => 'max:100',
        'og_image' => 'url|max:255',
        'og_image_alt' => 'max:255',
        'twitter_card_type' => 'in:summary,summary_large_image',
        'twitter_title' => 'max:70',
        'twitter_description' => 'max:200',
        'twitter_image' => 'url|max:255',
    ];

    /**
     * Title, slug, body and the other core fields of a new post. The body may
     * be empty here; missingText() decides that once the status is known.
     */
    public const CORE = [
        'title' => 'required|title|min:2|max:100',
        'slug' => 'required|slug|min:2|max:100',
        'content' => 'max:60000',
        'excerpt' => 'max:300',
        'timezone' => 'timezone',
        'comments_enabled' => 'boolean',
    ];

    /** Longest slug the editor accepts, also the cap for numbered copies. */
    public const SLUG_MAX = 100;

    public const TEXT_REQUIRED = 'Write the post before publishing it or sending it for review. A draft can be saved with only a title.';

    public const TEXT_CHANGED_ELSEWHERE = 'Someone saved this post after you opened it. Copy your changes, then reload to see theirs.';

    /**
     * A draft may wait for its text. Any other status puts the post in front of
     * readers or a reviewer, so it needs some.
     *
     * @param  string  $status  The status the post is about to have
     * @param  string|null  $content  Its body
     */
    public static function missingText(string $status, ?string $content): bool
    {
        return $status !== 'draft' && trim((string) $content) === '';
    }
}
