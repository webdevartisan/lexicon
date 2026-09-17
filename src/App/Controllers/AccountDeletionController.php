<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Gate;
use App\Models\UserModel;
use App\Services\AccountErasureSchedulerService;
use App\Services\AccountErasureService;
use App\Services\PasswordConfirmRateLimiter;
use Exception;
use Framework\Core\Response;

/**
 * Account deletion on the front, reached from the foot of Preferences rather
 * than from the section rail: an irreversible action should not sit beside
 * "change your display name" with equal weight.
 */
final class AccountDeletionController extends AppController
{
    public function __construct(
        private UserModel $users,
        private AccountErasureService $erasure,
        private AccountErasureSchedulerService $scheduler,
        private PasswordConfirmRateLimiter $passwordThrottle
    ) {}

    /**
     * Show what deletion does and require explicit confirmation.
     */
    public function confirm(): Response
    {
        $userId = (int) auth()->user()['id'];

        $userResource = $this->users->findResource($userId);

        if (!$userResource) {
            return $this->notFound('User not found');
        }

        // This is the confirmation screen, not the deletion itself: gate softly
        // so a user who cannot delete sees the page explain why, rather than a
        // hard "Access denied". The real enforcement is in destroy().
        $blockers = $this->erasure->blockers($userId);

        return $this->view('public.Account.delete', [
            'user' => $userResource->toArray(),
            'blockers' => $blockers,
            'canDelete' => $this->erasure->canErase($userId) && Gate::allows('delete', $userResource, auth()->user()),
        ]);
    }

    /**
     * Process the deletion. POST only, password-confirmed, never on GET.
     */
    public function destroy(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $userId = (int) auth()->user()['id'];

        $userResource = $this->users->findResource($userId);

        if (!$userResource) {
            $this->flash('error', chrome_translate('account.flash.userNotFound'));

            return $this->redirect(lurl('/account/preferences'));
        }

        try {
            Gate::authorize('delete', $userResource, auth()->user());
        } catch (Exception $e) {
            $this->flash('error', chrome_translate('account.flash.deletionNotAuthorized'));

            return $this->redirect(lurl('/account/preferences'));
        }

        // Throttled like every other password confirmation here: without a limit
        // this form is an unlimited oracle for guessing the password of whichever
        // account a stolen session belongs to.
        if ($this->passwordThrottle->tooManyAttempts($userId)) {
            $this->flash('error', chrome_translate('account.flash.deletionThrottled'));

            return $this->redirect(lurl('/account/preferences'));
        }

        if (!$this->users->verifyPassword($userId, (string) $this->request->postParam('password'))) {
            $this->passwordThrottle->hit($userId);
            $this->flash('error', chrome_translate('account.flash.deletionCancelled'));

            return $this->redirect(lurl('/account/preferences'));
        }

        $this->passwordThrottle->clear($userId);

        if (!$this->erasure->canErase($userId)) {
            return $this->redirect(lurl('/account/delete'));
        }

        $this->scheduler->schedule($userId, $userId, $this->request->ip());

        audit()->log($userId, 'user.erasure_scheduled', 'user', $userId, [], $this->request->ip());

        auth()->logout();

        $this->flash('success', chrome_translate('account.flash.accountDeletionScheduled', ['days' => erasure_grace_period_days()]));

        return $this->redirect(lurl('/'));
    }
}
