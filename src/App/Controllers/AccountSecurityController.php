<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\PasswordConfirmRateLimiter;
use DateTime;
use DateTimeZone;
use Exception;
use Framework\Core\Response;

/**
 * Account credentials on the front: the password, plus the last-login stamp.
 *
 * The current-password check is throttled, so a stolen session is not an
 * unlimited password oracle, and the session id is rotated after a successful
 * change to close session fixation. The last-login value is formatted for
 * display, never compared against PHP's clock: the database runs at GMT+3 while
 * PHP runs at UTC, so a comparison would be three hours out.
 */
final class AccountSecurityController extends AppController
{
    public function __construct(
        private UserModel $users,
        private UserPreferencesModel $prefs,
        private PasswordConfirmRateLimiter $passwordThrottle
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

        return $this->view('public.Account.security', [
            'user' => $user,
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

        // Throttle the current-password check and verify it explicitly, before
        // the new-password rules, so a refused attempt is indistinguishable in
        // timing and wording from any other validation failure on this form and
        // the throttle can observe the miss (validateOrFail would redirect first).
        if ($this->passwordThrottle->tooManyAttempts($userId)) {
            return $this->rejectCurrentPassword();
        }

        $current = (string) $this->request->postParam('current_password');
        if ($current === '' || !$this->users->verifyPassword($userId, $current)) {
            $this->passwordThrottle->hit($userId);

            return $this->rejectCurrentPassword();
        }

        $this->passwordThrottle->clear($userId);

        $validator = $this->validateOrFail([
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
            error_log("Failed to update password for user {$userId}");
            $this->session->set('_errors', [
                'new_password' => [chrome_translate('account.flash.passwordError')],
            ]);
            $this->flash('error', chrome_translate('account.flash.passwordError'));

            return $this->redirect(lurl('/account/security'));
        }

        // regenerate session ID after credential change to prevent session fixation
        $this->session->regenerate();

        $this->flash('success', chrome_translate('account.flash.passwordUpdated'));

        return $this->redirect(lurl('/account/security'));
    }

    /**
     * Refuse the current-password check with wording generic enough that the
     * field is not a password oracle with a nicer error message.
     */
    private function rejectCurrentPassword(): Response
    {
        $this->session->set('_errors', [
            'current_password' => [chrome_translate('account.flash.preferencesUnchanged')],
        ]);
        $this->flash('error', chrome_translate('account.flash.preferencesUnchanged'));

        return $this->redirect(lurl('/account/security'));
    }

    /**
     * Format the last login timestamp in the user's timezone.
     *
     * Timestamps are stored in UTC. An unusable timezone falls back to UTC
     * rather than crashing the page. This formats; it never compares.
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
