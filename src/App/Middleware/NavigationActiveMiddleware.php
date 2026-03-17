<?php

declare(strict_types=1);

namespace App\Middleware;

use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Helpers\NavigationActiveInjector;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;

/**
 * Navigation active state middleware.
 *
 * inject active state into navigation links in the final HTML output.
 * This runs after all rendering and caching, ensuring active state
 * is always correct regardless of cache status.
 */
class NavigationActiveMiddleware implements MiddlewareInterface
{
    /**
     * Process the request and inject active state into response.
     *
     * modify the response HTML to add 'active' class to navigation
     * links matching the current page path.
     *
     * @param  callable  $next
     */
    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        // let the request complete first
        $response = $next->handle($request);

        // only process HTML responses
        $contentType = $response->getHeader('Content-Type') ?? '';
        if (!str_contains($contentType, 'text/html') && !empty($contentType)) {
            return $response;
        }

        // only process successful responses
        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        // get the response body
        $html = $response->getBody();

        // skip if no navigation markers found (optimization)
        if (!str_contains($html, 'data-nav-path')) {
            return $response;
        }

        // inject active state
        $currentPath = parse_url('/'.locale().$request->uri ?? '/', PHP_URL_PATH);
        $modifiedHtml = NavigationActiveInjector::inject($html, $currentPath);

        // update the response
        $response->setBody($modifiedHtml);

        return $response;
    }
}
