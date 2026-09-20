<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Reduces post HTML to the markup a writer is allowed to publish.
 *
 * Post bodies are printed unescaped by every theme, because that is what makes a
 * post look like a post. The editor limits what its toolbar can produce, but that
 * happens in the browser, so a request sent without it could store a script or an
 * event handler that then runs for every reader. This is the server's own limit,
 * applied to whatever arrives, wherever it arrives from.
 */
final class PostContentSanitizer
{
    /**
     * Attributes the editor and the seeded themes rely on that are not part of the
     * W3C safe set, either because they are ours or because they carry styling.
     */
    private const EXTRA_ATTRIBUTES = [
        'class',
        'style',
        'role',
        'aria-label',
        'aria-labelledby',
        'aria-describedby',
        'aria-hidden',
        'aria-live',
        'data-label',
        'data-block-type',
        'data-block-size',
        'data-block-display',
        'data-block-background',
        'data-section-type',
    ];

    /** Comfortably past the longest body the database column and validation accept. */
    private const MAX_LENGTH = 1_000_000;

    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias()
            ->withMaxInputLength(self::MAX_LENGTH)
            ->withAttributeSanitizer(new InlineStyleSanitizer());

        foreach (self::EXTRA_ATTRIBUTES as $attribute) {
            $config = $config->allowAttribute($attribute, '*');
        }

        $this->sanitizer = new HtmlSanitizer($config);
    }

    /**
     * The publishable version of a post body.
     *
     * @param  string  $html  Content as submitted
     * @return string The same content with anything outside the allowlist removed
     *
     * @throws RuntimeException When the content is longer than the sanitizer can read in one piece
     */
    public function clean(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // The sanitizer truncates past its limit, and a body that came back shortened
        // without anyone saying so would look like the writer lost the end of their post.
        if (strlen($html) > self::MAX_LENGTH) {
            throw new RuntimeException('The post content is too long to be checked for unsafe markup.');
        }

        return $this->sanitizer->sanitize($html);
    }
}
