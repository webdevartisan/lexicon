<?php

declare(strict_types=1);

use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\AccountZone;
use Framework\Session;
use Tests\Factories\UserFactory;

/**
 * A picked zone wins, an account on Automatic follows its device, and the
 * site's zone covers the time before the device has said.
 */
beforeEach(function () {
    $_SESSION = [];
    $this->prefs = new UserPreferencesModel($this->db);
    $this->userId = UserFactory::new(new UserModel($this->db))->create();
    $this->zones = new AccountZone($this->prefs, new Session());
});

afterEach(function () {
    $_SESSION = [];
});

it('uses the zone the account picked, whatever the device says', function () {
    $this->prefs->upsert($this->userId, ['timezone' => 'Europe/Athens']);
    $this->zones->rememberDevice('America/New_York');

    expect($this->zones->resolve($this->userId))->toBe('Europe/Athens')
        ->and($this->zones->chosen($this->userId))->toBe('Europe/Athens');
});

it('follows the device when the account is on Automatic', function () {
    $this->zones->rememberDevice('America/New_York');

    expect($this->zones->chosen($this->userId))->toBeNull()
        ->and($this->zones->resolve($this->userId))->toBe('America/New_York');
});

it('falls back to the site zone until the device has reported', function () {
    expect($this->zones->device())->toBeNull()
        ->and($this->zones->resolve($this->userId))->toBe(site_timezone());
});

it('keeps the name the browser sent, even when it is used under another or not at all', function () {
    $this->zones->rememberDevice('Asia/Calcutta');
    expect($this->zones->reportedDevice())->toBe('Asia/Calcutta')
        ->and($this->zones->device())->toBe('Asia/Kolkata');

    $this->zones->rememberDevice('Somewhere/Else');
    expect($this->zones->reportedDevice())->toBe('Somewhere/Else')
        ->and($this->zones->device())->toBeNull();
});

it('ignores a stored zone PHP no longer knows', function () {
    $this->prefs->upsert($this->userId, ['timezone' => 'Mars/Olympus']);
    $this->zones->rememberDevice('Asia/Tokyo');

    expect($this->zones->resolve($this->userId))->toBe('Asia/Tokyo');
});
