<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use App\Controllers\ModuleController;
use App\Models\BlogModel;
use App\Models\BlogModuleModel;
use App\Services\BlogCacheInvalidator;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Saves and removes a post's place for the editor's Place panel.
 *
 * The manage-blog-extra route guard has already checked that the user may change
 * the blog; this controller makes sure the post belongs to that blog.
 */
final class StopController extends ModuleController
{
    public function __construct(
        BlogModuleModel $blogModules,
        private StopModel $stops,
        private BlogModel $blogs,
        private BlogCacheInvalidator $blogCache,
    ) {
        parent::__construct($blogModules);
    }

    /**
     * Give the post its place, or answer 422 with a message per wrong field.
     */
    public function save(string $blogId, string $postId): Response
    {
        $slug = $this->blogSlugFor((int) $blogId, (int) $postId);

        try {
            $stop = Stop::fromInput($this->request->postParams());
        } catch (InvalidStopException $e) {
            return $this->json(['errors' => array_map('chrome_translate', $e->errors)], 422);
        }

        $this->stops->save((int) $blogId, (int) $postId, $stop);
        $this->blogCache->forgetBlog($slug);

        return $this->json([
            'stop' => $stop->toRow(),
            'message' => chrome_translate('journey-map::editor.saved'),
        ]);
    }

    /**
     * Take the post's place away.
     */
    public function remove(string $blogId, string $postId): Response
    {
        $slug = $this->blogSlugFor((int) $blogId, (int) $postId);

        $this->stops->remove((int) $postId);
        $this->blogCache->forgetBlog($slug);

        return $this->json(['removed' => true, 'message' => chrome_translate('journey-map::editor.removed')]);
    }

    /**
     * The module whose switch decides if these endpoints exist on a blog.
     */
    protected function moduleKey(): string
    {
        return 'journey-map';
    }

    /**
     * The blog's address, once the module is on for it and the post is one of its posts.
     *
     * @throws PageNotFoundException
     */
    private function blogSlugFor(int $blogId, int $postId): string
    {
        $this->requireActive($blogId);

        $blog = $this->blogs->getBlog($blogId);
        if ($blog === false || !$this->stops->postInBlog($postId, $blogId)) {
            throw new PageNotFoundException('This post is not part of this blog.');
        }

        return (string) $blog->toArray()['blog_slug'];
    }
}
