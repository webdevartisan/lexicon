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

test('the post editor exposes the sidebar slot', function () {
    $form = file_get_contents(ROOT_PATH.'/views/partials/dashboard/post/_form.lex.php');

    expect(substr_count($form, '{% slot "editor.sidebar" %}'))->toBe(1);
});

test('the shared theme partials expose the page slots', function () {
    expect(file_get_contents(ROOT_PATH.'/views/partials/public/_theme_head.lex.php'))->toContain('{% slot "head.end" %}')
        ->and(file_get_contents(ROOT_PATH.'/views/partials/public/_theme_scripts.lex.php'))
        ->toContain('{% slot "body.end" %}');
});
