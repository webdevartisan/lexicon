<?php

declare(strict_types=1);

namespace App\Middleware;

use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;

/**
 * Security Headers Middleware
 *
 * set security-related HTTP headers on all responses.
 * This works at the application level, making it portable across
 * different server environments (Apache, Nginx, cloud hosting).
 *
 * NOTE: For static files served directly by the web server,
 * headers must still be set in .htaccess or nginx.conf.
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    private bool $isProduction;

    public function __construct()
    {
        $this->isProduction = ($_ENV['APP_ENV'] ?? 'development') === 'production';
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        // let the request continue
        $response = $next->handle($request);

        // apply security headers to the response
        $this->applySecurityHeaders($response);

        return $response;
    }

    /**
     * Apply security headers
     *
     * set headers that protect against common web vulnerabilities.
     * Some headers are only set in production to avoid breaking dev tools.
     */
    private function applySecurityHeaders(Response $response): void
    {
        // obfuscate server information (production only)
        if ($this->isProduction) {
            $response->removeHeader('Server');
            $response->removeHeader('X-Powered-By');
            $response->addHeader('Server', 'WebServer');
        }

        // prevent clickjacking attacks
        if (!$response->hasHeader('X-Frame-Options')) {
            $response->addHeader('X-Frame-Options', 'SAMEORIGIN');
        }

        // prevent MIME-type sniffing
        if (!$response->hasHeader('X-Content-Type-Options')) {
            $response->addHeader('X-Content-Type-Options', 'nosniff');
        }

        // enable XSS protection (legacy browsers)
        if (!$response->hasHeader('X-XSS-Protection')) {
            $response->addHeader('X-XSS-Protection', '1; mode=block');
        }

        // set referrer policy
        if (!$response->hasHeader('Referrer-Policy')) {
            $response->addHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        // enforce HTTPS in production
        if ($this->isProduction && $this->isHttps()) {
            if (!$response->hasHeader('Strict-Transport-Security')) {
                $response->addHeader(
                    'Strict-Transport-Security',
                    'max-age=31536000; includeSubDomains; preload'
                );
            }
        }

        // Optional: Content Security Policy (customize for your needs)
        // Uncomment and configure when ready:
        // if (!$response->hasHeader('Content-Security-Policy')) {
        //     $csp = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'";
        //     $response->addHeader('Content-Security-Policy', $csp);
        // }
    }

    /**
     * Check if current request is HTTPS
     */
    private function isHttps(): bool
    {
        return
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
}
