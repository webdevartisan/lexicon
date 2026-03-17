<?php

declare(strict_types=1);

use Tests\Helpers\AuthTestHelper;

it('authenticates with valid credentials using mocked dependencies', function () {
    $mocks = AuthTestHelper::createMockedAuth();
    $email = $this->faker->email();
    $password = $this->faker->password(12);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $userData = AuthTestHelper::mockUserData([
        'id' => 42,
        'email' => $email,
        'password' => $hashedPassword,
    ]);

    $mocks['userModel']
        ->shouldReceive('findByEmail')
        ->once()
        ->with($email)
        ->andReturn($userData);

    // Mock session regeneration for security
    $mocks['session']
        ->shouldReceive('regenerate')
        ->once()
        ->with(true);

    $mocks['session']
        ->shouldReceive('set')
        ->once()
        ->with('user_id', 42);

    // Mock last_login update
    $mocks['userModel']
        ->shouldReceive('updateById')
        ->once()
        ->with(42, Mockery::type('array'));

    $mocks['session']
        ->shouldReceive('get')
        ->with('user_id')
        ->andReturn(42);

    $result = $mocks['auth']->login($email, $password);

    expect($result)->toBeTrue();
});

it('rejects invalid password using mocked dependencies', function () {
    $mocks = AuthTestHelper::createMockedAuth();
    $email = $this->faker->email();
    $correctPassword = $this->faker->password(12);
    $wrongPassword = $this->faker->password(12);

    $userData = AuthTestHelper::mockUserData([
        'email' => $email,
        'password' => password_hash($correctPassword, PASSWORD_DEFAULT),
    ]);

    $mocks['userModel']
        ->shouldReceive('findByEmail')
        ->once()
        ->with($email)
        ->andReturn($userData);

    // Should NOT call session methods when password is wrong
    $mocks['session']
        ->shouldNotReceive('regenerate');

    $mocks['session']
        ->shouldNotReceive('set');

    $mocks['userModel']
        ->shouldNotReceive('updateById');

    $result = $mocks['auth']->login($email, $wrongPassword);

    expect($result)->toBeFalse();
});

it('rejects non-existent user using mocked dependencies', function () {
    $mocks = AuthTestHelper::createMockedAuth();
    $email = $this->faker->email();
    $password = $this->faker->password(12);

    // Return null for non-existent user
    $mocks['userModel']
        ->shouldReceive('findByEmail')
        ->once()
        ->with($email)
        ->andReturn(null);

    $mocks['session']
        ->shouldNotReceive('regenerate');

    $mocks['session']
        ->shouldNotReceive('set');

    $mocks['userModel']
        ->shouldNotReceive('updateById');

    $result = $mocks['auth']->login($email, $password);

    expect($result)->toBeFalse();
});

it('returns authenticated user data from session', function () {
    $mocks = AuthTestHelper::createMockedAuth();
    $email = $this->faker->email();
    $userData = AuthTestHelper::mockUserData(['id' => 99, 'email' => $email]);

    $mocks['session']
        ->shouldReceive('get')
        ->with('user_id')
        ->andReturn(99);

    $mocks['userModel']
        ->shouldReceive('find')
        ->once()
        ->with(99)
        ->andReturn($userData);

    // Mock role/permission loading
    $mocks['userModel']
        ->shouldReceive('getUserRoles')
        ->once()
        ->with(99)
        ->andReturn(['roles' => [1, 2]]);

    $mocks['userModel']
        ->shouldReceive('getUserPermissions')
        ->once()
        ->with(99)
        ->andReturn(['permissions' => ['read', 'write']]);

    // Mock profile avatar loading
    $mocks['profileModel']
        ->shouldReceive('getProfileAvatar')
        ->once()
        ->with(99)
        ->andReturn(['avatar' => 'avatar.jpg']);

    $result = $mocks['auth']->user();

    expect($result)->toBeArray()
        ->and($result['email'])->toBe($email);
});

it('checks that unauthenticated user returns false', function () {
    $mocks = AuthTestHelper::createMockedAuth();

    $mocks['session']
        ->shouldReceive('get')
        ->with('user_id')
        ->andReturn(null);

    expect($mocks['auth']->check())->toBeFalse();
});

it('checks that authenticated user returns true', function () {
    $mocks = AuthTestHelper::createMockedAuth();

    $mocks['session']
        ->shouldReceive('get')
        ->with('user_id')
        ->andReturn(123);

    // check() calls user() internally, which needs these mocks
    $mocks['userModel']
        ->shouldReceive('find')
        ->once()
        ->with(123)
        ->andReturn(AuthTestHelper::mockUserData(['id' => 123]));

    $mocks['userModel']
        ->shouldReceive('getUserRoles')
        ->once()
        ->with(123)
        ->andReturn(['roles' => []]);

    $mocks['userModel']
        ->shouldReceive('getUserPermissions')
        ->once()
        ->with(123)
        ->andReturn(['permissions' => []]);

    $mocks['profileModel']
        ->shouldReceive('getProfileAvatar')
        ->once()
        ->with(123)
        ->andReturn(['avatar' => null]);

    expect($mocks['auth']->check())->toBeTrue();
});

it('clears session on logout and user is no longer authenticated', function () {
    $mocks = AuthTestHelper::createMockedAuth();

    $mocks['session']
        ->shouldReceive('remove')
        ->once()
        ->with('user_id');

    $mocks['session']
        ->shouldReceive('regenerate')
        ->once()
        ->with(true);

    // After logout, check() should return false
    $mocks['session']
        ->shouldReceive('get')
        ->with('user_id')
        ->andReturn(null);

    $mocks['auth']->logout();

    // Verify user is no longer authenticated
    expect($mocks['auth']->check())->toBeFalse();
});
