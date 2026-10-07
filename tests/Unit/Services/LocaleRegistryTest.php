<?php

declare(strict_types=1);

use App\Services\LocaleRegistry;

/**
 * A languages section for config/localization.php naming only the codes.
 *
 * @return array<string, array{name: string, dir: string, og_locale: string}>
 */
function languagesFor(string ...$codes): array
{
    $languages = [];
    foreach ($codes as $code) {
        $languages[$code] = ['name' => strtoupper($code), 'dir' => 'ltr', 'og_locale' => $code.'_XX'];
    }

    return $languages;
}

/**
 * LocaleRegistry Unit Test Suite
 *
 * The locale list used to live in six places at once, which is how fr and de
 * stayed advertised with no strings file behind them. These tests pin the
 * single source of truth and the file-existence guard that prevents a repeat.
 */
beforeEach(function () {
    $this->createdRoots = [];

    $this->makeRoot = function (array $config, array $localeFiles): string {
        $root = sys_get_temp_dir().'/lexicon-locale-'.uniqid('', true);

        mkdir($root.'/config', 0777, true);
        mkdir($root.'/locales', 0777, true);

        file_put_contents(
            $root.'/config/localization.php',
            '<?php return '.var_export($config, true).';'
        );

        foreach ($localeFiles as $code) {
            file_put_contents($root.'/locales/'.$code.'.json', '{"a":"b"}');
        }

        $this->createdRoots[] = $root;

        return $root;
    };
});

afterEach(function () {
    $removeRecursively = static function (string $path) use (&$removeRecursively): void {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path.'/'.$item;

            if (is_dir($fullPath)) {
                $removeRecursively($fullPath);
            } else {
                @unlink($fullPath);
            }
        }

        @rmdir($path);
    };

    foreach ($this->createdRoots as $root) {
        $removeRecursively($root);
    }
});

test('a configured locale with no strings file is not supported', function () {
    $root = ($this->makeRoot)(
        ['default' => 'en', 'languages' => languagesFor('en', 'fr', 'el')],
        ['en', 'el']
    );

    $registry = new LocaleRegistry($root);

    expect($registry->supported())->toBe(['en', 'el'])
        ->and($registry->configured())->toBe(['en', 'fr', 'el']);
});

test('the default locale is returned even when its file is the only one present', function () {
    $root = ($this->makeRoot)(['default' => 'en', 'languages' => languagesFor('en', 'fr')], ['en']);

    $registry = new LocaleRegistry($root);

    expect($registry->default())->toBe('en')
        ->and($registry->supported())->toBe(['en']);
});

test('supported never returns an empty list', function () {
    $root = ($this->makeRoot)(['default' => 'en', 'languages' => languagesFor('fr', 'de')], []);

    expect((new LocaleRegistry($root))->supported())->toBe(['en']);
});

test('the configured order is the order languages are offered in', function () {
    $root = ($this->makeRoot)(['default' => 'en', 'languages' => languagesFor('el', 'en', 'ar')], ['en', 'el', 'ar']);

    expect((new LocaleRegistry($root))->supported())->toBe(['el', 'en', 'ar']);
});

test('normalize accepts a supported locale in any casing and rejects the rest', function () {
    $root = ($this->makeRoot)(['default' => 'en', 'languages' => languagesFor('en', 'el')], ['en', 'el']);

    $registry = new LocaleRegistry($root);

    expect($registry->normalize('EL'))->toBe('el')
        ->and($registry->normalize(' en '))->toBe('en')
        ->and($registry->normalize('fr'))->toBeNull()
        ->and($registry->normalize(null))->toBeNull();
});

test('text direction comes from each language\'s configuration', function () {
    $languages = languagesFor('en', 'ar', 'he');
    $languages['ar']['dir'] = 'rtl';
    $languages['he']['dir'] = 'RTL';
    $root = ($this->makeRoot)(['default' => 'en', 'languages' => $languages], ['en', 'ar', 'he']);

    $registry = new LocaleRegistry($root);

    expect($registry->isRtl('ar'))->toBeTrue()
        ->and($registry->isRtl('AR'))->toBeTrue()
        ->and($registry->isRtl('he'))->toBeTrue()
        ->and($registry->isRtl('en'))->toBeFalse()
        ->and($registry->isRtl('fa'))->toBeFalse();
});

