<?php

declare(strict_types=1);

namespace App\Middleware;

use Framework\Cache\RateLimitStore;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;

/**
 * Refuses every request from an IP an admin has blocked by hand.
 *
 * Runs ahead of the page cache so a blocked visitor cannot be served a cached page.
 */
class BlockedIpMiddleware implements MiddlewareInterface
{
    public function __construct(private RateLimitStore $store) {}

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $ip = $request->ip();

        if ($ip !== null && $this->store->get('manual:hardlock:'.$ip) !== null) {
            return (new Response())->html('<!doctype html><title>Access blocked</title><h1>Access blocked</h1>'
                .'<p>Requests from your address have been blocked. Contact the site owner if you think this is a mistake.</p>', 403);
        }

        return $next->handle($request);
    }
}
