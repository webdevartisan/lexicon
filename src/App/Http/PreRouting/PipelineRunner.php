<?php

declare(strict_types=1);

namespace App\Http\PreRouting;

use Framework\Core\Request;

/**
 * Pre-routing HTTP kernel.
 *
 * Runs a sequence of fast, security- and SEO-related checks and normalizations
 * before the main application dispatcher and middleware pipeline.
 *
 * Each step is a callable [ClassName::class, 'handle'] that receives the
 * current Request instance and may:
 *   - mutate it (e.g. normalize path, locale prefix),
 *   - send a redirect/response and exit (e.g. HTTPS redirect, maintenance mode),
 *   - or simply no-op and pass control to the next step.
 *
 * All steps should be idempotent and very cheap to run.
 */
final class PipelineRunner
{
    /**
     * @var array<int,array{0:class-string,1:string}>
     */
    private array $steps;

    public function __construct()
    {
        $this->steps = [
            [HttpsRedirector::class,        'handle'], // Enforce HTTPS, protect cookies
            [SubdomainNormalizer::class,    'handle'], // Canonical host/subdomain
            [MaintenanceModeGate::class,    'handle'], // Global maintenance / admin bypass
            // [RateLimitPrecheck::class,   'handle'], // Optional: IP/route rate limiting
            [UserAgentBlocklist::class,     'handle'], // Block known bad bots
            [HealthCheckBypass::class,      'handle'], // Cheap health/uptime endpoints
            // [LegacyUrlRewriter::class,   'handle'], // Optional: old URL redirects
            [CanonicalQueryKeys::class,     'handle'], // Normalize query string keys/order
            [PathCanonicalization::class,   'handle'], // Remove duplicate slashes, etc.
            [TrailingSlashNormalizer::class, 'handle'], // Consistent trailing slash policy
            [CacheControlHint::class,       'handle'], // Hint cache headers for static paths
            [LocaleAwareStaticBypass::class, 'handle'], // Fast‑path static assets with locale
            [LocalePrefixIntake::class,     'handle'], // Extract locale from URL prefix
        ];
    }

    /**
     * Run all pre-routing steps in order.
     *
     * Every step MUST be a pure function of (Request) with no return value;
     * steps that decide to short-circuit (redirect, 503) should send their
     * own Response and exit in a controlled way.
     */
    public function run(Request $request): void
    {
        foreach ($this->steps as [$class, $method]) {
            // Call static [ClassName, 'handle'] form for clarity
            $class::$method($request);
        }
    }
}
