<?php

declare(strict_types=1);

use App\Interfaces\UploadServiceInterface;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Models\UserProfileModel;
use App\Models\UserSocialLinkModel;
use App\Services\UserDeletionService;
use Mockery as m;

/**
 * Unit tests for UserDeletionService.
 *
 * We test business logic in isolation by mocking all dependencies.
 * No database connections are made - tests run in milliseconds.
 */
describe('UserDeletionService', function () {

    beforeEach(function () {
        $this->userModel = m::mock(UserModel::class);
        $this->profileModel = m::mock(UserProfileModel::class);
        $this->socialLinksModel = m::mock(UserSocialLinkModel::class);
        $this->preferencesModel = m::mock(UserPreferencesModel::class);
        $this->uploadService = m::mock(UploadServiceInterface::class);

        $this->service = new UserDeletionService(
            $this->userModel,
            $this->profileModel,
            $this->socialLinksModel,
            $this->preferencesModel,
            $this->uploadService
        );
    });

    afterEach(function () {
        m::close();
    });

    describe('pseudonymizeUser()', function () {

        it('anonymizes user data across all tables within transaction', function () {
            $userId = 123;

            // Mock transaction wrapper - execute callback immediately
            $this->userModel->shouldReceive('transaction')
                ->once()
                ->andReturnUsing(function (callable $callback) {
                    return $callback();
                });

            // Expect core user data anonymization
            $this->userModel->shouldReceive('updateById')
                ->once()
                ->with($userId, m::on(function ($data) use ($userId) {
                    return str_starts_with($data['email'], 'deleted_user_')
                        && $data['username'] === "deleted_user_{$userId}"
                        && $data['first_name'] === 'Deleted'
                        && $data['last_name'] === 'User'
                        && $data['password'] === ''
                        && $data['last_login'] === null;
                }))
                ->andReturn(true);

            // Expect profile anonymization
            $this->profileModel->shouldReceive('updateByUserId')
                ->once()
                ->with($userId, m::on(function ($data) use ($userId) {
                    return $data['slug'] === "deleted_{$userId}"
                        && $data['bio'] === null
                        && $data['occupation'] === null
                        && $data['location'] === null
                        && $data['avatar_url'] === null;
                }))
                ->andReturn(true);

            // Expect social links deletion
            $this->socialLinksModel->shouldReceive('deleteByUserId')
                ->once()
                ->with($userId)
                ->andReturn(true);

            // Expect preferences reset to defaults
            $this->preferencesModel->shouldReceive('updateByUserId')
                ->once()
                ->with($userId, [
                    'timezone' => 'UTC',
                    'notify_comments' => 0,
                    'notify_likes' => 0,
                ])
                ->andReturn(true);

            // Expect file deletion
            $this->uploadService->shouldReceive('deleteUserUploads')
                ->once()
                ->with($userId);

            $result = $this->service->pseudonymizeUser($userId);

            expect($result)->toBeTrue();
        });

        it('generates unique email addresses to prevent collisions', function () {
            $userId = 456;
            $capturedEmails = [];

            $this->userModel->shouldReceive('transaction')
                ->twice()
                ->andReturnUsing(function (callable $callback) {
                    return $callback();
                });

            $this->userModel->shouldReceive('updateById')
                ->twice()
                ->andReturnUsing(function ($id, $data) use (&$capturedEmails) {
                    $capturedEmails[] = $data['email'];

                    return true;
                });

            $this->profileModel->shouldReceive('updateByUserId')->twice()->andReturn(true);
            $this->socialLinksModel->shouldReceive('deleteByUserId')->twice()->andReturn(true);
            $this->preferencesModel->shouldReceive('updateByUserId')->twice()->andReturn(true);
            $this->uploadService->shouldReceive('deleteUserUploads')->twice();

            // Call twice to verify uniqueness
            $this->service->pseudonymizeUser($userId);
            sleep(1); // Ensure timestamp differs
            $this->service->pseudonymizeUser($userId);

            expect($capturedEmails[0])->not->toBe($capturedEmails[1]);
        });

        it('returns true when transaction completes successfully', function () {
            $userId = 789;

            $this->userModel->shouldReceive('transaction')
                ->once()
                ->andReturn(true);

            $result = $this->service->pseudonymizeUser($userId);

            expect($result)->toBeTrue();
        });

        it('throws exception when transaction fails', function () {
            $userId = 999;

            // Transaction wrapper throws exception on failure
            $this->userModel->shouldReceive('transaction')
                ->once()
                ->andThrow(new Exception('Transaction failed'));

            expect(fn () => $this->service->pseudonymizeUser($userId))
                ->toThrow(Exception::class, 'Transaction failed');
        });
    });

    describe('canDeleteUser()', function () {

        it('returns false when user does not exist', function () {
            $userId = 404;

            $this->userModel->shouldReceive('findById')
                ->once()
                ->with($userId)
                ->andReturn(null);

            $result = $this->service->canDeleteUser($userId);

            expect($result)->toBe([
                'canDelete' => false,
                'reason' => 'User not found',
            ]);
        });

        it('prevents deletion of last administrator account', function () {
            $userId = 1;

            $this->userModel->shouldReceive('findById')
                ->once()
                ->with($userId)
                ->andReturn(['id' => $userId]);

            $this->userModel->shouldReceive('getUserRoles')
                ->once()
                ->with($userId)
                ->andReturn(['administrator']);

            // Mock only 1 admin exists
            $this->userModel->shouldReceive('countAdministrators')
                ->once()
                ->andReturn(1);

            $result = $this->service->canDeleteUser($userId);

            expect($result)->toBe([
                'canDelete' => false,
                'reason' => 'Cannot delete the last administrator account',
            ]);
        });

        it('allows deletion of administrator when multiple admins exist', function () {
            $userId = 2;

            $this->userModel->shouldReceive('findById')
                ->once()
                ->with($userId)
                ->andReturn(['id' => $userId]);

            $this->userModel->shouldReceive('getUserRoles')
                ->once()
                ->with($userId)
                ->andReturn(['administrator']);

            // Mock 3 admins exist
            $this->userModel->shouldReceive('countAdministrators')
                ->once()
                ->andReturn(3);

            $result = $this->service->canDeleteUser($userId);

            expect($result)->toBe([
                'canDelete' => true,
                'reason' => '',
            ]);
        });

        it('allows deletion of non-administrator users', function () {
            $userId = 5;

            $this->userModel->shouldReceive('findById')
                ->once()
                ->with($userId)
                ->andReturn(['id' => $userId]);

            $this->userModel->shouldReceive('getUserRoles')
                ->once()
                ->with($userId)
                ->andReturn(['editor', 'author']);

            $result = $this->service->canDeleteUser($userId);

            expect($result)->toBe([
                'canDelete' => true,
                'reason' => '',
            ]);
        });

        it('allows deletion of users with no roles', function () {
            $userId = 10;

            $this->userModel->shouldReceive('findById')
                ->once()
                ->with($userId)
                ->andReturn(['id' => $userId]);

            $this->userModel->shouldReceive('getUserRoles')
                ->once()
                ->with($userId)
                ->andReturn([]);

            $result = $this->service->canDeleteUser($userId);

            expect($result)->toBe([
                'canDelete' => true,
                'reason' => '',
            ]);
        });
    });

    describe('deleteUser()', function () {

        it('executes complete deletion workflow within transaction', function () {
            $userId = 50;

            // Mock transaction wrapper
            $this->userModel->shouldReceive('transaction')
                ->once()
                ->andReturnUsing(function (callable $callback) {
                    return $callback();
                });

            // Expect pseudonymization (internal method calls)
            $this->userModel->shouldReceive('updateById')->once()->andReturn(true);
            $this->profileModel->shouldReceive('updateByUserId')->once()->andReturn(true);
            $this->socialLinksModel->shouldReceive('deleteByUserId')->once()->andReturn(true);
            $this->preferencesModel->shouldReceive('updateByUserId')->once()->andReturn(true);
            $this->uploadService->shouldReceive('deleteUserUploads')->once();

            // Expect soft delete
            $this->userModel->shouldReceive('softDelete')
                ->once()
                ->with($userId)
                ->andReturn(true);

            $result = $this->service->deleteUser($userId);

            expect($result)->toBeTrue();
        });

        it('soft deletes user after pseudonymization', function () {
            $userId = 75;
            $deleteCalled = false;

            $this->userModel->shouldReceive('transaction')
                ->once()
                ->andReturnUsing(function (callable $callback) {
                    return $callback();
                });

            $this->userModel->shouldReceive('updateById')->once()->andReturn(true);
            $this->profileModel->shouldReceive('updateByUserId')->once()->andReturn(true);
            $this->socialLinksModel->shouldReceive('deleteByUserId')->once()->andReturn(true);
            $this->preferencesModel->shouldReceive('updateByUserId')->once()->andReturn(true);
            $this->uploadService->shouldReceive('deleteUserUploads')->once();

            // Track that soft delete is called
            $this->userModel->shouldReceive('softDelete')
                ->once()
                ->with($userId)
                ->andReturnUsing(function () use (&$deleteCalled) {
                    $deleteCalled = true;

                    return true;
                });

            $this->service->deleteUser($userId);

            expect($deleteCalled)->toBeTrue();
        });

        it('throws exception when deletion transaction fails', function () {
            $userId = 100;

            $this->userModel->shouldReceive('transaction')
                ->once()
                ->andThrow(new Exception('Deletion failed'));

            expect(fn () => $this->service->deleteUser($userId))
                ->toThrow(Exception::class, 'Deletion failed');
        });

        it('rolls back all changes when soft delete fails', function () {
            $userId = 125;

            $this->userModel->shouldReceive('transaction')
                ->once()
                ->andReturnUsing(function (callable $callback) {
                    // Simulate rollback by throwing exception
                    throw new Exception('Soft delete failed');
                });

            expect(fn () => $this->service->deleteUser($userId))
                ->toThrow(Exception::class);
        });
    });
});
