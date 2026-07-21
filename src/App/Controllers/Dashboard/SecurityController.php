<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use DateTime;
use DateTimeZone;
use Exception;
use Framework\Core\Response;

/**
 * Account credentials and sign-in activity.
 *
 * Kept apart from the cosmetic settings so the password form is reachable on
 * its own URL rather than buried among profile fields.
 */
class SecurityController extends AppController
{
    public function __construct(
        private UserModel $users,
        private UserPreferencesModel $prefs
    ) {}

    /**
     * Display the password form and recent sign-in details.
     */
    public function edit(): Response
    {
        $userId = (int) auth()->user()['id'];

        $user = $this->users->findById($userId);

        if (!$user) {
            throw new Exception("User record not found for ID {$userId}");
        }

        $preferences = $this->prefs->findOrCreate($userId) ?: [];

        $user['last_login'] = $this->formatLastLogin(
            $user['last_login'] ?? null,
            $preferences['timezone'] ?? 'UTC'
        );

        return $this->view([
            'user' => $user,
            'noBreadcrumb' => false,
        ]);
    }

    /**
     * Change the account password and rotate the session.
     */
    public function updatePassword(): Response
    {
        // enforce CSRF protection
        csrf()->assertValid($this->request->postParam('_token'));

        $userId = (int) auth()->user()['id'];

        $validator = $this->validateOrFail([
            'current_password' => 'required|password:basic|current_password',
            'new_password' => 'required|password:basic',
            'new_password_confirm' => 'required|password:basic|same:new_password',
        ], [
            'new_password.min' => 'Password must be at least 8 characters for security.',
            'new_password_confirm.same' => 'Password confirmation does not match.',
        ]);

        $validated = $validator->validated();

        // bcrypt handles salting automatically
        $newHash = password_hash($validated['new_password'], PASSWORD_DEFAULT);

        $success = $this->users->updatePasswordHashById($userId, $newHash);

        if (!$success) {
            // log database errors but show a generic message to the user
            error_log("Failed to update password for user {$userId}");

            $this->session->set('_errors', [
                'new_password' => ['Could not update password. Please try again.'],
            ]);

            $this->flash('error', 'Failed to update password. Please try again.');

            return $this->redirect('/dashboard/account/security');
        }

        // regenerate session ID after credential change to prevent session fixation
        $this->session->regenerate();

        $this->flash('success', 'Password updated successfully.');

        return $this->redirect('/dashboard/account/security');
    }

    /**
     * Format the last login timestamp in the user's timezone.
     *
     * Timestamps are stored in UTC. An unusable timezone falls back to UTC
     * rather than crashing the page.
     *
     * @return string|null Formatted datetime, or null if never logged in
     */
    private function formatLastLogin(?string $lastLogin, string $timezone): ?string
    {
        if (!$lastLogin) {
            return null;
        }

        try {
            $date = new DateTime($lastLogin, new DateTimeZone('UTC'));

            if (in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
                $date->setTimezone(new DateTimeZone($timezone));
            } else {
                error_log("Invalid timezone '{$timezone}' on the security page, using UTC");
            }

            return $date->format('M j, Y H:i');
        } catch (Exception $e) {
            error_log("Failed to parse last_login '{$lastLogin}': ".$e->getMessage());

            return null;
        }
    }
}
