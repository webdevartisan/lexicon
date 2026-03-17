<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\UploadServiceInterface;
use App\Models\UserModel;
use App\Models\UserProfileModel;
use App\Models\UserSocialLinkModel;
use App\Models\UserPreferencesModel;
use Exception;

/**
 * UserDeletionService
 *
 * Orchestrates user account deletion and anonymization workflows.
 * Coordinates operations across multiple models with proper transaction
 * management for GDPR compliance.
 */
class UserDeletionService
{
    /**
     * Constructor with dependency injection.
     */
    public function __construct(
        private UserModel $users,
        private UserProfileModel $profiles,
        private UserSocialLinkModel $socialLinks,
        private UserPreferencesModel $preferences,
        private UploadServiceInterface $uploader
    ) {}

    /**
     * Pseudonymize user data for GDPR compliance.
     *
     * Replaces all PII with anonymous values across all user-related tables
     * while preserving content attribution. Deletes uploaded files.
     *
     * @param int $userId User ID to pseudonymize
     * @return bool True on success
     * @throws Exception If pseudonymization fails
     */
    public function pseudonymizeUser(int $userId): bool
    {
        // Generate unique anonymous identifiers to avoid collisions
        $timestamp = time();
        $anonymousEmail = "deleted_user_{$userId}_{$timestamp}@deleted.local";
        $anonymousUsername = "deleted_user_{$userId}";
        $anonymousSlug = "deleted_{$userId}";

        return $this->users->transaction(function() use ($userId, $anonymousEmail, $anonymousUsername, $anonymousSlug) {
            
            // Step 1: Anonymize core user record
            $this->users->updateById($userId, [
                'email' => $anonymousEmail,
                'username' => $anonymousUsername,
                'first_name' => 'Deleted',
                'last_name' => 'User',
                'password' => '',
                'last_login' => null
            ]);

            // Step 2: Clear user profile PII
            $this->profiles->updateByUserId($userId, [
                'slug' => $anonymousSlug,
                'bio' => null,
                'occupation' => null,
                'location' => null,
                'avatar_url' => null
            ]);

            // Step 3: Delete social links (contain external PII)
            $this->socialLinks->deleteByUserId($userId);

            // Step 4: Reset preferences to defaults
            $this->preferences->updateByUserId($userId, [
                'timezone' => 'UTC',
                'notify_comments' => 0,
                'notify_likes' => 0
            ]);

            // Step 5: Delete uploaded files
            $this->uploader->deleteUserUploads($userId);

            return true;
        });
    }

    /**
     * Check if user can be safely deleted.
     *
     * Applies business rules before allowing deletion, such as
     * preventing deletion of the last administrator account.
     *
     * @param int $userId User ID to check
     * @return array{canDelete: bool, reason: string}
     */
    public function canDeleteUser(int $userId): array
    {
        $user = $this->users->findById($userId);
        
        if (!$user) {
            return [
                'canDelete' => false,
                'reason' => 'User not found',
            ];
        }

        // Prevent deletion of the last administrator
        $roles = $this->users->getUserRoles($userId);

        if (in_array('administrator', $roles, true)) {
            $adminCount = $this->users->countAdministrators();

            if ($adminCount <= 1) {
                return [
                    'canDelete' => false,
                    'reason' => 'Cannot delete the last administrator account',
                ];
            }
        }

        return [
            'canDelete' => true,
            'reason' => '',
        ];
    }

    /**
     * Perform complete user deletion workflow.
     *
     * Pseudonymizes data and performs soft delete with transaction safety.
     * Caller is responsible for audit logging and session management.
     *
     * @param int $userId User ID to delete
     * @return bool True on success
     * @throws Exception If deletion fails
     */
    public function deleteUser(int $userId): bool
    {
        return $this->users->transaction(function() use ($userId) {
            
            // Step 1: Pseudonymize all PII
            $this->pseudonymizeUserInTransaction($userId);

            // Step 2: Soft delete the account
            $this->users->softDelete($userId);

            return true;
        });
    }

    /**
     * Internal pseudonymization without transaction wrapper.
     *
     * Used within deleteUser() which already manages the transaction.
     * Separated to avoid nested transaction issues.
     *
     * @param int $userId User ID to pseudonymize
     */
    private function pseudonymizeUserInTransaction(int $userId): void
    {
        $timestamp = time();
        $anonymousEmail = "deleted_user_{$userId}_{$timestamp}@deleted.local";
        $anonymousUsername = "deleted_user_{$userId}";
        $anonymousSlug = "deleted_{$userId}";

        // Anonymize core user record
        $this->users->updateById($userId, [
            'email' => $anonymousEmail,
            'username' => $anonymousUsername,
            'first_name' => 'Deleted',
            'last_name' => 'User',
            'password' => '',
            'last_login' => null
        ]);

        // Clear user profile PII
        $this->profiles->updateByUserId($userId, [
            'slug' => $anonymousSlug,
            'bio' => null,
            'occupation' => null,
            'location' => null,
            'avatar_url' => null
        ]);

        // Delete social links
        $this->socialLinks->deleteByUserId($userId);

        // Reset preferences
        $this->preferences->updateByUserId($userId, [
            'timezone' => 'UTC',
            'notify_comments' => 0,
            'notify_likes' => 0
        ]);

        // Delete uploaded files
        $this->uploader->deleteUserUploads($userId);
    }
}
