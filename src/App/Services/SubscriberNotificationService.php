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
 * Announcements go onto the mail queue rather than out over SMTP here. A blog
 * with real subscriber numbers would otherwise send the whole list in one
 * request and get cut off partway by the provider's per-second limit, with the
 * remaining readers silently never notified.
 *
 * Each post is announced at most once: subscribers_notified_at is stamped
 * before queueing, so republishing or double-clicking publish never re-sends.
 */
class SubscriberNotificationService
{
    public function __construct(
        private Database $database,
        private PostModel $postModel,
        private BlogModel $blogModel,
        private BlogSubscriberModel $subscriberModel,
        private MailQueueService $mailQueue,
    ) {}

    /**
     * Queue a new-post announcement for every subscriber of the blog.
     *
     * @return int Number of emails queued
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

        // Stamp first so a concurrent publish cannot fan out twice. The guard
        // on the WHERE clause is what makes this safe: losing the race means
        // updating nothing, and the winner owns the announcement.
        $claimed = $this->database->execute(
            'UPDATE posts SET subscribers_notified_at = NOW() WHERE id = ? AND subscribers_notified_at IS NULL',
            [$postId]
        );

        if ($claimed === 0) {
            return 0;
        }

        $queued = 0;

        foreach ($this->subscriberModel->forBlog((int) $post['blog_id']) as $subscriber) {
            $queued += $this->mailQueue->enqueue(
                new NewPostMail(
                    (string) $subscriber['email'],
                    (string) ($blog['blog_name'] ?? 'A blog you follow'),
                    (string) $post['title'],
                    (string) $blog['blog_slug'],
                    (string) $post['slug'],
                    (string) $subscriber['token']
                ),
                'post',
                $postId
            );
        }

        return $queued;
    }
}
