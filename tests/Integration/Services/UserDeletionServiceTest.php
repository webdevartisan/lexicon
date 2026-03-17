<?php

declare(strict_types=1);

use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Models\UserProfileModel;
use App\Models\UserSocialLinkModel;
use App\Services\UploadService;
use App\Services\UserDeletionService;
use Framework\Database;
use Tests\Factories\UserFactory;
use Tests\Helpers\UserRelationsHelper;

/**
 * Integration tests for UserDeletionService.
 *
 * We test actual database operations with real models and transactions.
 * Transaction rollback ensures no data persists between tests.
 */
describe('UserDeletionService Integration', function () {

    beforeEach(function () {
        $this->userModel = new UserModel($this->db);
        $this->profileModel = new UserProfileModel($this->db);
        $this->socialLinksModel = new UserSocialLinkModel($this->db);
        $this->preferencesModel = new UserPreferencesModel($this->db);
        $this->uploadService = new UploadService();

        $this->service = new UserDeletionService(
            $this->userModel,
            $this->profileModel,
            $this->socialLinksModel,
            $this->preferencesModel,
            $this->uploadService
        );

        $this->userFactory = UserFactory::new($this->userModel);
    });

    describe('pseudonymizeUser()', function () {

        it('anonymizes user data across all related tables', function () {
            // Create user with complete profile data
            $userId = $this->userFactory
                ->withAttributes([
                    'email' => 'john.doe@example.com',
                    'username' => 'johndoe',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                ])
                ->create();

            UserRelationsHelper::createCompleteUserData(
                $this->db,
                $userId,
                [
                    'slug' => 'johndoe',
                    'bio' => 'Software engineer from Cyprus',
                    'occupation' => 'Developer',
                    'location' => 'Nicosia',
                    'avatar_url' => '/uploads/avatar.jpg',
                ],
                [
                    'twitter' => 'https://twitter.com/johndoe',
                    'github' => 'https://github.com/johndoe',
                ],
                [
                    'timezone' => 'Europe/Nicosia',
                    'notify_comments' => 1,
                    'notify_likes' => 1,
                ]
            );

            $result = $this->service->pseudonymizeUser($userId);

            expect($result)->toBeTrue();

            // Verify anonymization using helper
            UserRelationsHelper::assertUserAnonymized($this->db, $userId);
        });

        it('generates unique email addresses for multiple pseudonymizations', function () {
            $userId1 = $this->userFactory->create();
            $userId2 = $this->userFactory->create();

            UserRelationsHelper::createCompleteUserData($this->db, $userId1);
            UserRelationsHelper::createCompleteUserData($this->db, $userId2);

            $this->service->pseudonymizeUser($userId1);
            sleep(1); // Ensure timestamp differs
            $this->service->pseudonymizeUser($userId2);

            // Fetch anonymized emails
            $stmt = $this->db->query('SELECT email FROM users WHERE id IN (?, ?)', [$userId1, $userId2]);
            $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

            expect($emails[0])->not->toBe($emails[1]);
            expect($emails[0])->toStartWith('deleted_user_');
            expect($emails[1])->toStartWith('deleted_user_');
        });

        it('deletes all social links during pseudonymization', function () {
            $userId = $this->userFactory->create();

            UserRelationsHelper::createUserSocialLinks($this->db, $userId, [
                'twitter' => 'https://twitter.com/test',
                'github' => 'https://github.com/test',
                'linkedin' => 'https://linkedin.com/in/test',
            ]);

            // Verify links exist before
            expect(UserRelationsHelper::getSocialLinkCount($this->db, $userId))->toBe(3);

            $this->service->pseudonymizeUser($userId);

            // Verify links deleted after
            expect(UserRelationsHelper::getSocialLinkCount($this->db, $userId))->toBe(0);
        });

        it('resets preferences to default values', function () {
            $userId = $this->userFactory->create();

            UserRelationsHelper::createUserPreferences($this->db, $userId, [
                'timezone' => 'America/New_York',
                'notify_comments' => 1,
                'notify_likes' => 1,
            ]);

            $this->service->pseudonymizeUser($userId);

            // Check preferences reset
            $stmt = $this->db->query(
                'SELECT timezone, notify_comments, notify_likes FROM user_preferences WHERE user_id = ?',
                [$userId]
            );
            $prefs = $stmt->fetch();

            expect($prefs['timezone'])->toBe('UTC');
            expect($prefs['notify_comments'])->toBe(0);
            expect($prefs['notify_likes'])->toBe(0);
        });

        it('commits transaction when all operations succeed', function () {
            $userId = $this->userFactory->create();
            UserRelationsHelper::createCompleteUserData($this->db, $userId);

            $this->service->pseudonymizeUser($userId);

            // Verify data persisted (not rolled back)
            $stmt = $this->db->query('SELECT username FROM users WHERE id = ?', [$userId]);
            $user = $stmt->fetch();

            expect($user['username'])->toBe("deleted_user_{$userId}");
        });

        it('rolls back transaction when operation fails', function () {
            $userId = $this->userFactory->create();

            UserRelationsHelper::createUserProfile($this->db, $userId, [
                'slug' => 'original-slug',
            ]);

            // Force failure by using invalid user ID in the service
            // expect transaction to roll back any changes
            $originalSlug = $this->db->query(
                'SELECT slug FROM user_profiles WHERE user_id = ?',
                [$userId]
            )->fetch()['slug'];

            try {
                // Create a service with broken dependency to trigger rollback
                $brokenService = new UserDeletionService(
                    $this->userModel,
                    $this->profileModel,
                    $this->socialLinksModel,
                    new class($this->db) extends UserPreferencesModel
                    {
                        public function updateByUserId(int $userId, array $data): bool
                        {
                            throw new Exception('Simulated failure');
                        }
                    },
                    $this->uploadService
                );

                $brokenService->pseudonymizeUser($userId);
            } catch (Exception $e) {
                // Expected exception
            }

            // Verify data NOT changed (rolled back)
            $stmt = $this->db->query('SELECT slug FROM user_profiles WHERE user_id = ?', [$userId]);
            $profile = $stmt->fetch();

            expect($profile['slug'])->toBe($originalSlug);
        });
    });

    describe('canDeleteUser()', function () {

        it('returns false when user does not exist', function () {
            $result = $this->service->canDeleteUser(99999);

            expect($result)->toBe([
                'canDelete' => false,
                'reason' => 'User not found',
            ]);
        });

        it('prevents deletion of last administrator', function () {
            $adminId = $this->userFactory->admin()->create();

            $result = $this->service->canDeleteUser($adminId);

            expect($result['canDelete'])->toBeFalse();
            expect($result['reason'])->toContain('last administrator');
        });

        it('allows deletion of administrator when multiple exist', function () {
            $admin1 = $this->userFactory->admin()->create();
            $admin2 = $this->userFactory->admin()->create();

            $result = $this->service->canDeleteUser($admin1);

            expect($result['canDelete'])->toBeTrue();
            expect($result['reason'])->toBe('');
        });

        it('allows deletion of regular users', function () {
            $userId = $this->userFactory->create();

            $result = $this->service->canDeleteUser($userId);

            expect($result['canDelete'])->toBeTrue();
            expect($result['reason'])->toBe('');
        });
    });

    describe('deleteUser()', function () {

        it('pseudonymizes and soft deletes user in transaction', function () {
            $userId = $this->userFactory
                ->withAttributes([
                    'email' => 'delete.me@example.com',
                    'username' => 'deleteme',
                ])
                ->create();

            UserRelationsHelper::createCompleteUserData($this->db, $userId);

            $result = $this->service->deleteUser($userId);

            expect($result)->toBeTrue();

            // Verify pseudonymization
            UserRelationsHelper::assertUserAnonymized($this->db, $userId);

            // Verify soft delete
            $stmt = $this->db->query('SELECT deleted_at FROM users WHERE id = ?', [$userId]);
            $user = $stmt->fetch();

            expect($user['deleted_at'])->not->toBeNull();
        });

        it('maintains transaction atomicity across all operations', function () {
            $userId = $this->userFactory->create();
            UserRelationsHelper::createCompleteUserData($this->db, $userId);

            // Verify user exists with data before deletion
            expect(UserRelationsHelper::getSocialLinkCount($this->db, $userId))->toBe(3);

            $this->service->deleteUser($userId);

            // Verify all operations completed atomically
            $stmt = $this->db->query(
                'SELECT email, username, deleted_at FROM users WHERE id = ?',
                [$userId]
            );
            $user = $stmt->fetch();

            expect($user['email'])->toStartWith('deleted_user_');
            expect($user['username'])->toBe("deleted_user_{$userId}");
            expect($user['deleted_at'])->not->toBeNull();
            expect(UserRelationsHelper::getSocialLinkCount($this->db, $userId))->toBe(0);
        });

        it('deletes multiple users independently', function () {
            $user1 = $this->userFactory->create();
            $user2 = $this->userFactory->create();

            UserRelationsHelper::createCompleteUserData($this->db, $user1);
            UserRelationsHelper::createCompleteUserData($this->db, $user2);

            $this->service->deleteUser($user1);
            $this->service->deleteUser($user2);

            // Both users should be soft deleted and anonymized
            $stmt = $this->db->query(
                'SELECT id, deleted_at FROM users WHERE id IN (?, ?) AND deleted_at IS NOT NULL',
                [$user1, $user2]
            );

            expect($stmt->rowCount())->toBe(2);
        });
    });

    describe('GDPR Compliance', function () {

        it('removes all personally identifiable information', function () {
            $userId = $this->userFactory
                ->withAttributes([
                    'email' => 'gdpr.test@example.com',
                    'username' => 'gdprtest',
                    'first_name' => 'GDPR',
                    'last_name' => 'Test',
                ])
                ->create();

            UserRelationsHelper::createCompleteUserData(
                $this->db,
                $userId,
                [
                    'bio' => 'Personal bio with PII',
                    'location' => 'Nicosia, Cyprus',
                ],
                [
                    'twitter' => 'https://twitter.com/gdprtest',
                    'linkedin' => 'https://linkedin.com/in/gdprtest',
                ]
            );

            $this->service->pseudonymizeUser($userId);

            // Verify NO PII remains
            $stmt = $this->db->query(
                'SELECT email, first_name, last_name, password FROM users WHERE id = ?',
                [$userId]
            );
            $user = $stmt->fetch();

            expect($user['email'])->not->toContain('gdpr.test@example.com');
            expect($user['first_name'])->toBe('Deleted');
            expect($user['last_name'])->toBe('User');
            expect($user['password'])->toBe('');

            $stmt = $this->db->query(
                'SELECT bio, location, avatar_url FROM user_profiles WHERE user_id = ?',
                [$userId]
            );
            $profile = $stmt->fetch();

            expect($profile['bio'])->toBeNull();
            expect($profile['location'])->toBeNull();
            expect($profile['avatar_url'])->toBeNull();
        });
    });
});
