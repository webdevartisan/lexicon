<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;

/**
 * Answers a browser's "has this page changed?" check with an empty 304.
 *
 * Public pages ask browsers to check with the server before reusing a copy
 * (Cache-Control: no-cache). This tags each of those pages with a hash of its
 * body, so a browser that already holds that exact page gets no body back.
 * It runs outside every middleware that rewrites the body, so the hash is
 * taken from the page that is actually sent.
 */
final class ConditionalGetMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $response = $next->handle($request);

        if ($request->method !== 'GET' || !$this->isSharedAndCheckedFirst($response)) {
            return $response;
        }

        $etag = '"'.hash('xxh128', $response->getBody()).'"';
        $response->addHeader('ETag', $etag);

        if ($this->browserHolds($request->header('If-None-Match'), $etag)) {
            $response->setStatusCode(304);
            $response->setBody('');
        }

        return $response;
    }

    /**
     * Only shared pages are tagged. Signed-in views and pages showing a one-off
     * message are private, and no-store pages must not be kept at all.
     */
    private function isSharedAndCheckedFirst(Response $response): bool
    {
        $policy = strtolower($response->getHeader('Cache-Control') ?? '');

        return $response->getStatusCode() === 200
            && !$response->hasHeader('ETag')
            && str_contains($policy, 'no-cache')
            && !str_contains($policy, 'private')
            && !str_contains($policy, 'no-store');
    }

    /**
     * If-None-Match may list several tags and compares them weakly, so a W/
     * prefix doesn't count as a difference.
     */
    private function browserHolds(?string $ifNoneMatch, string $etag): bool
    {
        foreach (explode(',', (string) $ifNoneMatch) as $candidate) {
            if (preg_replace('#^W/#', '', trim($candidate)) === $etag) {
                return true;
            }
        }

        return false;
    }
}
