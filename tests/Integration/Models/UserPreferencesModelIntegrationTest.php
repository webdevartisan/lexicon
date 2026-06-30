<?php

declare(strict_types=1);

use App\Models\UserPreferencesModel;
use App\Models\UserModel;
use Tests\Factories\UserFactory;

/**
 * Integration tests for UserPreferencesModel notification preference columns.
 *
 * Covers the three new columns added in the 2026-06-24 migration:
 * notify_post_status, notify_role_changes, notify_invites.
 */
beforeEach(function () {
    $this->userModel = new UserModel($this->db);
    $this->prefs     = new UserPreferencesModel($this->db);
    $this->userId    = UserFactory::new($this->userModel)->create();
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
        'notify_post_status'  => 0,
        'notify_role_changes' => 1,
        'notify_invites'      => 0,
    ]);

    $row = $this->prefs->findOrCreate($this->userId);

    expect((int) $row['notify_post_status'])->toBe(0)
        ->and((int) $row['notify_role_changes'])->toBe(1)
        ->and((int) $row['notify_invites'])->toBe(0);
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
