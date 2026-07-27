<?php

declare(strict_types=1);

namespace App\Controllers;

use Framework\Core\Response;

/**
 * Collect Content-Security-Policy violation reports sent by the browser.
 *
 * The browser POSTs these itself (via the CSP report-uri directive) with no
 * CSRF token and no interactive session, so this route is exempted from
 * CsrfMiddleware - see its EXEMPT_PATHS.
 */
final class CspReportController extends AppController
{
    private const LOG_FILE = 'storage/logs/csp-violations.log';

    /**
     * blocked-uri/source-file prefixes that identify a browser extension as
     * the actor, not our own pages. Ad blockers, password managers, and
     * similar extensions inject scripts into every site their user visits,
     * and those injections get reported here exactly like a real violation -
     * across enough visitors this drowns out the violations we'd actually
     * need to act on.
     */
    private const EXTENSION_URI_PREFIXES = [
        'chrome-extension:',
        'moz-extension:',
        'safari-extension:',
        'safari-web-extension:',
        'ms-browser-extension:',
        'resource:',
        'webkit-masked-url:',
    ];

    public function store(): Response
    {
        $limiter = app(\App\Services\CspReportRateLimiter::class);
        $ip = $this->request->ip() ?? 'unknown';

        // Record first, then check - recording blocked attempts too is what
        // stops the decayed score sliding back under the limit and leaking a
        // trickle of requests to a client that just keeps flooding.
        $limiter->hit($ip);

        if ($limiter->tooManyAttempts($ip)) {
            $this->response->addHeader('Retry-After', (string) $limiter->availableIn($ip));
            $this->response->setStatusCode(429);

            return $this->response;
        }

        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);

        if (is_array($decoded) && $raw !== '') {
            $cspReport = $decoded['csp-report'] ?? $decoded;

            if (is_array($cspReport) && !$this->isExtensionNoise($cspReport)) {
                $this->logViolation($decoded);
            }
        }

        // Browsers ignore the response body/status for reports; 204 is the
        // honest answer since nothing is returned.
        $this->response->setStatusCode(204);

        return $this->response;
    }

    /**
     * @param  array<string,mixed>  $report
     */
    private function logViolation(array $report): void
    {
        $path = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.self::LOG_FILE;

        $line = sprintf(
            '[%s] %s%s',
            date('Y-m-d H:i:s'),
            json_encode($report, JSON_UNESCAPED_SLASHES),
            PHP_EOL
        );

        file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Detect violations caused by a browser extension rather than our pages.
     *
     * @param  array<string,mixed>  $cspReport
     */
    private function isExtensionNoise(array $cspReport): bool
    {
        $blockedUri = (string) ($cspReport['blocked-uri'] ?? $cspReport['blockedURI'] ?? '');
        $sourceFile = (string) ($cspReport['source-file'] ?? $cspReport['sourceFile'] ?? '');

        foreach (self::EXTENSION_URI_PREFIXES as $prefix) {
            if (str_starts_with($blockedUri, $prefix) || str_starts_with($sourceFile, $prefix)) {
                return true;
            }
        }

        // Firefox labels eval'd extension content-script code this way instead
        // of giving it a real source-file URI.
        return str_contains($sourceFile, 'sandbox eval code');
    }
}
