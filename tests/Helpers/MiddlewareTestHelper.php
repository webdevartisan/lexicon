<?php

namespace Tests\Helpers;

use App\Auth;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\RequestHandlerInterface;
use Mockery;

class MiddlewareTestHelper
{
    /**
     * Creates a mocked Auth instance with configurable authentication state.
     *
     * @param bool $isAuthenticated Whether user is authenticated
     * @return Mockery\MockInterface
     */
    public static function mockAuth(bool $isAuthenticated): Mockery\MockInterface
    {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('check')
            ->andReturn($isAuthenticated);
        
        return $auth;
    }
    
    /**
     * Creates a simple RequestHandler that returns a Response with body content.
     *
     * @param string $bodyContent Response body content
     * @return RequestHandlerInterface
     */
    public static function createHandler(string $bodyContent = 'Success'): RequestHandlerInterface
    {
        return new class($bodyContent) implements RequestHandlerInterface {
            public function __construct(private string $body) {}
            
            public function handle(Request $request): Response {
                $response = new Response();
                $response->setBody($this->body);
                return $response;
            }
        };
    }
    
    /**
     * Creates a RequestHandler that tracks whether it was invoked.
     *
     * @param bool $flag Reference to boolean flag that will be set to true when called
     * @return RequestHandlerInterface
     */
    public static function createTrackingHandler(bool &$flag): RequestHandlerInterface
    {
        return new class($flag) implements RequestHandlerInterface {
            public function __construct(private &$called) {}
            
            public function handle(Request $request): Response {
                $this->called = true;
                return new Response();
            }
        };
    }
    
    /**
     * Creates a RequestHandler that captures the Request object passed to it.
     *
     * @param Request|null $capturedRequest Reference to store the captured request
     * @return RequestHandlerInterface
     */
    public static function createCapturingHandler(?Request &$capturedRequest): RequestHandlerInterface
    {
        return new class($capturedRequest) implements RequestHandlerInterface {
            public function __construct(private &$captured) {}
            
            public function handle(Request $request): Response {
                $this->captured = $request;
                return new Response();
            }
        };
    }
}
