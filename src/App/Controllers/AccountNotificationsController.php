<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserPreferencesModel;
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
    public function __construct(private UserPreferencesModel $prefs) {}

    /**
     * Display the notification toggles.
     */
    public function edit(): Response
    {
        $userId = (int) auth()->user()['id'];

        return $this->view('public.Account.notifications', [
            'toggles' => $this->loadToggles($userId),
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
        // off by writing 0 for it explicitly. Driving this off NOTIFY_KEYS is
        // what holds the feature up: skip the loop and unticking stops working.
        $data = [];
        foreach (UserPreferencesModel::NOTIFY_KEYS as $key) {
            $data[$key] = $this->request->postParam($key) ? 1 : 0;
        }

        $this->prefs->upsert($userId, $data);

        $this->flash('success', chrome_translate('account.flash.notificationsSaved'));

        return $this->redirect(lurl('/account/notifications'));
    }

    /**
     * Load the toggles, defaulting each to enabled when no row exists yet.
     *
     * @return array<string, int>
     */
    private function loadToggles(int $userId): array
    {
        $preferences = $this->prefs->findOrCreate($userId) ?: [];

        $toggles = [];
        foreach (UserPreferencesModel::NOTIFY_KEYS as $key) {
            $toggles[$key] = isset($preferences[$key]) ? (int) $preferences[$key] : 1;
        }

        return $toggles;
    }
}
