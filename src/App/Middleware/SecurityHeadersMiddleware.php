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
        $this->isProduction = (env('APP_ENV', 'development')) === 'production';
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

        $this->applyContentSecurityPolicy($response);
    }

    /**
     * Attach a Content-Security-Policy, report-only until it is proven clean.
     *
     * Ships in report-only mode so a missed inline script shows up in the
     * console instead of breaking the page. Flip CSP_ENFORCE=true once the
     * report-only run is quiet.
     *
     * Note the policy deliberately omits 'unsafe-inline' for script-src: with
     * it, the policy would still allow exactly the injected-script attack it
     * exists to stop, which is why the previous commented-out block was
     * decorative rather than protective.
     *
     * Known remaining inline scripts to clear before enforcing: the per-page
     * TinyMCE/Dropzone init blocks. The window.AppLocales block is gone, dropped
     * with the client-side locale rewriting it existed to feed.
     *
     * style-src/font-src allow Google Fonts because cp-assets/css/fonts.css
     * and every theme's base layout pull Public Sans (and theme-specific
     * families) from fonts.googleapis.com/fonts.gstatic.com - those aren't
     * stray third-party calls, they're how this app has always served fonts.
     */
    private function applyContentSecurityPolicy(Response $response): void
    {
        if ($response->hasHeader('Content-Security-Policy')
            || $response->hasHeader('Content-Security-Policy-Report-Only')) {
            return;
        }

        // Everything this app loads is first-party (/assets, /cp-assets,
        // /themes, /uploads); data: covers inlined icons and editor previews.
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "img-src 'self' data:",
            "font-src 'self' data: https://fonts.gstatic.com",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);

        $header = filter_var(env('CSP_ENFORCE', false), FILTER_VALIDATE_BOOLEAN)
            ? 'Content-Security-Policy'
            : 'Content-Security-Policy-Report-Only';

        $response->addHeader($header, $csp);
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
