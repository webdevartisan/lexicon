<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogModel;
use App\Models\PostModel;

/**
 * Takes a draft blog live when its first post is published.
 *
 * A new blog starts as a draft, and a writer who publishes a post expects
 * people to be able to read it. Only the first post does this, so a blog the
 * owner took offline stays offline while its posts keep changing.
 */
final class BlogLaunchService
{
    public const LAUNCHED_MESSAGE = 'Published. Your blog is live now too, so anyone can read this post.';

    public function __construct(
        private BlogModel $blogs,
        private PostModel $posts,
    ) {}

    /**
     * Publish the post's blog when the post just became its only published one.
     *
     * @param  int  $postId  The post that was just published
     * @param  string  $previousStatus  The post's status before this publish
     * @return bool True when the blog went live
     */
    public function launchWithFirstPost(int $postId, string $previousStatus): bool
    {
        if ($previousStatus === 'published') {
            return false;
        }

        $post = $this->posts->find($postId);
        if (!$post || $post['status'] !== 'published' || ($post['visibility'] ?? 'public') === 'private') {
            return false;
        }

        $blog = $this->blogs->getBlog((int) $post['blog_id']);
        if (!$blog || $blog->status() !== 'draft') {
            return false;
        }

        if ($this->posts->countByBlogIdAndStatus($blog->id(), 'published') !== 1) {
            return false;
        }

        return $this->blogs->update($blog->id(), [
            'status' => 'published',
            'published_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
