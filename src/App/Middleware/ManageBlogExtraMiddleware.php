<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth;
use App\Gate;
use App\Models\BlogModel;
use App\Models\BlogModuleModel;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;

/**
 * Guards a blog module's settings pages under /dashboard/blog/{blogId}/m/<key>.
 *
 * Only someone who may edit the blog gets in, the same check as the blog's Extras page,
 * and only while the module is switched on for that blog. Living on the route group,
 * it covers every module page even when a module controller forgets its own check.
 */
final class ManageBlogExtraMiddleware implements MiddlewareInterface
{
    /** The route already fixed this shape; an optional language segment may come first. */
    private const PATH = '~^(?:/[^/]+)?/dashboard/blog/(\d+)/m/([a-z][a-z0-9-]{1,40})(?:/|$)~';

    public function __construct(
        private Auth $auth,
        private BlogModel $blogs,
        private BlogModuleModel $blogModules,
    ) {}

    /**
     * Let the request through only for an editor of the blog, with the module on for it.
     *
     * @throws PageNotFoundException When the blog is missing or the module is off for it
     * @throws \Framework\Exceptions\UnauthorizedException When the user may not edit the blog
     */
    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        if (!preg_match(self::PATH, $request->path(), $match)) {
            throw new PageNotFoundException('This page does not exist.');
        }
        [, $blogId, $moduleKey] = $match;

        $blog = $this->blogs->getBlog((int) $blogId);
        if ($blog === false) {
            throw new PageNotFoundException('This page does not exist.');
        }

        // Authorize before the on check, so someone outside the blog learns nothing about its extras.
        Gate::authorize('update', $blog, $this->auth->user() ?? []);

        if (!in_array($moduleKey, $this->blogModules->activeKeys((int) $blogId), true)) {
            throw new PageNotFoundException('This page is not available on this blog.');
        }

        return $next->handle($request);
    }
}
