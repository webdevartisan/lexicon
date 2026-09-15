<?php

declare(strict_types=1);

use App\Models\ReservedHandleModel;
use App\Models\UserModel;
use App\Services\UserHandleValidator;

beforeEach(function () {
    $reservedHandles = Mockery::mock(ReservedHandleModel::class);
    $reservedHandles->shouldReceive('matchTypesByHandle')->once()->andReturn([
        'me' => 'exact',
        'login' => 'exact',
        'staff' => 'exact',
        'admin' => 'contains',
        'support' => 'contains',
    ]);

    $this->users = Mockery::mock(UserModel::class);
    $this->validator = new UserHandleValidator($this->users, $reservedHandles);
});

afterEach(function () {
    Mockery::close();
});

test('an exact word is reserved on its own but not inside a longer handle', function () {
    expect($this->validator->isReserved('me'))->toBeTrue()
        ->and($this->validator->isReserved('james'))->toBeFalse()
        ->and($this->validator->isReserved('staffordshire'))->toBeFalse();
});

test('a contains word is reserved inside a longer handle', function () {
    expect($this->validator->isReserved('the-admin'))->toBeTrue()
        ->and($this->validator->isReserved('support-team'))->toBeTrue();
});

test('look-alike characters are judged as the letters they resemble', function () {
    expect($this->validator->isReserved('adm1n'))->toBeTrue()
        ->and($this->validator->isReserved('adrnin'))->toBeTrue()
        ->and($this->validator->isReserved('supp0rt'))->toBeTrue()
        ->and($this->validator->isReserved('l0gin'))->toBeTrue()
        ->and($this->validator->isReserved('st4ff'))->toBeTrue();
});

test('names a few edits away from a reserved word stay available', function () {
    expect($this->validator->isReserved('adrian'))->toBeFalse()
        ->and($this->validator->isReserved('amin'))->toBeFalse()
        ->and($this->validator->isReserved('sport'))->toBeFalse();
});

test('a reserved handle is unavailable without asking whether it is taken', function () {
    $this->users->shouldReceive('isHandleUnique')->never();

    expect($this->validator->isAvailable('admin'))->toBeFalse();
});

test('an unreserved handle is available only when nobody holds it', function () {
    $this->users->shouldReceive('isHandleUnique')->with('james', 7)->andReturn(false);
    $this->users->shouldReceive('isHandleUnique')->with('carmen', 7)->andReturn(true);

    expect($this->validator->isAvailable('james', 7))->toBeFalse()
        ->and($this->validator->isAvailable('carmen', 7))->toBeTrue();
});