test('the Open Graph locale comes from configuration, else the code doubled', function () {
    $languages = languagesFor('en', 'fr');
    $languages['en']['og_locale'] = 'en_GB';
    unset($languages['fr']['og_locale']);
    $root = ($this->makeRoot)(['default' => 'en', 'languages' => $languages], ['en', 'fr']);

    $registry = new LocaleRegistry($root);

    expect($registry->ogLocale('en'))->toBe('en_GB')
        ->and($registry->ogLocale('fr'))->toBe('fr_FR')
        ->and($registry->ogLocale('zz'))->toBe('zz_ZZ');
});

test('a runtime override file takes precedence over the shipped config', function () {
    $root = ($this->makeRoot)(['default' => 'en', 'languages' => languagesFor('en')], ['en', 'el']);

    mkdir($root.'/storage', 0777, true);
    file_put_contents(
        $root.'/storage/localization.json',
        json_encode(['default' => 'el', 'languages' => languagesFor('en', 'el')])
    );

    $registry = new LocaleRegistry($root);

    expect($registry->supported())->toBe(['en', 'el'])
        ->and($registry->default())->toBe('el');
});

test('a corrupt override file falls back to the shipped config', function () {
    $root = ($this->makeRoot)(['default' => 'en', 'languages' => languagesFor('en')], ['en']);

    mkdir($root.'/storage', 0777, true);
    file_put_contents($root.'/storage/localization.json', 'not json at all');

    expect((new LocaleRegistry($root))->supported())->toBe(['en']);
});

/**
 * The locale code reaches hasTranslationFile straight from the URL prefix, so a
 * traversal attempt must not become a filesystem probe.
 */
test('locale codes that are not plain language tags are rejected', function (string $code) {
    $root = ($this->makeRoot)(['default' => 'en', 'languages' => languagesFor('en')], ['en']);

    expect((new LocaleRegistry($root))->hasTranslationFile($code))->toBeFalse();
})->with(['../../etc/passwd', 'en/../../secret', 'e', 'toolongcode', '..']);

/**
 * A language picker names each language in that language, never in the viewer's.
 * Translating these would show "Greek" to the one person who cannot read English,
 * which is exactly who the entry is for.
 */
test('the shipped languages are named in the language itself', function () {
    $registry = new LocaleRegistry(ROOT_PATH);

    expect($registry->nativeName('en'))->toBe('English')
        ->and($registry->nativeName('el'))->toBe('Ελληνικά')
        ->and($registry->nativeName('ar'))->toBe('العربية')
        ->and($registry->isRtl('ar'))->toBeTrue()
        ->and($registry->ogLocale('el'))->toBe('el_GR');
});

test('every shipped language is fully described', function (string $code) {
    $config = require ROOT_PATH.'/config/localization.php';
    $language = $config['languages'][$code];

    expect($language['name'] ?? '')->not->toBe('')
        ->and($language['dir'] ?? '')->toBeIn(['ltr', 'rtl'])
        ->and($language['og_locale'] ?? '')->toMatch('/^[a-z]{2,3}_[A-Z]{2}$/');
})->with(fn (): array => array_keys((require ROOT_PATH.'/config/localization.php')['languages']));

test('names lists the supported languages in order, by their own names', function () {
    $languages = languagesFor('en', 'el', 'fr');
    $languages['el']['name'] = 'Ελληνικά';
    $root = ($this->makeRoot)(['default' => 'en', 'languages' => $languages], ['en', 'el']);

    expect((new LocaleRegistry($root))->names())->toBe(['en' => 'EN', 'el' => 'Ελληνικά']);
});

test('an unknown code falls back to its own uppercased form', function () {
    $root = ($this->makeRoot)(['default' => 'en', 'languages' => languagesFor('en')], ['en']);

    expect((new LocaleRegistry($root))->nativeName('zz'))->toBe('ZZ');
});

test('native name lookup is case insensitive', function () {
    expect((new LocaleRegistry(ROOT_PATH))->nativeName('EL'))->toBe('Ελληνικά');
});
