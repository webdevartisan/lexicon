<?php

declare(strict_types=1);

/**
 * The languages the platform offers, and everything the code needs to know
 * about each one. LocaleRegistry is the only reader; everything else (routing,
 * pickers, page metadata, emails, tests) asks it.
 *
 * Each language, in the order pickers list them:
 *   name       the language's name in itself, for pickers ("Ελληνικά", not "Greek")
 *   dir        'ltr' or 'rtl'
 *   og_locale  the region-qualified Open Graph locale, e.g. 'el_GR'
 *
 * A language listed here also needs locales/{code}.json. LocaleRegistry leaves
 * out any language with no strings file, so adding one here without the file
 * degrades to a redirect rather than a page of raw translation keys.
 *
 * storage/localization.json, when present, replaces this file entirely and
 * takes the same shape.
 */
return [
    'default' => 'en',

    'languages' => [
        'en' => ['name' => 'English', 'dir' => 'ltr', 'og_locale' => 'en_US'],
        'el' => ['name' => 'Ελληνικά', 'dir' => 'ltr', 'og_locale' => 'el_GR'],
        'ar' => ['name' => 'العربية', 'dir' => 'rtl', 'og_locale' => 'ar_AE'],
    ],
];
