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
