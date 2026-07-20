<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\DisplayNameService;
use DateTimeZone;
use Exception;
use Framework\Core\Response;

/**
 * Private account settings: email, posting defaults, timezone, email notifications.
 *
 * Public identity lives in ProfileController and credentials in SecurityController.
 */
class AccountController extends AppController
{
    public function __construct(
        private UserModel $users,
        private UserPreferencesModel $prefs,
        private DisplayNameService $displayNames
    ) {}

    /**
     * Display the account preferences form.
     */
    public function edit(): Response
    {
        $userId = (int) auth()->user()['id'];

        return $this->view([
            'user' => $this->loadAccount($userId),
            'timezones' => $this->getGroupedTimezones(),
            'noBreadcrumb' => false,
        ]);
    }

    /**
     * Persist email address and posting preferences.
     */
    public function update(): Response
    {
        // enforce CSRF protection
        csrf()->assertValid($this->request->postParam('_token'));

        $userId = (int) auth()->user()['id'];

        $validator = $this->validateOrFail([
            'email' => 'required|email|unique:users,email,'.$userId,
            'display_name' => 'in:name,username',
            'default_visibility' => 'in:public,private,unlisted',
            'timezone' => 'timezone',
        ], [
            'email.unique' => 'This email address is already in use.',
            'timezone.timezone' => 'Please select a valid timezone.',
        ]);

        $validated = $validator->validated();

        $userUpdate = changedFields([
            'email' => $validated['email'] ?? '',
        ], auth()->user());

        if (!empty($userUpdate)) {
            $this->users->updateById($userId, $userUpdate);
        }

        $this->prefs->upsert($userId, [
            'display_name_preference' => $validated['display_name'] ?? 'username',
            'default_post_visibility' => $validated['default_visibility'] ?? 'public',
            'timezone' => $validated['timezone'] ?? null,
        ]);

        // the display preference decides whether the cached name is the real name or the handle
        $this->displayNames->refreshCached($userId);

        $this->flash('success', 'Account settings saved.');

        return $this->redirect('/dashboard/account');
    }

    /**
     * Display the email notification preferences.
     */
    public function notifications(): Response
    {
        $userId = (int) auth()->user()['id'];

        return $this->view([
            'user' => $this->loadNotificationPrefs($userId),
            'noBreadcrumb' => false,
        ]);
    }

    /**
     * Save email notification preferences.
     */
    public function updateNotifications(): Response
    {
        // enforce CSRF protection
        csrf()->assertValid($this->request->postParam('_token'));

        $userId = (int) auth()->user()['id'];

        // unchecked boxes are absent from the request, so absence means off
        $this->prefs->upsert($userId, [
            'notify_comments' => $this->request->postParam('notify_comments') ? 1 : 0,
            'notify_likes' => $this->request->postParam('notify_likes') ? 1 : 0,
            'notify_post_status' => $this->request->postParam('notify_post_status') ? 1 : 0,
            'notify_role_changes' => $this->request->postParam('notify_role_changes') ? 1 : 0,
            'notify_invites' => $this->request->postParam('notify_invites') ? 1 : 0,
        ]);

        $this->flash('success', 'Notification settings saved.');

        return $this->redirect('/dashboard/account/notifications');
    }

    /**
     * Load the account identity fields and posting preferences.
     *
     * @return array<string, mixed>
     */
    private function loadAccount(int $userId): array
    {
        $user = $this->users->findById($userId);

        if (!$user) {
            throw new Exception("User record not found for ID {$userId}");
        }

        $preferences = $this->prefs->findOrCreate($userId) ?: [];
        $merged = array_merge($user, $preferences);

        $merged['display_name'] = $preferences['display_name_preference'] ?? 'username';
        $merged['default_visibility'] = $preferences['default_post_visibility'] ?? 'public';
        $merged['timezone'] = $preferences['timezone'] ?? 'UTC';

        // the delete-account modal spells out what deletion will affect
        // denormalized counters are unreliable (often stale at 0), so recount when empty
        $merged['post_count'] = !empty($merged['posts_count'])
            ? (int) $merged['posts_count']
            : $this->users->countPosts($userId);
        $merged['comment_count'] = !empty($merged['comments_received_count'])
            ? (int) $merged['comments_received_count']
            : $this->users->countCommentsReceived($userId);

        return $merged;
    }

    /**
     * Load the notification toggles, defaulting each to enabled.
     *
     * @return array<string, mixed>
     */
    private function loadNotificationPrefs(int $userId): array
    {
        $preferences = $this->prefs->findOrCreate($userId) ?: [];

        $toggles = [];
        foreach (['notify_comments', 'notify_likes', 'notify_post_status', 'notify_role_changes', 'notify_invites'] as $key) {
            $toggles[$key] = isset($preferences[$key]) ? (int) $preferences[$key] : 1;
        }

        return $toggles;
    }

    /**
     * Get timezones grouped by region for the select dropdown.
     *
     * Groups identifiers like "America/New_York" by their region prefix so the
     * select can render optgroups.
     *
     * TODO: Cache this result as it's expensive and static.
     *
     * @return array<string, string[]>
     */
    private function getGroupedTimezones(): array
    {
        $zones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
        $grouped = [];

        foreach ($zones as $zone) {
            $parts = explode('/', $zone, 2);

            // deprecated zones such as "UTC" have no region prefix
            if (count($parts) === 1) {
                $grouped['Other'][] = $zone;
                continue;
            }

            $grouped[$parts[0]][] = $zone;
        }

        return $grouped;
    }
}
