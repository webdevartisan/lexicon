<?php

declare(strict_types=1);

use App\Middleware\SecurityHeadersMiddleware;
use Framework\Core\Request;
use Framework\Core\Response;
use Tests\Helpers\EnvironmentTestHelper;
use Tests\Helpers\MiddlewareTestHelper;

afterEach(function () {
    EnvironmentTestHelper::restore();
});

// ==================== BASIC SECURITY HEADERS ====================

it('adds X-Frame-Options header', function () {
    EnvironmentTestHelper::setDevelopment();
    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], []);
    $next = MiddlewareTestHelper::createHandler();

    $result = $middleware->process($request, $next);

    expect($result->getHeader('X-Frame-Options'))->toBe('SAMEORIGIN');
});

it('adds X-Content-Type-Options header', function () {
    EnvironmentTestHelper::setDevelopment();
    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], []);
    $next = MiddlewareTestHelper::createHandler();

    $result = $middleware->process($request, $next);

    expect($result->getHeader('X-Content-Type-Options'))->toBe('nosniff');
});

it('adds X-XSS-Protection header', function () {
    EnvironmentTestHelper::setDevelopment();
    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], []);
    $next = MiddlewareTestHelper::createHandler();

    $result = $middleware->process($request, $next);

    expect($result->getHeader('X-XSS-Protection'))->toBe('1; mode=block');
});

it('adds Referrer-Policy header', function () {
    EnvironmentTestHelper::setDevelopment();
    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], []);
    $next = MiddlewareTestHelper::createHandler();

    $result = $middleware->process($request, $next);

    expect($result->getHeader('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
});

it('adds all basic security headers in one response', function () {
    EnvironmentTestHelper::setDevelopment();
    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], []);
    $next = MiddlewareTestHelper::createHandler();

    $result = $middleware->process($request, $next);

    expect($result->getHeader('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($result->getHeader('X-Content-Type-Options'))->toBe('nosniff')
        ->and($result->getHeader('X-XSS-Protection'))->toBe('1; mode=block')
        ->and($result->getHeader('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
});

// ==================== SERVER HEADER OBFUSCATION ====================

it('obfuscates Server header in production', function () {
    EnvironmentTestHelper::setProduction();
    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], []);

    $next = new class() implements \Framework\Interfaces\RequestHandlerInterface
    {
        public function handle(\Framework\Core\Request $request): Response
        {
            $response = new Response();
            $response->addHeader('Server', 'Apache/2.4.41 (Ubuntu)');

            return $response;
        }
    };

    $result = $middleware->process($request, $next);

    expect($result->getHeader('Server'))->toBe('WebServer');
});

it('does not obfuscate Server header in development', function () {
    EnvironmentTestHelper::setDevelopment();
    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], []);

    $originalServerHeader = 'Apache/2.4.41 (Ubuntu)';
    $next = new class($originalServerHeader) implements \Framework\Interfaces\RequestHandlerInterface
    {
        public function __construct(private string $serverHeader) {}

        public function handle(\Framework\Core\Request $request): Response
        {
            $response = new Response();
            $response->addHeader('Server', $this->serverHeader);

            return $response;
        }
    };

    $result = $middleware->process($request, $next);

    expect($result->getHeader('Server'))->toBe($originalServerHeader);
});

it('obfuscates various server implementations in production', function ($serverHeader) {
    EnvironmentTestHelper::setProduction();
    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], []);

    $next = new class($serverHeader) implements \Framework\Interfaces\RequestHandlerInterface
    {
        public function __construct(private string $header) {}

        public function handle(\Framework\Core\Request $request): Response
        {
            $response = new Response();
            $response->addHeader('Server', $this->header);

            return $response;
        }
    };

    $result = $middleware->process($request, $next);

    expect($result->getHeader('Server'))->toBe('WebServer');
})->with([
    'Apache' => 'Apache/2.4.41',
    'Nginx' => 'nginx/1.18.0',
    'IIS' => 'Microsoft-IIS/10.0',
    'PHP built-in' => 'PHP 8.2.0 Development Server',
]);

// ==================== HSTS (HTTPS ENFORCEMENT) ====================

it('adds HSTS header in production over HTTPS', function () {
    EnvironmentTestHelper::setProduction();
    EnvironmentTestHelper::enableHttps();

    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], ['HTTPS' => 'on'], []);
    $next = MiddlewareTestHelper::createHandler();

    $result = $middleware->process($request, $next);

    expect($result->getHeader('Strict-Transport-Security'))
        ->toBe('max-age=31536000; includeSubDomains; preload');
});

it('does not add HSTS header in development even over HTTPS', function () {
    EnvironmentTestHelper::setDevelopment();
    EnvironmentTestHelper::enableHttps();

    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], ['HTTPS' => 'on'], []);
    $next = MiddlewareTestHelper::createHandler();

    $result = $middleware->process($request, $next);

    expect($result->getHeader('Strict-Transport-Security'))->toBeNull();
});

it('does not add HSTS header in production over HTTP', function () {
    EnvironmentTestHelper::setProduction();

    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], []);
    $next = MiddlewareTestHelper::createHandler();

    $result = $middleware->process($request, $next);

    expect($result->getHeader('Strict-Transport-Security'))->toBeNull();
});

