<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth;
use App\Gate;
use App\Resources\SystemResource;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;

/**
 * Guards the control panel pages of site wide extras with the manage_extras permission.
 *
 * The check lives on the route group rather than in each module controller, so a module
 * that forgets its own check still cannot open its pages to everyone.
 */
final class ManageExtrasMiddleware implements MiddlewareInterface
{
    public function __construct(private Auth $auth) {}

    /**
     * Let the request through only for someone who may manage extras.
     *
     * @throws \Framework\Exceptions\UnauthorizedException When the signed in user lacks the permission
     */
    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        Gate::authorize('manageExtras', SystemResource::class, $this->auth->user() ?? []);

        return $next->handle($request);
    }
}
