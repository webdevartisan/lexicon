<?php

declare(strict_types=1);

namespace Tests;

use Mockery;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case providing common setup and helper methods.
 *
 * All test classes should extend this base case to inherit shared functionality.
 *
 *
 * @property \Framework\Database $db
 * @property \Framework\Session $session
 * @property \App\Models\UserModel $userModel
 * @property \App\Models\UserProfileModel $profileModel
 * @property \App\Auth $auth
 * @property \App\Models\PostModel $postModel
 * @property \App\Models\BlogModel $blogModel
 * @property \App\Models\CategoryModel $categoryModel
 * @property \App\Models\CommentModel $commentModel
 * @property \App\Models\TagModel $tagModel
 *
 * @method void cleanDatabase()
 *
 * @var string $mockCachePath
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Setup executed before each test method.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetGlobalState();

        // Initialize app container for tests
        $this->initializeContainer();

        truncateAll();
    }

    /**
     * Initialize application container for feature tests.
     */
    protected function initializeContainer(): void
    {
        if (!\Framework\Core\App::hasContainer()) {
            if (!defined('ROOT_PATH')) {
                define('ROOT_PATH', dirname(__DIR__));
            }
            require_once __DIR__.'/../config/services.php';
            // $container is returned from services.php
            \Framework\Core\App::setContainer($container);
        }
    }

    /**
     * Cleanup executed after each test method.
     */
    protected function tearDown(): void
    {
        Mockery::close();
        $this->resetGlobalState();

        parent::tearDown();
    }

    /**
     * Resets PHP global variables to clean state.
     *
     * Prevents test pollution from modified superglobals.
     */
    protected function resetGlobalState(): void
    {
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        $_COOKIE = [];
        $_FILES = [];
        $_SERVER = [];
        $_SESSION = [];
    }

    /**
     * Creates a mock HTTP request for testing.
     *
     * @param  string  $method  HTTP method (GET, POST, etc.)
     * @param  string  $uri  Request URI
     * @param  array<string, mixed>  $params  Request parameters
     * @return mixed Mock request object (adjust return type to your Request class)
     */
    protected function createMockRequest(
        string $method = 'GET',
        string $uri = '/',
        array $params = []
    ): mixed {
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['HTTP_HOST'] = 'localhost';

        if ($method === 'GET') {
            $_GET = $params;
        } else {
            $_POST = $params;
        }

        // return a mock request object
        // Adjust this to match your actual Request class
        return (object) [
            'method' => $method,
            'uri' => $uri,
            'params' => $params,
        ];
    }
}
