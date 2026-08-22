<?php

declare(strict_types=1);

/**
 * Application-level helper functions.
 *
 * Framework-agnostic helpers live in src/Framework/Core/helpers.php; the
 * helpers here depend on application services and models.
 */

use App\Helpers\TimezoneHelper;
use App\Models\BlogSettingsModel;
use App\Models\SettingModel;
use App\Models\SiteContentModel;
use App\Models\UserPreferencesModel;
use App\Services\AssetPathMapper;
use App\Services\LocaleRegistry;
use App\Services\TranslationService;

/**
 * Translate an interface string outside a view.
 *
 * $t() is a view global, so anything resolved before rendering -- a flash read
 * on the next request, a redirect message, a mail subject -- needs this. Same
 * chrome locale, same service, memoized per locale: a bare static here would
 * serve whichever locale it happened to build first to every locale after it.
 *
 * @param  string|string[]  $key  Dotted key or pre-split path
 * @param  array<string, mixed>  $params  Placeholder name => value pairs
 * @return string The translation, or the key itself when there is no entry
 */
function chrome_translate(string|array $key, array $params = []): string
{
    static $translators = [];
    $locale = App\Services\LocaleState::get()->chromeLocale;
    $translators[$locale] ??= new TranslationService($locale);

    return $translators[$locale]->translate($key, $params);
}

/**
 * Editable site text with translation fallback.
 *
 * Resolution order: admin override for the current locale, then a
 * locale-independent override, then the locale file entry for the same key.
 * Site content keys deliberately mirror translation keys so the locale files
 * act as the defaults and the database only stores what admins changed.
 *
 * Follows the chrome locale, the same as $t(): this is the platform's own
 * marketing and interface copy, not authored content a URL points at, so it
 * has to move with the reader's interface language rather than the URL. Using
 * locale() here used to split the homepage between the nav (chrome locale)
 * and the hero/FAQ copy (URL locale) for any signed-in reader whose account
 * language differed from the locale they landed on.
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

    $chromeLocale = App\Services\LocaleState::get()->chromeLocale;

    $value = $model->get($name, $chromeLocale);

    if ($value !== null && $value !== '') {
        return $value;
    }

    if ($default !== null) {
        return $default;
    }

    return chrome_translate($name);
}

/**
 * Label for a navigation item, translated when a translation exists.
 *
 * NavigationService emits both an English `label` and a `key`. Rendering the
 * key blindly is what left the public menu in English, but rendering the
 * translation blindly is worse: TranslationService returns the key itself on a
 * miss, so a half-translated locale shows "navigation.home" to the reader.
 * ar.json is missing roughly half its keys today, which is exactly that case.
 *
 * @param  array<string, mixed>  $item  Navigation item with `label` and optional `key`
 * @return string Translated label, or the English label when no translation exists
 */
