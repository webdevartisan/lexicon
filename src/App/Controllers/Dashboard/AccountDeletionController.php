<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\UserModel;
use App\Services\UserDeletionService;
use Exception;
use Framework\Core\Response;

/**
 * AccountDeletionController
 *
 * Handles user account deletion requests with GDPR compliance.
 * Implements a two-step confirmation process to prevent accidental
 * data loss and ensure user intent.
 */
class AccountDeletionController extends AppController
{
    /**
     * Constructor with dependency injection.
     */
    public function __construct(
        private UserModel $users,
        private UserDeletionService $deletionService
    ) {}

    /**
     * Display account deletion confirmation page.
     *
     * Show what will be deleted and require explicit confirmation.
     */
    public function confirm(): Response
    {
        $userId = (int) auth()->user()['id'];

        // Load the user as a Resource for policy authorization
        $userResource = $this->users->findResource($userId);

        if (!$userResource) {
            return $this->notFound('User not found');
        }

        // Authorize the delete action via policy
        Gate::authorize('delete', $userResource, auth()->user());

        // Fetch deletion impact stats
        $postCount = $this->users->countPosts($userId);
        $commentCount = $this->users->countCommentsReceived($userId);

        // Check if deletion is allowed per business rules
        $deletionCheck = $this->deletionService->canDeleteUser($userId);

        return $this->view('account.delete', [
            'user' => $userResource->toArray(),
            'postCount' => $postCount,
            'commentCount' => $commentCount,
            'canDelete' => $deletionCheck['canDelete'],
            'deleteReason' => $deletionCheck['reason'],
        ]);
    }

    /**
     * Process account deletion request.
     *
     * Implement GDPR-compliant deletion with full audit trail.
     */
    public function destroy(): Response
    {
        // Enforce CSRF protection on destructive actions
        csrf()->assertValid($this->request->postParam('_token'));

        $userId = (int) auth()->user()['id'];

        // Load user as Resource for authorization
        $userResource = $this->users->findResource($userId);

        if (!$userResource) {
            $this->flash('error', 'User not found.');

            return $this->redirect('/dashboard');
        }

        // Authorize via policy
        try {
            Gate::authorize('delete', $userResource, auth()->user());
        } catch (Exception $e) {
            $this->flash('error', 'You are not authorized to delete this account.');

            return $this->redirect('/dashboard');
        }

        // Require password confirmation for security
        $password = $this->request->post['password'] ?? '';

        if (!$this->users->verifyPassword($userId, $password)) {
            $this->flash('error', 'Incorrect password. Account deletion cancelled.');

            return $this->redirect('/dashboard/profile');
        }

        // Check business rules
        $deletionCheck = $this->deletionService->canDeleteUser($userId);

        if (!$deletionCheck['canDelete']) {
            $this->flash('error', $deletionCheck['reason']);

            return $this->redirect('/dashboard/profile');
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

            $this->flash('success', 'Your account has been permanently deleted.');

            return $this->redirect('/');

        } catch (Exception $e) {
            error_log("Account deletion failed for user {$userId}: ".$e->getMessage());
            $this->flash('error', 'Failed to delete account. Please contact support.');

            return $this->redirect('/dashboard/profile');
        }
    }
}
