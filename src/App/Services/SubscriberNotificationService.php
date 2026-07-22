<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\NewPostMail;
use App\Models\BlogModel;
use App\Models\BlogSubscriberModel;
use App\Models\PostModel;
use Framework\Database;

/**
 * Fans out new-post emails to a blog's subscribers.
 *
 * Each post is announced at most once: subscribers_notified_at is stamped
 * before sending, so republishing or double-clicking publish never re-sends.
 */
class SubscriberNotificationService
{
    public function __construct(
        private Database $database,
        private PostModel $postModel,
        private BlogModel $blogModel,
        private BlogSubscriberModel $subscriberModel,
    ) {}

    /**
     * Notify subscribers about a freshly published post.
     *
     * @return int Number of emails sent
     */
    public function notifyPostPublished(int $postId): int
    {
        $post = $this->postModel->find((string) $postId);

        if (!$post || $post['status'] !== 'published' || ($post['visibility'] ?? 'public') !== 'public') {
            return 0;
        }

        if (!empty($post['subscribers_notified_at'])) {
            return 0;
        }

        $blog = $this->blogModel->find((string) $post['blog_id']);

        if (!$blog) {
            return 0;
        }

        // Stamp first so a concurrent publish cannot fan out twice
        $this->database->execute(
            'UPDATE posts SET subscribers_notified_at = NOW() WHERE id = ? AND subscribers_notified_at IS NULL',
            [$postId]
        );

        $sent = 0;

        foreach ($this->subscriberModel->forBlog((int) $post['blog_id']) as $subscriber) {
            $ok = mailer()->send(new NewPostMail(
                (string) $subscriber['email'],
                (string) ($blog['blog_name'] ?? 'A blog you follow'),
                (string) $post['title'],
                (string) $blog['blog_slug'],
                (string) $post['slug'],
                (string) $subscriber['token']
            ));

            if ($ok) {
                $sent++;

                continue;
            }

            // Naming the address makes a partial fan-out diagnosable; the
            // transport's own reason is already in the log from MailService.
            error_log(sprintf(
                'Subscriber notification failed for post %d, subscriber %s',
                $postId,
                $subscriber['email']
            ));
        }

        return $sent;
    }
}
