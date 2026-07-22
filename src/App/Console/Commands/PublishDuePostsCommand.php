<?php

declare(strict_types=1);

namespace App\Console\Commands;

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
 * Usage: php cli posts:publish-due
 * Cron:  * * * * * cd /var/www/html && php cli posts:publish-due >> /var/log/publish-due.log 2>&1
 */
class PublishDuePostsCommand
{
    public function __construct(
        private PostModel $posts,
        private SubscriberNotificationService $subscriberNotifier,
        private PublicCacheInvalidator $publicCache,
    ) {}

    /**
     * Publish everything that is due.
     *
     * @return int Exit code (0 = success, 1 = failure)
     */
    public function handle(): int
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
            $notified = 0;
            $failed = 0;

            foreach ($due as $post) {
                $postId = (int) $post['id'];

                if (!$this->posts->markScheduledAsPublished($postId)) {
                    // Another sweep took this one; nothing left to do here.
                    continue;
                }

                $published++;
                echo "  ✓ #{$postId} {$post['title']}\n";

                // Announcements are best-effort. A dead mail server must not
                // stop the rest of the batch from going live, and the post is
                // already published by this point either way.
                try {
                    // Runs after the flip because the notifier ignores
                    // anything that isn't already 'published'.
                    $notified += $this->subscriberNotifier->notifyPostPublished($postId);
                } catch (Throwable $e) {
                    $failed++;
                    echo "    ! subscriber email failed: {$e->getMessage()}\n";
                }
            }

            if ($published > 0) {
                // Listings are full-page cached, so without this the post stays
                // invisible until the TTL expires.
                $this->publicCache->purgeBlogSurfaces();
                $this->publicCache->purgeHome();
                $this->publicCache->purgeExplore();
            }

            $duration = round((microtime(true) - $start) * 1000, 2);

            echo "Published {$published} post(s), {$notified} subscriber email(s) sent in {$duration}ms\n";

            if ($failed > 0) {
                echo "{$failed} post(s) published but could not notify subscribers.\n";
            }

            return 0;
        } catch (Throwable $e) {
            echo "✗ Error publishing due posts: {$e->getMessage()}\n";
            echo "Stack trace:\n{$e->getTraceAsString()}\n";

            return 1;
        }
    }
}
