<?php

declare(strict_types=1);

/**
 * A field the server rejected must look rejected. The browser's :valid state only
 * knows about attributes like required, so it cannot be what colours the border.
 */
$input = file_get_contents(ROOT_PATH.'/views/components/input.lex.php');

test('a server error does not rely on the valid or invalid pseudo classes', function () use ($input) {
    expect($input)->not->toContain('valid:border-green')
        ->and($input)->not->toContain('invalid:border-red');
});

test('the error message is what the field points screen readers at', function () use ($input) {
    expect($input)->toContain('aria-describedby="<?= e($elementName) ?>_error"')
        ->and($input)->toContain('id="<?= e($elementName) ?>_error"');
});
