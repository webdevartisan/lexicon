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
        private LegSync $legs,
    ) {
        parent::__construct($blogModules);
    }

    /**
     * Give the post its places in the order sent, or answer 422 with the wrong fields of each place.
     * Every place carries the post's one stop number, so the journey keeps a post's places together.
     */
    public function save(string $blogId, string $postId): Response
    {
        $slug = $this->blogSlugFor((int) $blogId, (int) $postId);
        $input = $this->request->postParams();
        $places = $input['stops'] ?? null;
        if (!is_array($places) || !array_is_list($places) || $places === []
            || count($places) > StopModel::MAX_PER_POST) {
            $error = chrome_translate('journey-map::errors.stops', ['max' => StopModel::MAX_PER_POST]);

            return $this->json(['errors' => ['stops' => $error]], 422);
        }

        $stops = [];
        $errors = [];
        foreach ($places as $index => $place) {
            try {
                $stops[] = Stop::fromInput(['stop_number' => $input['stop_number'] ?? null] + (array) $place);
            } catch (InvalidStopException $e) {
                $errors[$index] = array_map('chrome_translate', $e->errors);
            }
        }
        if ($errors !== []) {
            return $this->json(['errors' => $errors], 422);
        }

        $this->stops->replace((int) $blogId, (int) $postId, $stops);
        $this->legs->sync((int) $blogId);
        $this->blogCache->forgetBlog($slug);

        return $this->json([
            'stops' => array_map(static fn (Stop $stop): array => $stop->toRow(), $stops),
            'message' => chrome_translate('journey-map::editor.saved'),
        ]);
    }

    /**
     * Take the post's places away.
     */
    public function remove(string $blogId, string $postId): Response
    {
        $slug = $this->blogSlugFor((int) $blogId, (int) $postId);

        $this->stops->remove((int) $postId);
        $this->legs->sync((int) $blogId);
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
