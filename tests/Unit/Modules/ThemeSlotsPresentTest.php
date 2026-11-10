<?php

declare(strict_types=1);

/**
 * Modules can only draw where a theme leaves a slot. Every theme must offer the
 * same inline slots, or a module would show on some blogs and silently not on others.
 */
test('every theme exposes the inline module slots', function (string $theme) {
    $post = file_get_contents(ROOT_PATH."/themes/{$theme}/views/posts/show.lex.php");
    $base = file_get_contents(ROOT_PATH."/themes/{$theme}/views/layouts/base.lex.php");

    expect(substr_count($post, '{% slot "post.header-aside" %}'))->toBe(1)
        ->and(substr_count($post, '{% slot "post.after-content" %}'))->toBe(1)
        ->and(substr_count($base, '{% slot "blog.nav" %}'))->toBe(1);
})->with(['closest', 'folio', 'nocturne', 'offset', 'vernissage']);

test('the post editor exposes the main column slot', function () {
    $form = file_get_contents(ROOT_PATH.'/views/partials/dashboard/post/_form.lex.php');

    expect(substr_count($form, '{% slot "editor.main" %}'))->toBe(1);
});

test('the shared theme partials expose the page slots', function () {
    expect(file_get_contents(ROOT_PATH.'/views/partials/public/_theme_head.lex.php'))->toContain('{% slot "head.end" %}')
        ->and(file_get_contents(ROOT_PATH.'/views/partials/public/_theme_scripts.lex.php'))
        ->toContain('{% slot "body.end" %}');
});

test('the dashboard layout has the page slots', function () {
    $layout = file_get_contents(ROOT_PATH.'/views/layouts/back.lex.php');

    expect(substr_count($layout, '{% slot "head.end" %}'))->toBe(1)
        ->and(substr_count($layout, '{% slot "body.end" %}'))->toBe(1);
});

/**
 * Module styles read only these tokens, so a module looks native in every theme
 * without a theme ever naming a module.
 */
test('every theme defines the module tokens', function (string $theme) {
    $css = file_get_contents(ROOT_PATH."/themes/{$theme}/public/css/style.css");
    $tokens = ['accent', 'ink', 'muted', 'surface', 'rule', 'font', 'mono', 'map-flavor'];

    foreach ($tokens as $token) {
        expect($css)->toContain("--module-{$token}:");
    }
})->with(['closest', 'folio', 'nocturne', 'offset', 'vernissage']);
