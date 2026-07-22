<?php

declare(strict_types=1);

/**
 * Application-level helper functions.
 *
 * Framework-agnostic helpers live in src/Framework/Core/helpers.php; the
 * helpers here depend on application services and models.
 */

use App\Models\SettingModel;
use App\Models\SiteContentModel;
use App\Services\AssetPathMapper;
use App\Services\TranslationService;

/**
 * Editable site text with translation fallback.
 *
 * Resolution order: admin override for the current locale, then a
 * locale-independent override, then the locale file entry for the same key.
 * Site content keys deliberately mirror translation keys so the locale files
 * act as the defaults and the database only stores what admins changed.
 *
 * @param  string  $name  Content key, e.g. 'front.hero.title'
 * @param  string|null  $default  Returned instead of the translation when no override exists
 * @return string The effective text for the current locale
 */
function site_content(string $name, ?string $default = null): string
{
    // Memoized per request; the model caches all rows on first query.
    static $model = null;
    $model ??= app(SiteContentModel::class);

    $value = $model->get($name, locale());

    if ($value !== null && $value !== '') {
        return $value;
    }

    if ($default !== null) {
        return $default;
    }

    static $translator = null;
    $translator ??= new TranslationService(locale());

    return $translator->translate($name);
}

/**
 * Read a site setting from the settings table.
 *
 * @param  string  $name  Setting name, e.g. 'site_name'
 * @param  string|null  $default  Value when the setting is missing
 * @return string|null The setting value or the default
 */
function site_setting(string $name, ?string $default = null): ?string
{
    static $model = null;
    $model ??= app(SettingModel::class);

    return $model->get($name, $default);
}

/**
 * Render the Open Graph and Twitter card meta tags for a page.
 *
 * Every theme emitted its own near-identical block, which is why og:image
 * dimensions and the article:* tags were missing everywhere at once. One
 * function means adding a tag reaches all six layouts.
 *
 * Facebook, LinkedIn, WhatsApp, Slack and Discord all read the og: tags, so
 * they necessarily share content. Only X reads the twitter: namespace, so the
 * twitter tags fall back to their og: counterparts unless a post overrode them.
 *
 * @param  array<string, mixed>  $meta  Assembled meta context from the controller
 * @return string Escaped HTML meta tags
 */
function social_meta_tags(array $meta): string
{
    $ogTitle = (string) ($meta['og_title'] ?? $meta['title'] ?? '');
    $ogDescription = (string) ($meta['og_description'] ?? $meta['description'] ?? '');
    $ogImage = (string) ($meta['og_image'] ?? '');

    $tags = [];

    $property = static function (string $name, string $value) use (&$tags): void {
        if ($value !== '') {
            $tags[] = '<meta property="'.e($name).'" content="'.e($value).'" />';
        }
    };
    $named = static function (string $name, string $value) use (&$tags): void {
        if ($value !== '') {
            $tags[] = '<meta name="'.e($name).'" content="'.e($value).'" />';
        }
    };

    $property('og:type', (string) ($meta['og_type'] ?? 'website'));
    $property('og:title', $ogTitle);
    $property('og:description', $ogDescription);
    $property('og:url', (string) ($meta['url'] ?? ''));
    $property('og:site_name', (string) ($meta['site_name'] ?? ''));

    if ($ogImage !== '') {
        $property('og:image', $ogImage);
        $property('og:image:alt', (string) ($meta['og_image_alt'] ?? $ogTitle));

        // LinkedIn and WhatsApp render a much better card when they can size
        // the image without downloading it first.
        [$width, $height] = social_image_dimensions($ogImage);
        if ($width > 0 && $height > 0) {
            $property('og:image:width', (string) $width);
            $property('og:image:height', (string) $height);
        }
    }

    if (($meta['og_type'] ?? '') === 'article') {
        $property('article:published_time', (string) ($meta['article_published_time'] ?? ''));
        $property('article:modified_time', (string) ($meta['article_modified_time'] ?? ''));
        $property('article:author', (string) ($meta['article_author'] ?? ''));
    }

    $named('twitter:card', (string) ($meta['twitter_card'] ?? 'summary_large_image'));
    $named('twitter:title', (string) ($meta['twitter_title'] ?? '') ?: $ogTitle);
    $named('twitter:description', (string) ($meta['twitter_description'] ?? '') ?: $ogDescription);
    $named('twitter:image', (string) ($meta['twitter_image'] ?? '') ?: $ogImage);
    $named('twitter:site', (string) ($meta['twitter_site'] ?? ''));
    $named('twitter:creator', (string) ($meta['twitter_creator'] ?? ''));

    return implode("\n  ", $tags);
}

/**
 * Pixel dimensions of a locally hosted social image.
 *
 * Only local files are measured: reaching out to a remote host mid-render
 * would put an unbounded network call in the critical path. Post pages are
 * full-page cached, so the getimagesize() call happens once per cache fill.
 *
 * Path resolution goes through AssetPathMapper because uploads live in
 * storage/uploads rather than under public/, and that mapper already handles
 * the traversal and containment checks.
 *
 * @param  string  $url  Absolute or root-relative image URL
 * @return array{int, int} Width and height, or [0, 0] when unavailable
 */
function social_image_dimensions(string $url): array
{
    $path = parse_url($url, PHP_URL_PATH);

    if (!is_string($path) || $path === '') {
        return [0, 0];
    }

    $host = parse_url($url, PHP_URL_HOST);
    $localHost = parse_url(base_url(), PHP_URL_HOST);

    if ($host !== null && $host !== $localHost) {
        return [0, 0];
    }

    // Constructed directly rather than resolved: it takes a plain string path,
    // which the container can't autowire. Matches ThemeService and the static
    // bypass, which do the same.
    static $mapper = null;
    $mapper ??= new AssetPathMapper(ROOT_PATH);

    $file = $mapper->fileFromUrlPath($path);

    if ($file === null) {
        return [0, 0];
    }

    $size = @getimagesize($file);

    return $size === false ? [0, 0] : [(int) $size[0], (int) $size[1]];
}
