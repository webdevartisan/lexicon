<?php

declare(strict_types=1);

namespace App\Middleware;

use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;
use Framework\Security\Csp;

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

    public function __construct(
        private Csp $csp = new Csp()
    ) {
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
     * script-src uses a per-request nonce (see Csp::getNonce()) instead of
     * 'unsafe-inline': inline scripts that carry the matching nonce attribute
     * still run, but injected/extension/attacker scripts don't have it and
     * get blocked. csp_nonce() (in helpers.php) exposes the same value to
     * templates so <script nonce="<?= csp_nonce() ?>"> tags line up with the
     * header.
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

        $enforced = filter_var(env('CSP_ENFORCE', false), FILTER_VALIDATE_BOOLEAN);
        $nonce = $this->csp->getNonce();

        // Extension-injected scripts (chrome-extension:/moz-extension:/etc.)
        // never carry our nonce and would otherwise spam the report endpoint
        // with noise we can't fix from app code. Only allow them through in
        // report-only mode, never once enforcement is on.
        $scriptSrc = $enforced
            ? "script-src 'self' 'nonce-{$nonce}'"
            : "script-src 'self' 'nonce-{$nonce}' chrome-extension: moz-extension: safari-extension:";

        // Everything this app loads is first-party (/assets, /cp-assets,
        // /themes, /uploads); data: covers inlined icons and editor previews.
        $csp = implode('; ', [
            "default-src 'self'",
            $scriptSrc,
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "img-src 'self' data: https://picsum.photos https://fastly.picsum.photos https://cdn.jsdelivr.net https://www.gravatar.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            'report-uri /csp-report',
        ]);

        $header = $enforced ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';

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
