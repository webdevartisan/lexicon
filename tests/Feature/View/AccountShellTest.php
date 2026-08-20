<?php

declare(strict_types=1);

/**
 * The rail is page navigation, not tabs: a nav with an accessible name and
 * aria-current="page", never role="tablist". Four items, deletion excluded.
 */
$partial = file_get_contents(ROOT_PATH.'/views/partials/_account_shell.lex.php');

test('the rail is a nav, not a tablist', function () use ($partial) {
    expect($partial)->toContain('lx-account-nav');
    expect($partial)->not->toContain('role="tablist"');
    expect($partial)->not->toContain('role="tab"');
});

test('the rail carries aria-current and an accessible name', function () use ($partial) {
    expect($partial)->toContain('aria-current');
    expect($partial)->toMatch('/<nav[^>]+aria-label/');
});

test('the four rail destinations are present and delete is not a rail item', function () use ($partial) {
    // The rail is data-driven: hrefs live in the $items array and go through
    // lurl() in the loop, so assert on the paths and that lurl() is applied.
    expect($partial)->toContain("'/account/profile'");
    expect($partial)->toContain("'/account/preferences'");
    expect($partial)->toContain("'/account/notifications'");
    expect($partial)->toContain("'/account/security'");
    expect($partial)->toContain('lurl($item[');
    expect($partial)->not->toContain("'/account/delete'");
});
