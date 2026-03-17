<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Exceptions\UnauthorizedException;
use Framework\Interfaces\AuthInterface;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;

/**
 * Ensures that the authenticated user has at least one of the required roles.
 *
 * Used via the "role" middleware alias in config/middleware.php, e.g.:
 *   'middleware' => ['auth', 'role:administrator']
 *
 * Behavior:
 *   - If the user is not authenticated, redirect to login (HTML) or throw 401 (API).
 *   - If authenticated but lacks roles, return 403 (HTML) or throw 403 (API).
 */
final class RequireRoleMiddleware implements MiddlewareInterface
{
    /**
     * @param  string[]  $roles  Roles required to pass this middleware.
     */
    public function __construct(
        private AuthInterface $auth,
        private array $roles
    ) {}

    /**
     * @throws UnauthorizedException
     */
    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        // 1. Not authenticated at all → treat as 401 / redirect to login
        if (!$this->auth->check()) {
            $accept = $request->header('accept') ?? '';

            if ($this->expectsJson($accept)) {
                throw new UnauthorizedException('Authentication required to access this resource.', 401);
            }

            $response = new Response();
            $response->redirect('/login');

            return $response;
        }

        // 2. Authenticated: check if user has at least one required role
        foreach ($this->roles as $role) {
            if ($this->auth->hasRole($role)) {
                return $next->handle($request);
            }
        }

        // 3. Authenticated but missing required roles → 403
        $accept = $request->header('accept') ?? '';

        if ($this->expectsJson($accept)) {
            throw new UnauthorizedException('You do not have permission to access this resource.', 403);
        }

        $response = new Response();
        $response->setStatusCode(403);

        return $response;
    }

    private function expectsJson(string $accept): bool
    {
        return stripos($accept, 'application/json') !== false
            || stripos($accept, 'text/json') !== false;
    }
}
