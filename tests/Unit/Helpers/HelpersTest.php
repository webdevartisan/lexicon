<?php

declare(strict_types=1);

it('escapes HTML entities', function () {
    $html = '<script>alert("XSS")</script>';

    $escaped = e($html);

    expect($escaped)->toBe('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;');
});

it('handles null values in e()', function () {
    expect(e(null))->toBe('');
});

it('retrieves old input from session', function () {
    $_SESSION['_old_input'] = ['name' => 'John'];

    expect(old('name'))->toBe('John')
        ->and(old('missing', 'default'))->toBe('default');
});

it('retrieves validation errors from session', function () {
    $_SESSION['_errors'] = ['email' => 'Invalid email'];

    $allErrors = errors();

    expect($allErrors)->toHaveKey('email', 'Invalid email');
});

test('locale_native_name gives the language its own name', function () {
    expect(locale_native_name('el'))->toBe('Ελληνικά');
});

test('locale_native_name falls back to the code for anything unknown', function () {
    expect(locale_native_name('zz'))->toBe('ZZ');
});
