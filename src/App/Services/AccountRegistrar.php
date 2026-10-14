<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth;
use App\Mail\Mailable;
use App\Mail\WelcomeEmail;
use App\Models\RoleModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Models\UserProfileModel;
use App\Services\Analytics\SignupAttribution;
use App\Services\Analytics\VisitorLink;
use Exception;
use Framework\Core\Request;

/**
 * Creates a reader account and signs it in.
 *
 * Shared by the registration form and the invitation wizard so both produce the
 * same account: reader role, public profile, default preferences, a derived
 * handle and a queued welcome email.
 */
final class AccountRegistrar
{
    public function __construct(
        private Auth $auth,
        private UserModel $users,
        private UserProfileModel $profiles,
        private UserPreferencesModel $preferences,
        private UserHandleValidator $handleValidator,
        private RoleModel $roles,
    ) {}

    /**
     * Create the account, then sign the visitor in as it.
     *
     * @param  string  $email  Already validated, unique address
     * @param  string  $password  Already validated plain password
     * @param  Request  $request  The sign-up request, for attribution and visitor linking
     * @return int|null The new user id, or null when the insert failed
     */
    public function register(string $email, string $password, Request $request): ?int
    {
        $handle = $this->deriveHandle($email);

        $userId = $this->users->insert([
            'email' => $email,
            'handle' => $handle,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'age_confirmed_at' => gmdate('Y-m-d H:i:s'),
        ]);

        if (!$userId) {
            return null;
        }

        // New accounts are readers; creating a blog later upgrades them
        $readerRole = $this->roles->findBySlug('reader');
        if ($readerRole !== null) {
            $this->users->insertUserRoles($userId, [(int) $readerRole['id']]);
        }

        $this->profiles->upsert($userId, [
            'is_public' => 1, // default new profiles to public
        ]);

        $this->preferences->findOrCreate($userId);

        // queued, so a transport problem does not cost the welcome email
        try {
            // In the language they signed up in.
            $welcome = Mailable::inLocale(app(RecipientLocale::class)->current(), fn (): WelcomeEmail => new WelcomeEmail([
                'email' => $email,
                'handle' => $handle,
            ]));
            mail_queue()->enqueue($welcome, 'user', $userId);
        } catch (Exception $e) {
            // queueing is a single insert, so this only fires if the database
            // is in trouble. Registration itself already succeeded either way.
            error_log('Failed to queue welcome email: '.$e->getMessage());
        }

        // Before signing in, which can move this visit's views onto the account.
        try {
            app(SignupAttribution::class)->record($request);
        } catch (Exception $e) {
            error_log('Failed to note where a sign-up came from: '.$e->getMessage());
        }

        $this->auth->login($email, $password);
        app(VisitorLink::class)->toAccount($request, (int) $userId);

        return (int) $userId;
    }

    /**
     * Build the account handle from the email local part, reduced to letters and digits.
     *
     * A taken local part gets a short random suffix. One that is too short or
     * reserved starts from "reader" instead, since suffixing support@ still reads as support.
     *
     * @param  string  $email  The registration email
     * @return string An available handle
     */
    private function deriveHandle(string $email): string
    {
        $localPart = strtolower(explode('@', $email, 2)[0]);
        $base = substr(preg_replace('/[^a-z0-9]/', '', $localPart), 0, 20);

        $usableBase = strlen($base) >= 3 && !$this->handleValidator->isReserved($base);

        if ($usableBase && !$this->handleValidator->isTaken($base)) {
            return $base;
        }

        $stem = $usableBase ? substr($base, 0, 15) : 'reader';

        do {
            $candidate = $stem.bin2hex(random_bytes(2));
        } while (!$this->handleValidator->isAvailable($candidate));

        return $candidate;
    }
}
