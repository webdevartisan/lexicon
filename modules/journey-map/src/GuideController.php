<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use App\Auth;
use App\Controllers\ModuleController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\BlogModuleModel;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * The blog's Journey map page in the dashboard: how the module works and every stop the blog has.
 */
final class GuideController extends ModuleController
{
    public function __construct(
        BlogModuleModel $blogModules,
        private StopModel $stops,
        private BlogModel $blogs,
        private Auth $auth,
    ) {
        parent::__construct($blogModules);
    }

    /**
     * Show the guide and the stop list to people who may change the blog.
     *
     * @throws PageNotFoundException When the blog is missing or the module is off for it
     * @throws \Framework\Exceptions\UnauthorizedException When the viewer may not change the blog
     */
    public function show(string $blogId): Response
    {
        $blog = $this->blogs->getBlog((int) $blogId);
        if ($blog === false) {
            throw new PageNotFoundException('Blog not found.');
        }
        Gate::authorize('update', $blog, (array) $this->auth->user());
        $this->requireActive((int) $blogId);

        $row = $blog->toArray();

        return $this->view('journey-map::guide', [
            'blog' => $row,
            'stops' => $this->forOwner($this->stops->forBlog((int) $blogId)),
            'journeyUrl' => '/blog/'.rawurlencode((string) $row['blog_slug']).'/m/journey-map',
        ]);
    }

    /**
     * The module whose switch decides if this page exists on a blog.
     */
    protected function moduleKey(): string
    {
        return 'journey-map';
    }

    /**
     * Each stop as the guide's table shows it, flagged when readers cannot see its post.
     *
     * @param list<array<string, mixed>> $stops
     * @return list<array<string, mixed>>
     */
    private function forOwner(array $stops): array
    {
        return array_map(static fn (array $stop): array => [
            'title' => (string) $stop['title'],
            'place' => (string) $stop['place_name'],
            'precision' => (string) $stop['place_precision'],
            'number' => $stop['stop_number'] === null ? null : (int) $stop['stop_number'],
            'status' => (string) $stop['status'],
            'public' => $stop['status'] === 'published' && $stop['visibility'] === 'public',
            'edit' => '/dashboard/post/'.(int) $stop['post_id'].'/edit',
        ], $stops);
    }
}
