<?php

declare(strict_types=1);

/**
 * The blog auth modal posts to endpoints named in its markup. Each must be a
 * live POST route, or the modal fails for every reader who tries to log in.
 */
$router = require ROOT_PATH.'/config/routes.php';
$partial = file_get_contents(ROOT_PATH.'/views/partials/_auth_modal.lex.php');
$script = file_get_contents(ROOT_PATH.'/public/assets/js/auth-modal.js');

test('every endpoint the modal posts to is a POST route', function () use ($router, $partial) {
    preg_match_all("#data-(?:identify|login|register|forgot)-url=\"<\?= e\(lurl\('([^']+)'\)\) \?>\"#", $partial, $matches);

    expect($matches[1])->toHaveCount(4);

    foreach ($matches[1] as $path) {
        expect($router->match($path, 'POST'))->not->toBeFalse("{$path} is not a POST route");
    }
});

test('the modal script falls back to live routes, never the removed login endpoint', function () use ($router, $script) {
    expect($script)->not->toContain('/login/submit');

    preg_match_all("#\|\| '(/[a-z/]+)'#", $script, $fallbacks);
    foreach (array_diff($fallbacks[1], ['/auth/nav']) as $path) {
        expect($router->match($path, 'POST'))->not->toBeFalse("{$path} is not a POST route");
    }
});
