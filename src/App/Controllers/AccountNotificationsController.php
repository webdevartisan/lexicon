<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserPreferencesModel;
use App\Services\NotificationPreferenceScope;
use Framework\Core\Response;

/**
 * Email notification toggles on the front.
 *
 * These settings gate email only; in-app notifications are never turned off by
 * them. A single comment emails at most once, under the most personal reason
 * that fits, so a lower-precedence toggle turned off may not stop an email a
 * higher one still sends. The page explains that; the controller keeps the
 * toggles honest.
 */
final class AccountNotificationsController extends AppController
{
    public function __construct(
        private UserPreferencesModel $prefs,
        private NotificationPreferenceScope $scope
    ) {}

    /**
     * Display the notification toggles.
     */
    public function edit(): Response
    {
        $userId = (int) auth()->user()['id'];
        $applicable = $this->scope->applicableKeys($userId);

        return $this->view('public.Account.notifications', [
            'toggles' => $this->loadToggles($userId, $applicable),
            'applicable' => $applicable,
        ]);
    }

    /**
     * Save the notification toggles.
     */
    public function update(): Response
    {
        // enforce CSRF protection
        csrf()->assertValid($this->request->postParam('_token'));

        $userId = (int) auth()->user()['id'];

        // Unchecked boxes are absent from the request, so absence means off. The
        // upsert leaves absent columns unchanged, so a toggle can only be turned
        // off by writing 0 for it explicitly. Iterate ONLY the keys this user was
        // actually shown: a toggle hidden as irrelevant is absent from the POST
        // too, and writing 0 for it would silently disable a notification the user
        // would want the day they gain the role. Untouched keys keep their default.
        $data = [];
        foreach ($this->scope->applicableKeys($userId) as $key) {
            $data[$key] = $this->request->postParam($key) ? 1 : 0;
        }

        $this->prefs->upsert($userId, $data);

        $this->flash('success', chrome_translate('account.flash.notificationsSaved'));

        return $this->redirect(lurl('/account/notifications'));
    }

    /**
     * Load the applicable toggles, defaulting each to enabled when no row exists.
     *
     * @param  string[]  $applicable  Keys this user should see
     * @return array<string, int>
     */
    private function loadToggles(int $userId, array $applicable): array
    {
        $preferences = $this->prefs->findOrCreate($userId) ?: [];

        $toggles = [];
        foreach ($applicable as $key) {
            $toggles[$key] = isset($preferences[$key]) ? (int) $preferences[$key] : 1;
        }

        return $toggles;
    }
}
