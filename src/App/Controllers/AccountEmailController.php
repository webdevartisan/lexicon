<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Mail\EmailChangedMail;
use App\Mail\EmailChangeVerificationMail;
use App\Models\PendingEmailChangeModel;
use App\Models\UserModel;
use App\Services\MailQueueService;
use App\Services\PasswordConfirmRateLimiter;
use Framework\Core\Response;

/**
 * Changing the address an account signs in and recovers with.
 *
 * Two gates, because either one alone leaves a hole. The current password
 * proves the person at the keyboard owns the account, which stops a borrowed
 * session from moving the address; the emailed token proves they own the inbox
 * they are moving to, which stops a typo or a hostile address from taking over
 * account recovery. Nothing is written to users.email until the token comes
 * back, so a request that is never confirmed leaves the account exactly as it
 * was.
 *
 * The password check is throttled, or a stolen session would be an unlimited
 * oracle for confirming that password.
 */
final class AccountEmailController extends AppController
{
    /** How long a confirmation link stays usable. */
    private const TOKEN_TTL_MINUTES = 60;

    public function __construct(
        private UserModel $users,
        private PendingEmailChangeModel $pending,
        private PasswordConfirmRateLimiter $passwordThrottle,
        private MailQueueService $mailQueue
    ) {}

    /**
     * Start a change: verify the password, then mail a token to the new address.
     *
     * Named requestChange rather than request because BaseController::request()
     * already exists and returns the Request object.
     */
    public function requestChange(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $userId = (int) auth()->user()['id'];
        $currentEmail = (string) (auth()->user()['email'] ?? '');

        if ($this->passwordThrottle->tooManyAttempts($userId)) {
            return $this->reject(chrome_translate('account.flash.emailChangeThrottled'));
        }

        $password = (string) $this->request->postParam('current_password');
        if ($password === '' || !$this->users->verifyPassword($userId, $password)) {
            $this->passwordThrottle->hit($userId);

            return $this->reject(chrome_translate('account.flash.emailChangeBadPassword'));
        }

        $this->passwordThrottle->clear($userId);

        $validator = $this->validateOrFail([
            'new_email' => 'required|email|unique:users,email,'.$userId,
        ], [
            'new_email.unique' => chrome_translate('account.flash.emailInUse'),
        ]);

        $newEmail = strtolower(trim((string) $validator->validated()['new_email']));

        // Asking to change to the address already on the account is a no-op, not
        // an error, and mailing a token for it would be confusing.
        if ($newEmail === strtolower($currentEmail)) {
            return $this->reject(chrome_translate('account.flash.emailChangeSameAddress'));
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + self::TOKEN_TTL_MINUTES * 60);

        $this->pending->replaceForUser($userId, $newEmail, hash('sha256', $token), $expiresAt);

        $this->mailQueue->enqueue(
            new EmailChangeVerificationMail($newEmail, $token, self::TOKEN_TTL_MINUTES),
            'account',
            $userId
        );

        $this->flash('success', chrome_translate('account.flash.emailChangePending', ['email' => $newEmail]));

        return $this->redirect(lurl('/account/preferences'));
    }

    /**
     * Finish a change from the link in the new inbox.
     *
     * @param  string  $token  Raw token from the URL
     */
    public function confirm(string $token): Response
    {
        $userId = (int) auth()->user()['id'];
        $currentEmail = (string) (auth()->user()['email'] ?? '');

        $row = $this->pending->findValidByTokenHash(hash('sha256', $token));

        // A token that belongs to somebody else is treated exactly like one that
        // does not exist, so this cannot be used to probe other accounts.
        if (!$row || (int) $row['user_id'] !== $userId) {
            $this->flash('error', chrome_translate('account.flash.emailChangeLinkInvalid'));

            return $this->redirect(lurl('/account/preferences'));
        }

        $newEmail = (string) $row['new_email'];

        // Re-check availability at confirm time: the address was free when the
        // token was issued, but somebody else may have registered it since.
        $taken = $this->users->findByEmail($newEmail);
        if ($taken && (int) $taken['id'] !== $userId) {
            $this->pending->deleteForUser($userId);
            $this->flash('error', chrome_translate('account.flash.emailInUse'));

            return $this->redirect(lurl('/account/preferences'));
        }

        $this->users->updateById($userId, ['email' => $newEmail]);
        $this->pending->deleteForUser($userId);

        // OWASP asks that the address losing the account hears about it, so a
        // takeover is visible to the person it was taken from.
        $this->mailQueue->enqueue(
            new EmailChangedMail($currentEmail, $newEmail, gmdate('c')),
            'account',
            $userId
        );

        // The sign-in identifier just moved; regenerate as after any credential change.
        $this->session->regenerate();

        $this->flash('success', chrome_translate('account.flash.emailChanged', ['email' => $newEmail]));

        return $this->redirect(lurl('/account/preferences'));
    }

    /**
     * Drop a pending change the user no longer wants.
     */
    public function cancel(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $this->pending->deleteForUser((int) auth()->user()['id']);

        $this->flash('success', chrome_translate('account.flash.emailChangeCancelled'));

        return $this->redirect(lurl('/account/preferences'));
    }

    /**
     * Send the user back to Preferences with the modal's error, keeping the
     * address they typed so they do not have to retype it.
     */
    private function reject(string $message): Response
    {
        $this->session->set('_errors', ['new_email' => [$message]]);
        $this->session->set('_old_input', ['new_email' => $this->request->postParam('new_email')]);
        $this->flash('error', $message);

        return $this->redirect(lurl('/account/preferences'));
    }
}