// ==================== HTTPS DETECTION METHODS ====================

it('detects HTTPS via SERVER_PORT 443', function () {
    EnvironmentTestHelper::setProduction();
    EnvironmentTestHelper::enableHttpsViaPort();

    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], ['SERVER_PORT' => '443'], []);
    $next = MiddlewareTestHelper::createHandler();

    $result = $middleware->process($request, $next);

    expect($result->getHeader('Strict-Transport-Security'))->not->toBeNull();
});

it('detects HTTPS via X-Forwarded-Proto header', function () {
    EnvironmentTestHelper::setProduction();
    EnvironmentTestHelper::enableHttpsViaProxy();

    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], ['HTTP_X_FORWARDED_PROTO' => 'https'], []);
    $next = MiddlewareTestHelper::createHandler();

    $result = $middleware->process($request, $next);

    expect($result->getHeader('Strict-Transport-Security'))->not->toBeNull();
});

it('does not add HSTS when X-Forwarded-Proto is http', function () {
    EnvironmentTestHelper::setProduction();
    EnvironmentTestHelper::setServer('HTTP_X_FORWARDED_PROTO', 'http');

    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], ['HTTP_X_FORWARDED_PROTO' => 'http'], []);
    $next = MiddlewareTestHelper::createHandler();

    $result = $middleware->process($request, $next);

    expect($result->getHeader('Strict-Transport-Security'))->toBeNull();
});

// ==================== HEADER PRESERVATION ====================

it('preserves existing security headers from response', function () {
    EnvironmentTestHelper::setDevelopment();
    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], []);

    $next = new class() implements \Framework\Interfaces\RequestHandlerInterface
    {
        public function handle(\Framework\Core\Request $request): Response
        {
            $response = new Response();
            $response->addHeader('X-Frame-Options', 'DENY');

            return $response;
        }
    };

    $result = $middleware->process($request, $next);

    // Custom value should be preserved
    expect($result->getHeader('X-Frame-Options'))->toBe('DENY');
});

it('preserves response body and status code', function () {
    EnvironmentTestHelper::setDevelopment();
    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], []);

    $bodyContent = $this->faker->paragraph();
    $statusCode = 201;

    $next = new class($bodyContent, $statusCode) implements \Framework\Interfaces\RequestHandlerInterface
    {
        public function __construct(private string $body, private int $status) {}

        public function handle(\Framework\Core\Request $request): Response
        {
            $response = new Response();
            $response->setBody($this->body);
            $response->setStatusCode($this->status);

            return $response;
        }
    };

    $result = $middleware->process($request, $next);

    expect($result->getBody())->toBe($bodyContent)
        ->and($result->getStatusCode())->toBe($statusCode);
});

// ==================== ENVIRONMENT EDGE CASES ====================

it('handles missing APP_ENV gracefully', function () {
    // Don't set APP_ENV at all
    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], []);
    $next = MiddlewareTestHelper::createHandler();

    // Should not throw exception
    $result = $middleware->process($request, $next);

    expect($result)->toBeInstanceOf(Response::class);
});

it('does not add HSTS in staging environment', function () {
    EnvironmentTestHelper::setStaging();
    EnvironmentTestHelper::enableHttps();

    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], ['HTTPS' => 'on'], []);
    $next = MiddlewareTestHelper::createHandler();

    $result = $middleware->process($request, $next);

    // Staging behaves like development - no HSTS
    expect($result->getHeader('Strict-Transport-Security'))->toBeNull();
});

it('does not obfuscate Server header in staging', function () {
    EnvironmentTestHelper::setStaging();
    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], []);

    $serverHeader = 'Apache/2.4.41';
    $next = new class($serverHeader) implements \Framework\Interfaces\RequestHandlerInterface
    {
        public function __construct(private string $header) {}

        public function handle(\Framework\Core\Request $request): Response
        {
            $response = new Response();
            $response->addHeader('Server', $this->header);

            return $response;
        }
    };

    $result = $middleware->process($request, $next);

    // Staging shows real server header
    expect($result->getHeader('Server'))->toBe($serverHeader);
});

// ==================== COMBINATION TESTS ====================

it('applies all production security measures simultaneously', function () {
    EnvironmentTestHelper::setProduction();
    EnvironmentTestHelper::enableHttps();

    $middleware = new SecurityHeadersMiddleware();

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], ['HTTPS' => 'on'], []);

    $next = new class() implements \Framework\Interfaces\RequestHandlerInterface
    {
        public function handle(\Framework\Core\Request $request): Response
        {
            $response = new Response();
            $response->addHeader('Server', 'Apache/2.4.41');

            return $response;
        }
    };

    $result = $middleware->process($request, $next);

    // All security headers + obfuscation + HSTS
    expect($result->getHeader('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($result->getHeader('X-Content-Type-Options'))->toBe('nosniff')
        ->and($result->getHeader('X-XSS-Protection'))->toBe('1; mode=block')
        ->and($result->getHeader('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($result->getHeader('Server'))->toBe('WebServer')
        ->and($result->getHeader('Strict-Transport-Security'))->not->toBeNull();
});
