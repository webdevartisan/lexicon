<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\AnalyticsEventModel;
use App\Models\BlogModel;
use App\Models\PostModel;
use Framework\Core\Request;

/**
 * Notes a reader reaching a goal on a blog: subscribing, commenting, liking or
 * saving a post. The event joins the reader's visit when there is one, never
 * their account. The people whose views don't count don't count here either.
 */
class GoalRecorder
{
    public function __construct(
        private AnalyticsSettings $settings,
        private UserAgentClassifier $agents,
        private VisitFinder $finder,
        private AnalyticsEventModel $events,
        private EventRegistry $registry,
        private BlogModel $blogs,
        private PostModel $posts,
    ) {}

    /**
     * @param  array<string, mixed>|null  $viewer
     */
    public function onBlog(Request $request, string $goal, int $blogId, ?array $viewer): void
    {
        if ($this->counts($request, $blogId, $viewer)) {
            $this->store($request, $goal, $blogId, null, $viewer);
        }
    }

    /**
     * @param  array<string, mixed>|null  $viewer
     */
    public function onPost(Request $request, string $goal, int $postId, ?array $viewer): void
    {
        $post = $this->posts->find($postId);
        if ($post === null) {
            return;
        }

        $blogId = (int) $post['blog_id'];

        if ($this->counts($request, $blogId, $viewer)) {
            $this->store($request, $goal, $blogId, $postId, $viewer);
        }
    }

    /**
     * @param  array<string, mixed>|null  $viewer
     */
    private function store(Request $request, string $goal, int $blogId, ?int $postId, ?array $viewer): void
    {
        if (!$this->registry->has($goal)) {
            throw new \InvalidArgumentException("Unknown goal '{$goal}'.");
        }

        $local = new \DateTimeImmutable('now', new \DateTimeZone(blog_timezone($blogId)));

        $this->events->recordInVisit([
            'name' => $goal,
            'blog_id' => $blogId,
            'post_id' => $postId,
            'local_date' => $local->format('Y-m-d'),
            'local_hour' => (int) $local->format('G'),
        ], $this->finder->open($request, $viewer), $this->registry->oncePerVisit($goal));
    }

    /**
     * @param  array<string, mixed>|null  $viewer
     */
    private function counts(Request $request, int $blogId, ?array $viewer): bool
    {
        if (!$this->settings->enabled() || AnalyticsRecorder::optedOut($request)) {
            return false;
        }

        if ($this->agents->isBot((string) $request->header('User-Agent', ''), $this->settings->extraBotPatterns())) {
            return false;
        }

        if ($viewer === null) {
            return true;
        }

        return !in_array('administrator', $viewer['roles'] ?? [], true)
            && !$this->blogs->userCanAccessBlog((int) $viewer['id'], $blogId);
    }
}
