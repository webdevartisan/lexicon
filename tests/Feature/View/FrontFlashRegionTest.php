<?php

declare(strict_types=1);

/**
 * front.lex.php reads flash() into $flash on line 2 but historically never
 * rendered it, so every front POST flashed into the void. This pins the region
 * in place: an aria-live container that loops $flash.
 */
$layout = file_get_contents(ROOT_PATH.'/views/layouts/front.lex.php');

test('front layout renders the flash it reads', function () use ($layout) {
    // The variable is assigned at the top; it must also be consumed.
    expect(substr_count($layout, '$flash'))->toBeGreaterThan(1);
});

test('front layout flash region is a live region', function () use ($layout) {
    expect($layout)->toContain('aria-live');
});

test('the flash renders as a fixed toast overlay, not an inline banner', function () use ($layout) {
    // A fixed overlay so a save confirmation never shoves the page down, and
    // each message is a dismissible toast, matching the dashboard's placement.
    expect($layout)->toContain('lx-toasts');
    expect($layout)->toContain('data-lx-toast');
    expect($layout)->toContain('lx-toast-close');
});
