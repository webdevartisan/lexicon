<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\CommentService;
use App\Services\EmailVerificationService;
use Framework\Core\Response;
use Framework\Helpers\RateLimiter;

/**
 * Confirms a new account's email address.
 *
 * GET  /verify-email/{token}          → confirm()
 * POST /account/verify-email/resend   → resend()
 */
final class EmailVerificationController extends AppController
{
    // One resend per minute per account keeps the button from becoming a mail cannon.
    private const RESEND_DECAY_SECONDS = 60;

    public function __construct(
        private EmailVerificationService $verification,
        private UserModel $users,
        private RateLimiter $rateLimiter,
        private CommentService $comments,
    ) {}

    /**
     * Finish a confirmation from the link in the inbox.
     *
     * Works signed out: the token is the proof, as with a password reset link.
     *
     * @param  string  $token  Raw token from the URL
     */
    public function confirm(string $token): Response
    {
        $userId = $this->verification->confirm($token);

        if ($userId === null) {
            $this->flash('error', chrome_translate('auth.verifyInvalid'));

            return $this->redirect(lurl(auth()->check() ? '/' : '/login'));
        }

        $this->flash('success', chrome_translate('auth.verifyDone'));

        // A comment held back while the address was unproven posts now, if this is the same browser.
        $signedIn = auth()->user();
        if ($signedIn !== null && (int) $signedIn['id'] === $userId) {
            $resume = $this->comments->resumePending($this->users->find($userId), $this->request->ip());
            if ($resume !== null) {
                $this->flash($resume['ok'] ? 'success' : 'error', $resume['message']);

                return $this->redirect($resume['path']);
            }

            return $this->redirect(lurl('/'));
        }

        return $this->redirect(lurl('/login'));
    }

    /**
     * Mail a new link to the signed-in account.
     */
    public function resend(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        if (!email_unverified($user)) {
            $this->flash('info', chrome_translate('auth.verifyAlready'));

            return $this->redirectBack();
        }

        $key = 'verify-resend:'.(int) $user['id'];
        if ($this->rateLimiter->tooManyAttempts($key, 1, self::RESEND_DECAY_SECONDS)) {
            $this->flash('error', chrome_translate('auth.verifyTooSoon'));

            return $this->redirectBack();
        }

        $this->rateLimiter->hit($key, self::RESEND_DECAY_SECONDS);
        $this->verification->send((int) $user['id']);
        $this->flash('success', chrome_translate('auth.verifyResent', ['email' => $user['email']]));

        return $this->redirectBack();
    }
}
