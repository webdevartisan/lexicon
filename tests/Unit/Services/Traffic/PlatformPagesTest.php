<?php

declare(strict_types=1);

use App\Services\Traffic\PlatformPages;

test("the platform's public pages are recognised and stored under one path each", function (array $segments, array $expected) {
    expect(PlatformPages::match($segments))->toBe($expected);
})->with([
    'home' => [[], ['home', '/']],
    'home alias' => [['home'], ['home', '/']],
    'discover' => [['discover'], ['discover', '/discover']],
    'about' => [['about'], ['static_page', '/about']],
    'contact' => [['contact'], ['static_page', '/contact']],
    'guides index' => [['getting-started'], ['guide', '/getting-started']],
    'a guide' => [['getting-started', 'blog-with-your-team'], ['guide', '/getting-started/blog-with-your-team']],
    'a profile, every handle in one row' => [['profile', 'anyone'], ['profile', '/profile']],
    'sign-in' => [['login'], ['auth', '/login']],
    'sign-up' => [['register'], ['auth', '/register']],
]);

test('everything else is left to the blog resolver or not counted at all', function (array $segments) {
    expect(PlatformPages::match($segments))->toBeNull();
})->with([
    'a blog page' => [['blog', 'demo']],
    'an account page' => [['account', 'profile']],
    'a password reset link' => [['password', 'reset', 'abc123']],
    'an invitation link' => [['invite', 'abc123']],
    'a guide that does not exist' => [['getting-started', 'not-a-guide']],
    'the dashboard' => [['dashboard']],
    'about with something after it' => [['about', 'extra']],
]);

test('a request path counts the same way, slashes and all', function () {
    expect(PlatformPages::counts('/'))->toBeTrue()
        ->and(PlatformPages::counts('/about/'))->toBeTrue()
        ->and(PlatformPages::counts('/account/security'))->toBeFalse()
        ->and(PlatformPages::counts('/blog/demo'))->toBeFalse();
});
