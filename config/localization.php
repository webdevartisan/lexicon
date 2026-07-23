<?php

declare(strict_types=1);

/**
 * Locales the platform offers.
 *
 * A locale listed here also needs locales/{code}.json. LocaleRegistry filters
 * out any entry with no strings file, so adding one here without the file
 * degrades to a redirect rather than a page of raw translation keys.
 *
 * Text direction is not configured here. It lives in LocaleRegistry::RTL,
 * because direction is a property of the language rather than a deployment
 * choice.
 */
return [
    'supported' => ['en', 'el', 'ar'],
    'default' => 'en',
];
