<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\PostModel;
use Framework\Core\Response;
use Framework\Helpers\RateLimiter;

/**
 * Answers the slug field while the writer types: is this address usable, and if a
 * post's address is taken, which numbered variant saving will use instead.
 */
class SlugCheckController extends AppController
{
    private const MAX_CHECKS_PER_MINUTE = 60;

    public function __construct(
        private PostModel $posts,
        private BlogModel $blogs,
        private RateLimiter $limiter
    ) {}

    /**
     * GET ?type=post&blog_id=6&slug=hello or ?type=blog&slug=hello
     */
    public function check(): Response
    {
        $user = auth()->user();
        $key = 'slug-check:'.(int) $user['id'];
        $this->limiter->hit($key, 60);
        if ($this->limiter->tooManyAttempts($key, self::MAX_CHECKS_PER_MINUTE, 60)) {
            return $this->json(['error' => 'Too many checks. Wait a moment and keep typing.'], 429);
        }

        $type = (string) ($this->request->get['type'] ?? '');
        $slug = (string) ($this->request->get['slug'] ?? '');

        return match ($type) {
            'post' => $this->checkPost($user, $slug),
            'blog' => $this->checkBlog($slug),
            default => $this->json(['error' => 'Unknown address type.'], 400),
        };
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function checkPost(array $user, string $slug): Response
    {
        $blog = $this->blogs->getBlog((int) ($this->request->get['blog_id'] ?? 0));
        if (!$blog || !Gate::allows('createPost', $blog, $user)) {
            return $this->json(['error' => 'You cannot write in this blog.'], 403);
        }

        $problem = $this->formatProblem($slug, 100);
        if ($problem !== null) {
            return $this->json(['slug' => $slug, 'available' => false, 'message' => $problem]);
        }

        $exceptId = (int) ($this->request->get['except'] ?? 0);
        $saved = $this->posts->availableSlug((int) $blog->id(), $slug, $exceptId ?: null);

        return $this->json([
            'slug' => $slug,
            'available' => $saved === $slug,
            'saved_as' => $saved,
        ]);
    }

    private function checkBlog(string $slug): Response
    {
        $problem = $this->formatProblem($slug, 50);
        if ($problem === null && $this->blogs->getBlogBySlug($slug) !== null) {
            $problem = 'Another blog already uses this address. Try a different one.';
        }

        return $this->json([
            'slug' => $slug,
            'available' => $problem === null,
            'message' => $problem,
        ]);
    }

    private function formatProblem(string $slug, int $max): ?string
    {
        $validator = $this->validator(['slug' => $slug]);
        $validator->rules(['slug' => 'required|slug|min:2|max:'.$max]);

        return $validator->fails()
            ? 'Use 2 to '.$max.' lowercase letters, numbers and single hyphens, with no hyphen at the start or end.'
            : null;
    }
}
