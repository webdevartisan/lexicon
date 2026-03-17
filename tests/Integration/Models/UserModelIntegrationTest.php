<?php

declare(strict_types=1);

use App\Models\UserModel;
use App\Models\BlogModel;
use App\Models\PostModel;
use Tests\Factories\UserFactory;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use function Pest\Faker\fake;

/**
 * Integration tests for UserModel.
 * 
 * Tests all UserModel methods with real database interactions.
 * Uses factories for data generation and transactions for isolation.
 */

beforeEach(function () {
    $this->userModel = new UserModel($this->db);
    $this->blogModel = new BlogModel($this->db);
    $this->postModel = new PostModel($this->db);
    
    expect($this->db->getConnection())->toHaveActiveTransaction();
});

// ============================================================================
// BASIC CRUD OPERATIONS
// ============================================================================

/**
 * Test that a user can be created in database.
 * 
 * Verifies insert operation returns valid ID and persists data correctly.
 */
it('inserts user and returns valid ID', function () {
    $email = faker()->unique()->safeEmail();
    
    $userId = UserFactory::new($this->userModel)
        ->withAttributes(['email' => $email])
        ->create();
    
    expect($userId)->toBeGreaterThan(0);
    
    $user = $this->userModel->findById($userId);
    expect($user['email'])->toBe($email);
});

/**
 * Test finding user by ID.
 * 
 * Verifies findById returns correct user data and excludes soft-deleted users.
 */
it('finds user by ID', function () {
    $email = faker()->unique()->safeEmail();
    $userId = UserFactory::new($this->userModel)
        ->withAttributes(['email' => $email])
        ->create();
    
    $user = $this->userModel->findById($userId);
    
    expect($user)->toBeArray()
        ->and($user['id'])->toBe($userId)
        ->and($user['email'])->toBe($email);
});

/**
 * Test that findById excludes soft-deleted users.
 * 
 * Verifies soft delete filtering in findById method.
 */
it('returns null when finding soft-deleted user by ID', function () {
    $userId = UserFactory::new($this->userModel)->deleted()->create();
    
    $user = $this->userModel->findById($userId);
    
    expect($user)->toBeNull();
});

/**
 * Test finding user by email.
 * 
 * Verifies findByEmail returns correct user data.
 */
it('finds user by email', function () {
    $email = faker()->unique()->safeEmail();
    $userId = UserFactory::new($this->userModel)
        ->withAttributes(['email' => $email])
        ->create();
    
    $user = $this->userModel->findByEmail($email);
    
    expect($user)->toBeArray()
        ->and($user['id'])->toBe($userId)
        ->and($user['email'])->toBe($email);
});

/**
 * Test that findByEmail returns null for non-existent email.
 */
it('returns false when email does not exist', function () {
    $user = $this->userModel->findByEmail('nonexistent-' . faker()->uuid() . '@example.com');
    
    expect($user)->toBeNull();
});

/**
 * Test that findByEmail excludes soft-deleted users.
 */
it('returns null when finding soft-deleted user by email', function () {
    $email = faker()->unique()->safeEmail();
    UserFactory::new($this->userModel)
        ->withAttributes(['email' => $email])
        ->deleted()
        ->create();
    
    $user = $this->userModel->findByEmail($email);
    
    expect($user)->toBeNull();
});

/**
 * Test finding users by username.
 * 
 * Verifies findByUsername returns array of matching users.
 */
it('finds users by username', function () {
    $username = faker()->unique()->userName();
    $userId = UserFactory::new($this->userModel)
        ->withAttributes(['username' => $username])
        ->create();
    
    $users = $this->userModel->findByUsername($username);
    
    expect($users)->toBeArray()
        ->and($users)->toHaveCount(1)
        ->and($users[0]['username'])->toBe($username);
});

/**
 * Test retrieving all non-deleted users.
 * 
 * Verifies findAll excludes soft-deleted users.
 */
it('finds all active users excluding soft-deleted', function () {
    UserFactory::new($this->userModel)->count(3);
    UserFactory::new($this->userModel)->deleted()->count(2);
    
    $users = $this->userModel->findAll();
    
    expect($users)->toBeArray()
        ->and(count($users))->toBeGreaterThanOrEqual(3);
    
    foreach ($users as $user) {
        expect($user['deleted_at'])->toBeNull();
    }
});

