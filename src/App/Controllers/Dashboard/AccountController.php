<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\DisplayNameService;
use App\Services\LocaleRegistry;
use App\Services\SessionLocaleSync;
use DateTimeZone;
use Exception;
use Framework\Core\Response;

/**
 * Private account settings: email, posting defaults, timezone, interface
 * language, email notifications.
 *
 * Public identity lives in ProfileController and credentials in SecurityController.
 */
class AccountController extends AppController
{
    public function __construct(
        private UserModel $users,
        private UserPreferencesModel $prefs,
        private DisplayNameService $displayNames,
        private LocaleRegistry $locales,
        private SessionLocaleSync $localeSync
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
            'locales' => $this->localeOptions(),
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
            // Built from the registry so adding a locale never means editing this file.
            'locale' => 'in:auto,'.implode(',', $this->locales->supported()),
        ], [
            'email.unique' => 'This email address is already in use.',
            'timezone.timezone' => 'Please select a valid timezone.',
            'locale.in' => 'Please choose a language from the list.',
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
            // "auto" is the absence of a preference, stored as NULL so the chrome
            // locale keeps following whatever language the page itself is in.
            'locale' => ($validated['locale'] ?? 'auto') === 'auto' ? null : $validated['locale'],
        ]);

        // the display preference decides whether the cached name is the real name or the handle
        $this->displayNames->refreshCached($userId);

        // Picking a language here has to move the URL too, or the redirect below
        // lands back on the locale the visitor was already on and the choice
        // looks like it did nothing.
        $this->localeSync->apply($userId);

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

        // unchecked boxes are absent from the request, so absence means off.
        // Driving this off NOTIFY_KEYS means adding a toggle is a one-line
        // change to the model, not another entry to remember here.
        $data = [];
        foreach (UserPreferencesModel::NOTIFY_KEYS as $key) {
            $data[$key] = $this->request->postParam($key) ? 1 : 0;
        }

        $this->prefs->upsert($userId, $data);

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
        $merged['locale'] = $preferences['locale'] ?? 'auto';

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
        foreach (UserPreferencesModel::NOTIFY_KEYS as $key) {
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

    /**
     * Language options for the account form, each named in its own language.
     *
     * @return array<string, string> Locale code => label, led by the "auto" entry
     */
    private function localeOptions(): array
    {
        $options = ['auto' => 'Automatic (follow page language)'];

        foreach ($this->locales->supported() as $code) {
            $options[$code] = $this->locales->nativeName($code);
        }

        return $options;
    }
}
