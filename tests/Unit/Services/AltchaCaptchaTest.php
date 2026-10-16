<?php

declare(strict_types=1);

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use App\Services\Captcha\AltchaCaptcha;
use App\Services\Captcha\CaptchaResult;
use Framework\Cache\RateLimitStore;

/**
 * The sign-in check: answers verify locally, once, for the purpose and email
 * they were issued for. A tiny work factor keeps solving fast in tests.
 */
beforeEach(function () {
    $this->storeDir = sys_get_temp_dir().'/lx-captcha-test-'.bin2hex(random_bytes(4));
    $this->store = new RateLimitStore($this->storeDir);
});

afterEach(function () {
    foreach (glob($this->storeDir.'/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($this->storeDir);
});

function makeCaptcha(RateLimitStore $store, int $expires = 300): AltchaCaptcha
{
    return new AltchaCaptcha(
        new Altcha(hmacSignatureSecret: 'test-signature', hmacKeySignatureSecret: 'test-key-signature'),
        $store,
        'test-binding',
        ['cost' => 10, 'counter_min' => 1, 'counter_max' => 3, 'max_level' => 3, 'expires_seconds' => $expires]
    );
}

/**
 * @param  array<string, mixed>  $challenge
 */
function solveCaptcha(array $challenge): string
{
    $parsed = Challenge::fromArray($challenge);
    $solution = (new Altcha())->solveChallenge(new SolveChallengeOptions(challenge: $parsed, algorithm: new Pbkdf2()));

    return (new Payload($parsed, $solution))->toBase64();
}

test('a solved challenge passes for the purpose and email it was issued for', function () {
    $captcha = makeCaptcha($this->store);
    $payload = solveCaptcha($captcha->issue('login', 'owner@example.com'));

    expect($captcha->verify($payload, 'login', 'owner@example.com'))->toBe(CaptchaResult::Passed);
});

test('the email binding ignores case and surrounding spaces', function () {
    $captcha = makeCaptcha($this->store);
    $payload = solveCaptcha($captcha->issue('login', 'Owner@Example.com'));

    expect($captcha->verify($payload, 'login', ' owner@example.com '))->toBe(CaptchaResult::Passed);
});

test('the challenge does not show the email it is bound to', function () {
    $challenge = makeCaptcha($this->store)->issue('login', 'owner@example.com');

    expect(json_encode($challenge))->not->toContain('owner@example.com');
});

test('an empty answer is reported as missing', function () {
    expect(makeCaptcha($this->store)->verify('', 'login', 'owner@example.com'))->toBe(CaptchaResult::Missing);
});

test('garbage and oversized answers are reported as malformed', function (string $payload) {
    expect(makeCaptcha($this->store)->verify($payload, 'login', 'owner@example.com'))->toBe(CaptchaResult::Malformed);
})->with([
    'not base64' => ['%%%'],
    'base64 of non-json' => [base64_encode('hello')],
    'json without a solution' => [base64_encode('{"challenge":{"parameters":{}}}')],
    'too large' => [str_repeat('A', 5000)],
]);

test('an answer is refused the second time', function () {
    $captcha = makeCaptcha($this->store);
    $payload = solveCaptcha($captcha->issue('login', 'owner@example.com'));

    expect($captcha->verify($payload, 'login', 'owner@example.com'))->toBe(CaptchaResult::Passed)
        ->and($captcha->verify($payload, 'login', 'owner@example.com'))->toBe(CaptchaResult::Replayed);
});

test('an answer for one email cannot be used for another', function () {
    $captcha = makeCaptcha($this->store);
    $payload = solveCaptcha($captcha->issue('login', 'attacker@example.com'));

    expect($captcha->verify($payload, 'login', 'owner@example.com'))->toBe(CaptchaResult::Mismatch);
});

test('a registration answer cannot be used to sign in', function () {
    $captcha = makeCaptcha($this->store);
    $payload = solveCaptcha($captcha->issue('register'));

    expect($captcha->verify($payload, 'login', ''))->toBe(CaptchaResult::Mismatch);
});

test('an expired challenge is refused', function () {
    $captcha = makeCaptcha($this->store, expires: -10);
    $payload = solveCaptcha($captcha->issue('login', 'owner@example.com'));

    expect($captcha->verify($payload, 'login', 'owner@example.com'))->toBe(CaptchaResult::Expired);
});

test('a tampered challenge is refused', function () {
    $captcha = makeCaptcha($this->store);
    $challenge = $captcha->issue('login', 'owner@example.com');
    $payload = json_decode(base64_decode(solveCaptcha($challenge)), true);
    $payload['challenge']['parameters']['cost'] = 1;

    expect($captcha->verify(base64_encode((string) json_encode($payload)), 'login', 'owner@example.com'))
        ->toBe(CaptchaResult::Invalid);
});

test('a wrong solution is refused', function () {
    $captcha = makeCaptcha($this->store);
    $payload = json_decode(base64_decode(solveCaptcha($captcha->issue('login', 'owner@example.com'))), true);
    $payload['solution']['derivedKey'] = str_repeat('0', 64);

    expect($captcha->verify(base64_encode((string) json_encode($payload)), 'login', 'owner@example.com'))
        ->toBe(CaptchaResult::Invalid);
});

test('a challenge signed with another key is refused', function () {
    $other = new AltchaCaptcha(
        new Altcha(hmacSignatureSecret: 'other', hmacKeySignatureSecret: 'other-key'),
        $this->store,
        'test-binding',
        ['cost' => 10, 'counter_min' => 1, 'counter_max' => 3, 'max_level' => 3, 'expires_seconds' => 300]
    );
    $payload = solveCaptcha($other->issue('login', 'owner@example.com'));

    expect(makeCaptcha($this->store)->verify($payload, 'login', 'owner@example.com'))->toBe(CaptchaResult::Invalid);
});

test('higher levels hide the answer in a wider range', function () {
    $captcha = makeCaptcha($this->store);
    $counters = [];
    for ($i = 0; $i < 20; $i++) {
        $challenge = Challenge::fromArray($captcha->issue('login', 'owner@example.com', 3));
        $counters[] = (new Altcha())->solveChallenge(new SolveChallengeOptions(challenge: $challenge, algorithm: new Pbkdf2()))->counter;
    }

    expect(min($counters))->toBeGreaterThanOrEqual(8)
        ->and(max($counters))->toBeLessThanOrEqual(24);
});

test('a claim succeeds once per key', function () {
    expect($this->store->claim('captcha:used:abc', 60))->toBeTrue()
        ->and($this->store->claim('captcha:used:abc', 60))->toBeFalse()
        ->and($this->store->claim('captcha:used:def', 60))->toBeTrue();
});
