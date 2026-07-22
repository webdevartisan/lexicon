<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Interfaces\SchedulableCommandInterface;
use App\Models\PostModel;
use App\Services\PublicCacheInvalidator;
use App\Services\SubscriberNotificationService;
use Throwable;

/**
 * CLI command that promotes scheduled posts once their publish time arrives.
 *
 * Scheduling relies on a distinct 'scheduled' status rather than a date check
 * inside every public query, so something has to make the transition. That is
 * this command. Each post is flipped with a status guard, which makes a second
 * concurrent run a no-op rather than a double announcement.
 *
 * Subscriber mail is only queued here; mail:queue-work delivers it.
 *
 * Usage: php cli posts:publish-due
 * Cron:  * * * * * cd /var/www/html && php cli posts:publish-due >> /var/log/publish-due.log 2>&1
 */
class PublishDuePostsCommand implements SchedulableCommandInterface
{
    public function __construct(
        private PostModel $posts,
        private SubscriberNotificationService $subscriberNotifier,
        private PublicCacheInvalidator $publicCache,
    ) {}

    public static function scheduleLabel(): string
    {
        return 'Publish scheduled posts';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function argumentSchema(): array
    {
        return [];
    }

    /**
     * Publish everything that is due.
     *
     * @return int Exit code (0 = success, 1 = failure)
     */
    public function handle(array $arguments = []): int
    {
        try {
            $start = microtime(true);
            $due = $this->posts->dueForPublishing();

            if ($due === []) {
                echo "No posts due for publishing.\n";

                return 0;
            }

            echo 'Found '.count($due)." post(s) due for publishing...\n";

            $published = 0;
            $queued = 0;
            $failed = 0;

            foreach ($due as $post) {
                $postId = (int) $post['id'];

                if (!$this->posts->markScheduledAsPublished($postId)) {
                    // Another sweep took this one; nothing left to do here.
                    continue;
                }

                $published++;
                echo "  ✓ #{$postId} {$post['title']}\n";

                // Announcements are best-effort. A queue write that fails must
                // not stop the rest of the batch from going live, and the post
                // is already published by this point either way.
                try {
                    // Runs after the flip because the notifier ignores
                    // anything that isn't already 'published'. Delivery itself
                    // is mail:queue-work's job; this only enqueues.
                    $queued += $this->subscriberNotifier->notifyPostPublished($postId);
                } catch (Throwable $e) {
                    $failed++;
                    echo "    ! queueing subscriber email failed: {$e->getMessage()}\n";
                }
            }

            if ($published > 0) {
                // Listings are full-page cached, so without this the post stays
                // invisible until the TTL expires.
                $this->publicCache->purgeBlogSurfaces();
                $this->publicCache->purgeHome();
                $this->publicCache->purgeExplore();

                // Also clear fragment caches for affected blogs so paginated lists
                // and category grids reflect the newly published post immediately.
                foreach ($due as $post) {
                    $this->posts->forgetBlogPostFragments((int) $post['blog_id']);
                }
            }

            $duration = round((microtime(true) - $start) * 1000, 2);

            echo "Published {$published} post(s), queued {$queued} subscriber email(s) in {$duration}ms\n";

            if ($failed > 0) {
                echo "{$failed} post(s) published but could not queue subscriber mail.\n";
            }

            return 0;
        } catch (Throwable $e) {
            echo "✗ Error publishing due posts: {$e->getMessage()}\n";
            echo "Stack trace:\n{$e->getTraceAsString()}\n";

            return 1;
        }
    }
}