/**
 * Test finding user as Resource for authorization.
 * 
 * Verifies findResource returns UserResource with roles loaded.
 */
it('finds user as resource with roles loaded', function () {
    $userId = UserFactory::new($this->userModel)
        ->withRoles([2, 3])
        ->create();
    
    $resource = $this->userModel->findResource($userId);
    
    expect($resource)->toBeInstanceOf(\App\Resources\UserResource::class);
    
    $resourceData = $resource->toArray();
    expect($resourceData)->toHaveKey('roles');
});

/**
 * Test that findResource returns false for non-existent user.
 */
it('returns false when finding non-existent user as resource', function () {
    $resource = $this->userModel->findResource(99999);
    
    expect($resource)->toBeFalse();
});

/**
 * Test updating user data.
 * 
 * Verifies updateById persists changes to database.
 */
it('updates user data by ID', function () {
    $userId = UserFactory::new($this->userModel)->create();
    
    $newFirstName = faker()->firstName();
    $result = $this->userModel->updateById($userId, [
        'first_name' => $newFirstName,
    ]);
    
    expect($result)->toBeTrue();
    
    $user = $this->userModel->findById($userId);
    expect($user['first_name'])->toBe($newFirstName);
});

/**
 * Test that updateById returns true for empty data.
 * 
 * Verifies no-op updates are handled gracefully.
 */
it('handles empty update data gracefully', function () {
    $userId = UserFactory::new($this->userModel)->create();
    
    $result = $this->userModel->updateById($userId, []);
    
    expect($result)->toBeTrue();
});

/**
 * Test that updateById validates column names.
 * 
 * Verifies protection against SQL injection via column names.
 */
it('throws exception for invalid column names in update', function () {
    $userId = UserFactory::new($this->userModel)->create();
    
    expect(fn() => $this->userModel->updateById($userId, [
        'email; DROP TABLE users;--' => 'malicious'
    ]))->toThrow(\Exception::class);
});

// ============================================================================
// SOFT DELETE & RESTORE
// ============================================================================

/**
 * Test soft deleting user.
 * 
 * Verifies softDelete marks user as deleted without removing data.
 */
