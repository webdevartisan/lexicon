<?php

declare(strict_types=1);

use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\DisplayNameService;

beforeEach(function () {
    $this->service = new DisplayNameService(
        Mockery::mock(UserModel::class),
        Mockery::mock(UserPreferencesModel::class),
    );
});

afterEach(function () {
    Mockery::close();
});

test('showing the name gives the full name', function () {
    expect($this->service->compute('name', 'Ada', 'Lovelace', 'theboss'))->toBe('Ada Lovelace');
});

test('showing the name falls back to the handle when the name is blank', function () {
    expect($this->service->compute('name', '', '', 'theboss'))->toBe('theboss');
});

test('hiding the name gives the handle', function () {
    expect($this->service->compute('handle', 'Ada', 'Lovelace', 'theboss'))->toBe('theboss');
});
