<?php

declare(strict_types=1);

use Framework\Session;

beforeEach(function () {
    $_SESSION = [];
    $this->session = new Session();
});

afterEach(function () {
    $_SESSION = [];
});

// ==================== BASIC GET/SET/HAS ====================

it('sets and gets string values', function () {
    $key = $this->faker->word();
    $value = $this->faker->sentence();

    $this->session->set($key, $value);

    expect($this->session->get($key))->toBe($value);
});

it('sets and gets various data types', function () {
    /** @var Framework\Session $session */
    $this->session->set('string', $this->faker->word());
    $this->session->set('integer', 42);
    $this->session->set('float', 3.14);
    $this->session->set('boolean', true);
    $this->session->set('array', ['nested' => 'data']);
    $this->session->set('null', null);

    expect($this->session->get('string'))->toBeString()
        ->and($this->session->get('integer'))->toBe(42)
        ->and($this->session->get('float'))->toBe(3.14)
        ->and($this->session->get('boolean'))->toBeTrue()
        ->and($this->session->get('array'))->toBe(['nested' => 'data'])
        ->and($this->session->get('null'))->toBeNull();
});

it('returns default value for missing keys', function () {
    $defaultValue = $this->faker->word();

    expect($this->session->get('nonexistent', $defaultValue))->toBe($defaultValue);
});

it('returns null as default when key is missing and no default provided', function () {
    expect($this->session->get('missing'))->toBeNull();
});

it('detects existing keys', function () {
    $key = $this->faker->word();
    $this->session->set($key, $this->faker->sentence());

    expect($this->session->has($key))->toBeTrue()
        ->and($this->session->has('nonexistent'))->toBeFalse();
});

it('detects keys with falsy values', function () {
    $this->session->set('zero', 0);
    $this->session->set('empty_string', '');
    $this->session->set('false', false);
    $this->session->set('null', null);

    expect($this->session->has('zero'))->toBeTrue()
        ->and($this->session->has('empty_string'))->toBeTrue()
        ->and($this->session->has('false'))->toBeTrue()
        ->and($this->session->has('null'))->toBeTrue();
});

// ==================== REMOVE/DELETE ====================

it('removes values', function () {
    $key = $this->faker->word();
    $this->session->set($key, $this->faker->sentence());

    expect($this->session->has($key))->toBeTrue();

    $this->session->remove($key);

    expect($this->session->has($key))->toBeFalse();
});

it('deletes values using delete alias', function () {
    $key = $this->faker->word();
    $this->session->set($key, $this->faker->sentence());

    expect($this->session->has($key))->toBeTrue();

    $this->session->delete($key);

    expect($this->session->has($key))->toBeFalse();
});

it('handles removing non-existent keys gracefully', function () {
    $this->session->remove('nonexistent');

    expect(true)->toBeTrue();
});

// ==================== PULL (GET AND REMOVE) ====================

it('pulls value and removes it from session', function () {
    $key = $this->faker->word();
    $value = $this->faker->sentence();

    $this->session->set($key, $value);

    $pulledValue = $this->session->pull($key);

    expect($pulledValue)->toBe($value)
        ->and($this->session->has($key))->toBeFalse();
});

it('pulls with default value when key does not exist', function () {
    $defaultValue = $this->faker->word();

    $pulledValue = $this->session->pull('nonexistent', $defaultValue);

    expect($pulledValue)->toBe($defaultValue);
});

it('pulls null when key does not exist and no default provided', function () {
    expect($this->session->pull('nonexistent'))->toBeNull();
});

// ==================== CLEAR/DESTROY ====================

it('clears all session data', function () {
    $key1 = $this->faker->word();
    $key2 = $this->faker->word();

    $this->session->set($key1, $this->faker->sentence());
    $this->session->set($key2, $this->faker->sentence());

    $this->session->clear();

    expect($this->session->has($key1))->toBeFalse()
        ->and($this->session->has($key2))->toBeFalse()
        ->and($_SESSION)->toBeEmpty();
});

it('destroys session completely', function () {
    $key = $this->faker->word();
    $this->session->set($key, $this->faker->sentence());

    $this->session->destroy();

    expect($_SESSION)->toBeEmpty();
});

// ==================== ALL (GET ALL DATA) ====================

it('returns all session data', function () {
    $data = [
        'key1' => $this->faker->word(),
        'key2' => $this->faker->numberBetween(1, 100),
        'key3' => ['nested' => 'array'],
    ];

    foreach ($data as $key => $value) {
        $this->session->set($key, $value);
    }

    expect($this->session->all())->toBe($data);
});

it('returns empty array when session is empty', function () {
    expect($this->session->all())->toBeEmpty();
});

