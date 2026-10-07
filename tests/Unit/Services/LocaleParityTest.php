<?php

declare(strict_types=1);

use App\Services\LocaleRegistry;

/**
 * Every language the site offers must carry the same key set as English. A key
 * added to one and forgotten in another is exactly what the English fallback
 * masks at runtime, so it has to fail here instead.
 */
function flattenKeys(array $data, string $prefix = ''): array
{
    $keys = [];
    foreach ($data as $k => $v) {
        $path = $prefix === '' ? (string) $k : $prefix.'.'.$k;
        if (is_array($v)) {
            $keys = array_merge($keys, flattenKeys($v, $path));
        } else {
            $keys[] = $path;
        }
    }

    return $keys;
}

function localeKeys(string $locale): array
{
    $data = json_decode(file_get_contents(ROOT_PATH.'/locales/'.$locale.'.json'), true);
    $keys = flattenKeys($data);
    sort($keys);

    return $keys;
}

test('every supported language carries the same key set as English', function (string $locale) {
    expect(localeKeys($locale))->toBe(localeKeys('en'));
})->with(fn (): array => array_values(array_diff((new LocaleRegistry(ROOT_PATH))->supported(), ['en'])));

test('the account namespace exists', function () {
    expect(localeKeys('en'))->toContain('account.profile.heading');
});
