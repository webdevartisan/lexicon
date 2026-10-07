<?php

declare(strict_types=1);

namespace App\Middleware;

use Framework\Interfaces\TemplateViewerInterface;
use Framework\Session;

/**
 * PreviousPathMiddleware
 *
 * Tracks the user's previous internal path using the session.
 *
 * - Reads previous path from session and exposes it to templates.
 * - Updates session with the current path for the next request.
 * - Ignores external URLs and non-path values.
 */
class PreviousPathMiddleware
{
    /**
     * @var Session Session manager
     */
    private Session $session;

    /**
     * @var TemplateViewerInterface Template viewer for adding globals
     */
    private TemplateViewerInterface $viewer;

    public function __construct(Session $session, TemplateViewerInterface $viewer)
    {
        $this->session = $session;
        $this->viewer = $viewer;
    }

    /**
     * Handle middleware processing
     *
     * @param  mixed  $request  Request object (with ->uri or similar)
     * @param  mixed  $handler  Next handler in the chain
     * @return mixed Response from handler
     */
    public function process($request, $handler)
    {
        // PRE: read previous path from session
        $previousPath = $this->session->get('last_path', '/');

        // Ensure it's a safe internal path (no external URLs, no protocol-relative, etc.)
        if (!is_string($previousPath)
            || !str_starts_with($previousPath, '/')
            || str_starts_with($previousPath, '//')
        ) {
            $previousPath = '/';
        }

        // Current path from request
        $path = $request->uri ?? '/';
        $currentPath = parse_url($path, PHP_URL_PATH) ?: '/';

        // Expose to templates before rendering
        $this->viewer->addGlobals([
            'previous_path' => $previousPath,
            'current_path' => $currentPath,
        ]);

        // Run the rest of the application
        $response = $handler->handle($request);

        // Only store GET requests in session (ignore POST/PUT/DELETE, etc.)
        if (!$request->isPost()) {
            $this->session->set('last_path', $currentPath);
        }

        return $response;
    }
}
