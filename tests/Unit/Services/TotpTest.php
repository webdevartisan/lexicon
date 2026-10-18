<?php

declare(strict_types=1);

use App\Services\TwoFactor\SecretBox;
use App\Services\TwoFactor\Totp;

/**
 * The code generator has to agree with every authenticator app, so it is held
 * to the published RFC 6238 test values, and the sealing of stored secrets has
 * to round-trip and refuse anything altered.
 */
beforeEach(function () {
    $this->totp = new Totp();
    // "12345678901234567890" from the RFC, base32 encoded
    $this->rfcSecret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
});

test('codes match the RFC 6238 SHA-1 test values', function (int $time, string $expected) {
    expect($this->totp->codeAt($this->rfcSecret, $this->totp->stepAt($time)))->toBe($expected);
})->with([
    [59, '287082'],
    [1111111109, '081804'],
    [1111111111, '050471'],
    [1234567890, '005924'],
    [2000000000, '279037'],
]);

test('a code is accepted one step either side of now and not beyond', function () {
    $now = 1234567890;
    $step = $this->totp->stepAt($now);

    expect($this->totp->matchingStep($this->rfcSecret, $this->totp->codeAt($this->rfcSecret, $step), $now))->toBe($step);
    expect($this->totp->matchingStep($this->rfcSecret, $this->totp->codeAt($this->rfcSecret, $step - 1), $now))->toBe($step - 1);
    expect($this->totp->matchingStep($this->rfcSecret, $this->totp->codeAt($this->rfcSecret, $step + 1), $now))->toBe($step + 1);
    expect($this->totp->matchingStep($this->rfcSecret, $this->totp->codeAt($this->rfcSecret, $step - 2), $now))->toBeNull();
});

test('spaces are ignored and anything that is not six digits is refused', function () {
    $now = 59;
    $code = $this->totp->codeAt($this->rfcSecret, $this->totp->stepAt($now));
    $spaced = substr($code, 0, 3).' '.substr($code, 3);

    expect($this->totp->matchingStep($this->rfcSecret, $spaced, $now))->not->toBeNull();
    expect($this->totp->matchingStep($this->rfcSecret, '12345', $now))->toBeNull();
    expect($this->totp->matchingStep($this->rfcSecret, 'abcdef', $now))->toBeNull();
    expect($this->totp->matchingStep($this->rfcSecret, '', $now))->toBeNull();
});

test('a new secret is thirty two base32 characters and different each time', function () {
    $first = $this->totp->generateSecret();

    expect($first)->toMatch('/^[A-Z2-7]{32}$/');
    expect($this->totp->generateSecret())->not->toBe($first);
});

test('the provisioning address names the account and the issuer', function () {
    $uri = $this->totp->provisioningUri('ABCDEFGH', 'person@example.com', 'My Site');

    expect($uri)->toStartWith('otpauth://totp/My%20Site%3Aperson%40example.com?');
    expect($uri)->toContain('secret=ABCDEFGH');
    expect($uri)->toContain('issuer=My%20Site');
});

test('a sealed secret opens with the same key and nothing else', function () {
    $box = new SecretBox('a-long-enough-application-key-for-the-test');
    $sealed = $box->seal('GEZDGNBVGY3TQOJQ');

    expect($sealed)->not->toContain('GEZDGNBVGY3TQOJQ');
    expect($box->open($sealed))->toBe('GEZDGNBVGY3TQOJQ');
    expect((new SecretBox('some-other-application-key-entirely'))->open($sealed))->toBeNull();
    expect($box->open(base64_encode('short')))->toBeNull();
    expect($box->open('not base64 at all !!'))->toBeNull();
});

test('sealing the same secret twice gives different text', function () {
    $box = new SecretBox('a-long-enough-application-key-for-the-test');

    expect($box->seal('SAME'))->not->toBe($box->seal('SAME'));
});
