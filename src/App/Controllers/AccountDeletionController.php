<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Gate;
use App\Models\UserModel;
use App\Services\UserDeletionService;
use Exception;
use Framework\Core\Response;

/**
 * Account deletion on the front, reached from the foot of Preferences rather
 * than from the section rail: an irreversible action should not sit beside
 * "change your display name" with equal weight.
 *
 * Deletion pseudonymises then soft-deletes. Posts and comments are left in
 * place, attributed to a deleted user; the page copy says so plainly.
 */
final class AccountDeletionController extends AppController
{
    public function __construct(
        private UserModel $users,
        private UserDeletionService $deletionService
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

        Gate::authorize('delete', $userResource, auth()->user());

        $deletionCheck = $this->deletionService->canDeleteUser($userId);

        return $this->view('public.Account.delete', [
            'user' => $userResource->toArray(),
            'canDelete' => $deletionCheck['canDelete'],
            'deleteReason' => $deletionCheck['reason'],
        ]);
    }

    /**
     * Process the deletion. POST only, password-confirmed, never on GET.
     */
    public function destroy(): Response
    {
        // Enforce CSRF protection on destructive actions
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

        // Require password confirmation. Read through postParam(), the convention
        // used everywhere else, rather than the raw request array.
        $password = (string) $this->request->postParam('password');

        if (!$this->users->verifyPassword($userId, $password)) {
            $this->flash('error', chrome_translate('account.flash.deletionCancelled'));

            return $this->redirect(lurl('/account/preferences'));
        }

        $deletionCheck = $this->deletionService->canDeleteUser($userId);

        if (!$deletionCheck['canDelete']) {
            $this->flash('error', $deletionCheck['reason']);

            return $this->redirect(lurl('/account/preferences'));
        }

        try {
            // Audit log before deletion (capture email before pseudonymization)
            audit()->log(
                $userId,
                'user.account_deleted',
                'user',
                $userId,
                ['email' => $userResource->email()],
                $this->request->ip()
            );

            // Perform the deletion via service (handles transaction)
            $this->deletionService->deleteUser($userId);

            // Destroy session to log out
            auth()->logout();

            $this->flash('success', chrome_translate('account.flash.accountDeleted'));

            return $this->redirect(lurl('/'));

        } catch (Exception $e) {
            error_log("Account deletion failed for user {$userId}: ".$e->getMessage());
            $this->flash('error', chrome_translate('account.flash.deletionFailed'));

            return $this->redirect(lurl('/account/preferences'));
        }
    }
}
