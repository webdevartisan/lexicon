<?php

declare(strict_types=1);

namespace App\Services\Traffic;

use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\TrafficEventModel;
use Framework\Core\Request;

/**
 * Notes a reader reaching a goal on a blog: subscribing, commenting, liking or
 * saving a post. Only the count is kept, never who. The people whose views don't
 * count don't count here either.
 */
class GoalRecorder
{
    public function __construct(
        private TrafficSettings $settings,
        private UserAgentClassifier $agents,
        private TrafficEventModel $events,
        private BlogModel $blogs,
        private PostModel $posts,
    ) {}

    /**
     * @param  array<string, mixed>|null  $viewer
     */
    public function onBlog(Request $request, string $goal, int $blogId, ?array $viewer): void
    {
        if ($this->counts($request, $blogId, $viewer)) {
            $this->events->recordGoal($goal, $blogId, null, self::blogDay($blogId));
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
            $this->events->recordGoal($goal, $blogId, $postId, self::blogDay($blogId));
        }
    }

    /**
     * @param  array<string, mixed>|null  $viewer
     */
    private function counts(Request $request, int $blogId, ?array $viewer): bool
    {
        if (!$this->settings->enabled() || TrafficRecorder::optedOut($request)) {
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

    private static function blogDay(int $blogId): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(blog_timezone($blogId))))->format('Y-m-d');
    }
}
