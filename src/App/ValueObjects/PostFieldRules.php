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
     * Title, slug, body and the other core fields of a new post.
     */
    public const CORE = [
        'title' => 'required|title|min:2|max:100',
        'slug' => 'required|slug|min:2|max:100',
        'content' => 'required|max:60000',
        'excerpt' => 'max:300',
        'timezone' => 'timezone',
        'comments_enabled' => 'boolean',
    ];

    /** Longest slug the editor accepts, also the cap for numbered copies. */
    public const SLUG_MAX = 100;
}
