<?php

declare(strict_types=1);

use App\Services\CompiledViewBrowser;

const COMPILED_HOME = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const COMPILED_BLOG = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

beforeEach(function () {
    $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lexicon-views-'.uniqid();
    mkdir($this->dir);
    file_put_contents($this->dir.'/'.COMPILED_HOME.'.php', "<?php\n// Source:    views/home.lex.php\necho 'plain';\n");
    file_put_contents($this->dir.'/'.COMPILED_BLOG.'.php', "<?php\n// Source:    views/blog.lex.php\n/* Fragment cache disabled: icons */\n");

    $this->browser = new CompiledViewBrowser($this->dir);
});

afterEach(function () {
    array_map('unlink', glob($this->dir.'/*') ?: []);
    rmdir($this->dir);
});

test('the listing names each template by the source in its header', function () {
    expect(array_column($this->browser->all(true), 'source'))->toBe(['views/blog.lex.php', 'views/home.lex.php']);
});

test('a template built with fragment caching off is stale while caching is on', function () {
    $stale = array_filter(array_column($this->browser->all(true), 'stale', 'id'));

    expect(array_keys($stale))->toBe([COMPILED_BLOG]);
});

test('no template is stale while caching is off when none was built with it on', function () {
    expect(array_filter(array_column($this->browser->all(false), 'stale')))->toBe([]);
});

test('deleting a template removes only that file', function () {
    expect($this->browser->delete(COMPILED_HOME))->toBeTrue()
        ->and(is_file($this->dir.'/'.COMPILED_HOME.'.php'))->toBeFalse()
        ->and(is_file($this->dir.'/'.COMPILED_BLOG.'.php'))->toBeTrue();
});

test('an id that is not a file hash is refused', function () {
    expect($this->browser->delete('not-a-hash'))->toBeFalse()
        ->and($this->browser->delete(strtoupper(COMPILED_HOME)))->toBeFalse()
        ->and($this->browser->read('not-a-hash', 100))->toBeNull()
        ->and(count(glob($this->dir.'/*.php')))->toBe(2);
});

test('a hash with no file behind it reports nothing to delete', function () {
    expect($this->browser->delete(str_repeat('c', 40)))->toBeFalse();
});

test('reading returns only the start of the file', function () {
    expect($this->browser->read(COMPILED_HOME, 5))->toBe('<?php');
});
