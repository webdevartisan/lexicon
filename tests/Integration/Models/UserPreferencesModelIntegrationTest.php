<?php

declare(strict_types=1);

use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use Tests\Factories\UserFactory;

/**
 * Integration tests for UserPreferencesModel.
 *
 * Covers the three notification columns added in the 2026-06-24 migration
 * (notify_post_status, notify_role_changes, notify_invites) and the locale
 * column added on 2026-07-23, including the rule that binds them all: upsert()
 * writes only the keys it is given, so one form cannot blank another's fields.
 */
beforeEach(function () {
    $this->userModel = new UserModel($this->db);
    $this->prefs = new UserPreferencesModel($this->db);
    $this->userId = UserFactory::new($this->userModel)->create();
});

test('findOrCreate returns row with new notification preference columns', function () {
    $row = $this->prefs->findOrCreate($this->userId);

    expect($row)->toHaveKey('notify_post_status')
        ->and($row)->toHaveKey('notify_role_changes')
        ->and($row)->toHaveKey('notify_invites');

    // Defaults are TRUE
    expect((int) $row['notify_post_status'])->toBe(1)
        ->and((int) $row['notify_role_changes'])->toBe(1)
        ->and((int) $row['notify_invites'])->toBe(1);
});

test('upsert persists new notification preference values', function () {
    $this->prefs->upsert($this->userId, [
        'notify_post_status' => 0,
        'notify_role_changes' => 1,
        'notify_invites' => 0,
    ]);

    $row = $this->prefs->findOrCreate($this->userId);

    expect((int) $row['notify_post_status'])->toBe(0)
        ->and((int) $row['notify_role_changes'])->toBe(1)
        ->and((int) $row['notify_invites'])->toBe(0);
});

test('upsert leaves columns it was not given untouched', function () {
    // seed the profile-form columns the way the profile page would
    $this->prefs->upsert($this->userId, [
        'display_name_preference' => 'name',
        'default_post_visibility' => 'private',
        'timezone' => 'UTC',
    ]);

    // now save only the notification toggles, as the notifications form does
    $this->prefs->upsert($this->userId, [
        'notify_comments_blog' => 0,
        'notify_post_status' => 0,
        'notify_role_changes' => 0,
        'notify_invites' => 0,
    ]);

    $row = $this->prefs->findOrCreate($this->userId);

    // the toggles were saved
    expect((int) $row['notify_comments_blog'])->toBe(0)
        ->and((int) $row['notify_invites'])->toBe(0);

    // and the untouched columns kept their values instead of reverting to defaults
    expect($row['display_name_preference'])->toBe('name')
        ->and($row['default_post_visibility'])->toBe('private')
        ->and($row['timezone'])->toBe('UTC');
});

test('upsert applies schema defaults when inserting a brand new row', function () {
    $freshId = UserFactory::new($this->userModel)->create();
    $this->db->execute('DELETE FROM user_preferences WHERE user_id = ?', [$freshId]);

    $this->prefs->upsert($freshId, ['timezone' => 'Europe/Athens']);

    $row = $this->prefs->findOrCreate($freshId);

    expect($row['timezone'])->toBe('Europe/Athens')
        ->and($row['display_name_preference'])->toBe('username')
        ->and($row['default_post_visibility'])->toBe('public')
        ->and((int) $row['notify_comments_blog'])->toBe(1);
});

test('notificationPreference returns boolean for the requested key', function () {
    $this->prefs->upsert($this->userId, ['notify_invites' => 0]);

    expect($this->prefs->notificationPreference($this->userId, 'notify_invites'))->toBeFalse()
        ->and($this->prefs->notificationPreference($this->userId, 'notify_post_status'))->toBeTrue();
});

test('notificationPreference defaults true when no row exists', function () {
    // Create a fresh user that has no preferences row yet
    $orphanId = UserFactory::new($this->userModel)->create();

    // Remove the auto-created row if findOrCreate ran during the factory
    $this->db->execute('DELETE FROM user_preferences WHERE user_id = ?', [$orphanId]);

    expect($this->prefs->notificationPreference($orphanId, 'notify_post_status'))->toBeTrue();
});

test('notificationPreference rejects unknown keys', function () {
    expect(fn () => $this->prefs->notificationPreference($this->userId, 'notify_bogus'))
        ->toThrow(\InvalidArgumentException::class);
});

test('a stored interface language round-trips', function () {
    $this->prefs->upsert($this->userId, ['locale' => 'el']);

    expect($this->prefs->getLocale($this->userId))->toBe('el');
});

test('no stored preference reads as null rather than an empty string', function () {
    $this->prefs->upsert($this->userId, ['timezone' => 'Europe/Athens']);

    expect($this->prefs->getLocale($this->userId))->toBeNull();
});

/**
 * The notifications form and the profile form post disjoint field sets, so
 * saving one must not blank the other's columns.
 */
test('saving notifications leaves the interface language alone', function () {
    $this->prefs->upsert($this->userId, ['locale' => 'ar']);

    $this->prefs->upsert($this->userId, ['notify_invites' => 0]);

    expect($this->prefs->getLocale($this->userId))->toBe('ar');
});