// ==================== SESSION ID & REGENERATION ====================

it('returns empty session ID in CLI mode', function () {
    expect($this->session->id())->toBe('');
});

it('reports session as enabled returns false in CLI mode', function () {
    expect($this->session->isEnabled())->toBeFalse();
});

it('preserves data when regenerate is called in CLI mode', function () {
    $key = $this->faker->word();
    $value = $this->faker->sentence();

    $this->session->set($key, $value);

    // Regeneration does nothing in CLI but doesn't throw errors
    $this->session->regenerate(true);

    // Data persists
    expect($this->session->get($key))->toBe($value);
});

// ==================== IS NOT ENABLED (CLI) ====================

it('reports session as disabled in CLI mode', function () {
    expect($this->session->isEnabled())->toBeFalse();
});

// ==================== FLASH MESSAGES (CORRECT LIFECYCLE) ====================

it('stores flash data in new flash bag', function () {
    $key = $this->faker->word();
    $value = $this->faker->sentence();

    $this->session->flash($key, $value);

    // Flash data stored with _flash_new prefix
    expect($_SESSION["_flash_new.{$key}"])->toBe($value);
});

it('retrieves flash data from new flash bag in same request', function () {
    $key = $this->faker->word();
    $value = $this->faker->sentence();

    $this->session->flash($key, $value);

    // Can retrieve immediately from new flash bag
    expect($this->session->getFlash($key))->toBe($value);
});

it('ages flash data from new to old', function () {
    $key = $this->faker->word();
    $value = $this->faker->sentence();

    $this->session->flash($key, $value);

    // Age flash data (simulate end of request)
    $this->session->ageFlashData();

    // Flash moved from _flash_new to _flash_old
    expect($_SESSION)->not->toHaveKey("_flash_new.{$key}")
        ->and($_SESSION)->toHaveKey("_flash_old.{$key}")
        ->and($_SESSION["_flash_old.{$key}"])->toBe($value);
});

it('retrieves flash data from old flash bag after aging', function () {
    $key = $this->faker->word();
    $value = $this->faker->sentence();

    $this->session->flash($key, $value);
    $this->session->ageFlashData();

    // Can still retrieve from old flash bag
    expect($this->session->getFlash($key))->toBe($value);
});

it('removes old flash data on second aging cycle', function () {
    $key = $this->faker->word();
    $value = $this->faker->sentence();

    // Request 1: Flash message
    $this->session->flash($key, $value);
    $this->session->ageFlashData(); // End of request 1

    // Request 2: Message available
    expect($this->session->getFlash($key))->toBe($value);

    $this->session->ageFlashData(); // End of request 2

    // Request 3: Message removed
    expect($this->session->getFlash($key))->toBeNull()
        ->and($_SESSION)->not->toHaveKey("_flash_old.{$key}");
});

it('handles multiple flash messages independently', function () {
    $key1 = 'success';
    $key2 = 'error';
    $value1 = $this->faker->sentence();
    $value2 = $this->faker->sentence();

    $this->session->flash($key1, $value1);
    $this->session->flash($key2, $value2);

    expect($this->session->getFlash($key1))->toBe($value1)
        ->and($this->session->getFlash($key2))->toBe($value2);
});

it('returns default value when flash key does not exist', function () {
    $defaultValue = $this->faker->word();

    expect($this->session->getFlash('nonexistent', $defaultValue))->toBe($defaultValue);
});

it('ages only flash data and preserves regular session data', function () {
    $regularKey = $this->faker->word();
    $regularValue = $this->faker->sentence();
    $flashKey = $this->faker->word();
    $flashValue = $this->faker->sentence();

    $this->session->set($regularKey, $regularValue);
    $this->session->flash($flashKey, $flashValue);

    $this->session->ageFlashData();

    // Regular data unaffected
    expect($this->session->get($regularKey))->toBe($regularValue)
        ->and($_SESSION[$regularKey])->toBe($regularValue);
});

// ==================== EDGE CASES ====================

it('handles session keys with special characters', function () {
    $specialKey = 'key_with-special.chars@123';
    $value = $this->faker->word();

    $this->session->set($specialKey, $value);

    expect($this->session->get($specialKey))->toBe($value);
});

it('stores XSS payloads without modification', function ($xssPayload) {
    $key = $this->faker->word();

    $this->session->set($key, $xssPayload);

    // Sanitization happens on output, not storage
    expect($this->session->get($key))->toBe($xssPayload);
})->with('xss_payloads');

it('handles unicode edge cases in session values', function ($unicodeValue) {
    $key = $this->faker->word();

    $this->session->set($key, $unicodeValue);

    expect($this->session->get($key))->toBe($unicodeValue);
})->with('unicode_edge_cases');
