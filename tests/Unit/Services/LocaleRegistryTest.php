<?php

declare(strict_types=1);

use App\Services\LocaleRegistry;

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
        ['supported' => ['en', 'fr', 'el'], 'default' => 'en'],
        ['en', 'el']
    );

    expect((new LocaleRegistry($root))->supported())->toBe(['en', 'el']);
});

test('the default locale is returned even when its file is the only one present', function () {
    $root = ($this->makeRoot)(['supported' => ['en', 'fr'], 'default' => 'en'], ['en']);

    $registry = new LocaleRegistry($root);

    expect($registry->default())->toBe('en')
        ->and($registry->supported())->toBe(['en']);
});

test('supported never returns an empty list', function () {
    $root = ($this->makeRoot)(['supported' => ['fr', 'de'], 'default' => 'en'], []);

    expect((new LocaleRegistry($root))->supported())->toBe(['en']);
});

test('normalize accepts a supported locale in any casing and rejects the rest', function () {
    $root = ($this->makeRoot)(['supported' => ['en', 'el'], 'default' => 'en'], ['en', 'el']);

    $registry = new LocaleRegistry($root);

    expect($registry->normalize('EL'))->toBe('el')
        ->and($registry->normalize(' en '))->toBe('en')
        ->and($registry->normalize('fr'))->toBeNull()
        ->and($registry->normalize(null))->toBeNull();
});

test('right-to-left locales are recognised from the class constant', function () {
    $root = ($this->makeRoot)(['supported' => ['en', 'ar'], 'default' => 'en'], ['en', 'ar']);

    $registry = new LocaleRegistry($root);

    expect($registry->isRtl('ar'))->toBeTrue()
        ->and($registry->isRtl('AR'))->toBeTrue()
        ->and($registry->isRtl('el'))->toBeFalse();
});

test('a runtime override file takes precedence over the shipped config', function () {
    $root = ($this->makeRoot)(['supported' => ['en'], 'default' => 'en'], ['en', 'el']);

    mkdir($root.'/storage', 0777, true);
    file_put_contents(
        $root.'/storage/localization.json',
        json_encode(['supported' => ['en', 'el'], 'default' => 'el'])
    );

    $registry = new LocaleRegistry($root);

    expect($registry->supported())->toBe(['en', 'el'])
        ->and($registry->default())->toBe('el');
});

test('a corrupt override file falls back to the shipped config', function () {
    $root = ($this->makeRoot)(['supported' => ['en'], 'default' => 'en'], ['en']);

    mkdir($root.'/storage', 0777, true);
    file_put_contents($root.'/storage/localization.json', 'not json at all');

    expect((new LocaleRegistry($root))->supported())->toBe(['en']);
});

/**
 * The locale code reaches hasTranslationFile straight from the URL prefix, so a
 * traversal attempt must not become a filesystem probe.
 */
test('locale codes that are not plain language tags are rejected', function (string $code) {
    $root = ($this->makeRoot)(['supported' => ['en'], 'default' => 'en'], ['en']);

    expect((new LocaleRegistry($root))->hasTranslationFile($code))->toBeFalse();
})->with(['../../etc/passwd', 'en/../../secret', 'e', 'toolongcode', '..']);