function nav_label(array $item): string
{
    $label = (string) ($item['label'] ?? '');
    $key = (string) ($item['key'] ?? '');

    if ($key === '') {
        return $label;
    }

    // Navigation is interface text, so it follows the chrome locale.
    $translated = chrome_translate($key);

    // The service echoes the key back when the lookup misses.
    return $translated === $key ? $label : $translated;
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
 * Timezone configured for the site as a whole.
 *
 * Last resort for both of the resolvers below, so it never returns something
 * DateTimeZone will reject.
 *
 * @return string A valid timezone identifier
 */
function site_timezone(): string
{
    static $zone = null;

    if ($zone === null) {
        $configured = site_setting('timezone', 'UTC');
        $zone = TimezoneHelper::isValid($configured) ? $configured : 'UTC';
    }

    return $zone;
}

/**
 * Timezone the signed in viewer reads dates in.
 *
 * Falls back to the site timezone rather than UTC, since most accounts never
 * open the preferences page and UTC would be wrong for all of them.
 *
 * @return string A valid timezone identifier
 */
function viewer_timezone(): string
{
    static $zone = null;

    if ($zone !== null) {
        return $zone;
    }

    $user = auth()->user();

    if ($user) {
        $preference = app(UserPreferencesModel::class)->getTimezone((int) $user['id']);

        if (TimezoneHelper::isValid($preference)) {
            return $zone = $preference;
        }
    }

    return $zone = site_timezone();
}

/**
 * Timezone a blog publishes in.
 *
 * Public pages are full page cached, so they render on the blog's clock
 * instead of the reader's. Rendering per reader would store one visitor's
 * timezone in the cache and serve it to the next.
 *
 * @param  int|null  $blogId  Blog identifier, null falls back to the site zone
 * @return string A valid timezone identifier
 */
function blog_timezone(?int $blogId): string
{
    if ($blogId === null) {
        return site_timezone();
    }

    static $zones = [];

    if (!array_key_exists($blogId, $zones)) {
        // findByBlogId is fragment cached, so this costs nothing on a page that
        // already loaded the blog's settings.
        $settings = app(BlogSettingsModel::class)->findByBlogId($blogId);
        $candidate = $settings['timezone'] ?? null;

        $zones[$blogId] = TimezoneHelper::isValid($candidate) ? $candidate : site_timezone();
    }

    return $zones[$blogId];
}

/**
 * Format a stored UTC timestamp for display.
 *
 * Every timestamp in the database is UTC. Calling date() on a raw column
 * formats it in PHP's own zone, which is how dashboard times ended up hours
 * off, so views should come through here instead.
 *
 * @param  string|null  $utc  Datetime as stored, 'Y-m-d H:i:s'
 * @param  string  $format  Any date() format string
 * @param  string|null  $timezone  Target zone, defaults to the viewer's
 * @return string Formatted datetime, or '' when there is nothing to show
 */
function local_datetime(?string $utc, string $format = 'M j, Y', ?string $timezone = null): string
{
    if ($utc === null || trim($utc) === '') {
        return '';
    }

    $timezone ??= viewer_timezone();

    if (!TimezoneHelper::isValid($timezone)) {
        $timezone = site_timezone();
    }

    try {
        $when = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    } catch (Exception $e) {
        error_log("local_datetime could not parse '{$utc}': ".$e->getMessage());

        return '';
    }

    return $when->setTimezone(new DateTimeZone($timezone))->format($format);
}

/**
 * Machine readable timestamp for a <time datetime="..."> attribute.
 *
 * Always UTC with an offset, which is what a browser needs if anything ever
 * localises these client side.
 *
 * @param  string|null  $utc  Datetime as stored, 'Y-m-d H:i:s'
 * @return string ISO 8601 timestamp, or '' when there is nothing to show
 */
function iso_datetime(?string $utc): string
{
    return local_datetime($utc, 'c', 'UTC');
}

/**
 * How long ago something happened, or how long until it does.
 *
 * Timezone independent by construction, so it stays correct on a cached page
 * no matter who reads it.
 *
 * @param  string|null  $utc  Datetime as stored, 'Y-m-d H:i:s'
 * @param  bool  $short  Terse units for dense tables, '3h ago' over '3 hours ago'
 * @return string Human phrasing, or '' when there is nothing to show
 */
function relative_time(?string $utc, bool $short = false): string
{
    if ($utc === null || trim($utc) === '') {
        return '';
    }

    $seconds = strtotime($utc.' UTC');

    if ($seconds === false) {
        return '';
    }

    $seconds -= time();
    $ahead = $seconds > 0;
    $seconds = abs($seconds);

    if ($seconds < 60) {
        $text = $short ? $seconds.'s' : plural_unit($seconds, 'second');
    } elseif ($seconds < 3600) {
        $count = (int) round($seconds / 60);
        $text = $short ? $count.'m' : plural_unit($count, 'minute');
    } elseif ($seconds < 86400) {
        $count = (int) round($seconds / 3600);
        $text = $short ? $count.'h' : plural_unit($count, 'hour');
    } elseif ($seconds < 604800) {
        $count = (int) round($seconds / 86400);
        $text = $short ? $count.'d' : plural_unit($count, 'day');
    } elseif ($seconds < 2592000) {
        // Floor, not round, from here up: rounding lets five and a half weeks
        // read as "6 weeks" when the next unit has already taken over.
        $count = (int) floor($seconds / 604800);
        $text = $short ? $count.'w' : plural_unit($count, 'week');
    } elseif ($seconds < 31536000) {
        // Capped at 11 so a 30-day month never counts its way to "12 months",
        // which is a year by any reader's arithmetic.
        $count = min(11, (int) floor($seconds / 2592000));
        $text = $short ? $count.'mo' : plural_unit($count, 'month');
    } else {
        $count = (int) floor($seconds / 31536000);
        $text = $short ? $count.'y' : plural_unit($count, 'year');
    }

    return $ahead ? 'in '.$text : $text.' ago';
}

/**
 * Pluralise a counted unit, '1 hour' against '2 hours'.
 *
 * @param  int  $count  How many
 * @param  string  $unit  Singular noun
 */
function plural_unit(int $count, string $unit): string
{
    return $count.' '.$unit.($count === 1 ? '' : 's');
}

/**
 * A language's own name for itself, for language pickers.
 *
 * Not translated on purpose: a picker exists for someone who cannot read the
 * language currently on screen.
 *
 * @param  string  $code  Locale code
 * @return string Native name, or the uppercased code when unknown
 */
function locale_native_name(string $code): string
{
    return app(LocaleRegistry::class)->nativeName($code);
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
