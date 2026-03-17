<?php

namespace Tests\Helpers;

use App\Auth;
use Framework\Session;
use App\Models\UserModel;
use App\Models\UserProfileModel;
use Mockery;

class AuthTestHelper
{
    /**
     * Creates a fully mocked Auth instance for unit testing.
     * All dependencies (Session, UserModel, UserProfileModel) are mocked to ensure isolation.
     *
     * @return array{auth: Auth, session: Mockery\MockInterface, userModel: Mockery\MockInterface, profileModel: Mockery\MockInterface}
     */
    public static function createMockedAuth(): array
    {
        $session = Mockery::mock(Session::class);
        $userModel = Mockery::mock(UserModel::class);
        $profileModel = Mockery::mock(UserProfileModel::class);
        
        $auth = new Auth($session, $userModel, $profileModel);
        
        return [
            'auth' => $auth,
            'session' => $session,
            'userModel' => $userModel,
            'profileModel' => $profileModel,
        ];
    }
    
    /**
     * Creates a sample user array matching typical database structure.
     * Used for mocking UserModel return values in unit tests.
     *
     * @param array $overrides Custom field values to override defaults
     * @return array User data array with common fields
     */
    public static function mockUserData(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'email' => 'mock@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'username' => 'mockuser',
            'created_at' => date('Y-m-d H:i:s'),
        ], $overrides);
    }
}