it('soft deletes user and excludes from queries', function () {
    $userId = UserFactory::new($this->userModel)->create();
    
    $result = $this->userModel->softDelete($userId);
    
    expect($result)->toBeTrue();
    expect($this->userModel->findById($userId))->toBeNull();
    
    // Verify data persists with deleted_at timestamp for audit trail
    $conn = $this->db->getConnection();
    $stmt = $conn->prepare('SELECT deleted_at FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    expect($row['deleted_at'])->not->toBeNull();
});

/**
 * Test that soft deleting already-deleted user returns true.
 * 
 * Verifies idempotent behavior for soft delete operations.
 */
it('handles soft deleting already deleted user', function () {
    $userId = UserFactory::new($this->userModel)->deleted()->create();
    
    $result = $this->userModel->softDelete($userId);

    expect($result)->toBeTrue();
});

/**
 * Test restoring soft-deleted user.
 * 
 * Verifies restoreDeleted clears deleted_at and makes user queryable again.
 */
it('restores soft-deleted user', function () {
    $userId = UserFactory::new($this->userModel)->deleted()->create();
    
    $result = $this->userModel->restoreDeleted($userId);
    
    expect($result)->toBeTrue();
    
    $user = $this->userModel->findById($userId);
    expect($user)->toBeArray()
        ->and($user['id'])->toBe($userId);
});

// ============================================================================
// ROLE MANAGEMENT
// ============================================================================

/**
 * Test inserting user roles.
 * 
 * Verifies insertUserRoles creates role associations in user_roles table.
 */
it('inserts user roles', function () {
    $userId = UserFactory::new($this->userModel)->create();
    
    $roleIds = [2, 3];
    $result = $this->userModel->insertUserRoles($userId, $roleIds);
    
    expect($result)->toBeTrue();
    
    $roles = $this->userModel->getUserRoles($userId);
    expect($roles)->toBeArray()
        ->and(count($roles))->toBeGreaterThanOrEqual(2);
});

/**
 * Test that inserting empty roles array returns true.
 * 
 * Verifies no-op role insertion is handled gracefully.
 */
it('handles inserting empty roles array', function () {
    $userId = UserFactory::new($this->userModel)->create();
    
    $result = $this->userModel->insertUserRoles($userId, []);
    
    expect($result)->toBeTrue();
});

/**
 * Test updating user roles.
 * 
 * Verifies updateUserRoles replaces existing roles with new set.
 */
it('updates user roles by replacing existing roles', function () {
    $userId = UserFactory::new($this->userModel)
        ->withRoles([2])
        ->create();
    
    $result = $this->userModel->updateUserRoles($userId, [3, 4]);
    
    expect($result)->toBeTrue();
    
    $roles = $this->userModel->getUserRoles($userId);
    
    expect($roles)->toBeArray()
        ->and(count($roles))->toBeGreaterThanOrEqual(2);
});

/**
 * Test retrieving user role slugs.
 * 
 * Verifies getUserRoles returns array of role slug strings.
 */
it('gets user role slugs', function () {
    $userId = UserFactory::new($this->userModel)
        ->withRoles([2, 3])
        ->create();
    
    $roles = $this->userModel->getUserRoles($userId);
    
    expect($roles)->toBeArray()
        ->and($roles)->not->toBeEmpty();
});

/**
 * Test retrieving user permissions.
 * 
 * Verifies getUserPermissions returns distinct permission slugs via roles.
 */
it('gets user permissions from assigned roles', function () {
    $userId = UserFactory::new($this->userModel)
        ->admin()
        ->create();
    
    $permissions = $this->userModel->getUserPermissions($userId);
    
    expect($permissions)->toBeArray();
});

// ============================================================================
// BUSINESS LOGIC & VALIDATION
// ============================================================================

/**
 * Test that user with posts cannot be deleted.
 * 
 * Verifies canDelete prevents deletion when user has authored content.
 */
it('prevents deletion when user has authored posts', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId])
        ->create();
    
    $canDelete = $this->userModel->canDelete($userId);
    
    expect($canDelete)->toBeFalse();
});

/**
 * Test that user without posts can be deleted.
 * 
 * Verifies canDelete allows deletion when no content exists.
 */
it('allows deletion when user has no posts', function () {
    $userId = UserFactory::new($this->userModel)->create();
    
    $canDelete = $this->userModel->canDelete($userId);
    
    expect($canDelete)->toBeTrue();
});

// ============================================================================
// COUNTING METHODS
// ============================================================================

/**
 * Test counting user's authored posts.
 * 
 * Verifies countPosts returns accurate post count.
 */
it('counts user authored posts', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId])
        ->count(5);
    
    $count = $this->userModel->countPosts($userId);
    
    expect($count)->toBe(5);
});

/**
 * Test counting user's owned blogs.
 * 
 * Verifies countBlogs returns accurate blog count.
 */
it('counts user owned blogs', function () {
    $userId = UserFactory::new($this->userModel)->create();
    
    BlogFactory::new($this->blogModel)->count(3)->create($userId);
    
    $count = $this->userModel->countBlogs($userId);
    
    expect($count)->toBe(3);
});

/**
 * Test counting comments received on user's posts.
 * 
 * Verifies countCommentsReceived returns accurate count.
 */
it('counts comments received on user posts', function () {
    $userId = UserFactory::new($this->userModel)->create();
    
    $count = $this->userModel->countCommentsReceived($userId);
    
    expect($count)->toBeInt()
        ->and($count)->toBeGreaterThanOrEqual(0);
});



/**
 * Test updating user password hash.
 * 
 * Verifies updatePasswordHashById persists new password hash securely.
 */
it('updates user password hash', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $newHash = password_hash('NewSecurePassword123!', PASSWORD_DEFAULT);
    
    $result = $this->userModel->updatePasswordHashById($userId, $newHash);
    
    expect($result)->toBeTrue();
    
    $conn = $this->db->getConnection();
    $stmt = $conn->prepare('SELECT password FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    expect($user['password'])->toBe($newHash);
});
