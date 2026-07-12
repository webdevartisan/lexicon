<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\CommentModel;
use App\Models\PostModel;
use App\Services\NotificationService;
use Framework\Core\Response;

class CommentController extends AppController
{
    private const MIN_LENGTH = 2;

    private const MAX_LENGTH = 2000;

    public function __construct(
        private PostModel $postModel,
        private CommentModel $commentModel,
        private BlogSettingsModel $blogSettings,
        private BlogModel $blogModel,
        private NotificationService $notifications,
    ) {}

    public function create(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $postId = (int) ($this->request->post['post_id'] ?? 0);
        $content = trim($this->request->post['content'] ?? '');

        $user = auth()->user();
        $userId = $user ? (int) $user['id'] : null;

        if ($postId <= 0 || $content === '') {
            $this->flash('error', 'Comment content is required.');

            return $this->redirectBack();
        }

        $length = mb_strlen($content);

        if ($length < self::MIN_LENGTH) {
            $this->flash('error', 'Your comment is too short.');

            return $this->redirectBack();
        }

        if ($length > self::MAX_LENGTH) {
            $this->flash('error', 'Your comment is too long. Maximum '.self::MAX_LENGTH.' characters.');

            return $this->redirectBack();
        }

        $post = $this->postModel->find((string) $postId);

        if (!$post) {
            $this->flash('error', 'Post not found.');

            return $this->redirect('/');
        }

        if ($post['status'] !== 'published' || $post['visibility'] !== 'public') {
            $this->flash('error', 'Comments are not available for this post.');

            return $this->redirectBack();
        }

        $parentId = (int) ($this->request->post['parent_comment_id'] ?? 0);

        if ($parentId > 0) {
            // Replies require an account; guests may only post top-level comments
            if ($userId === null) {
                $this->flash('error', 'Please log in to reply to comments.');

                return $this->redirectBack();
            }

            $parent = $this->commentModel->findApprovedParent($parentId, $postId);

            if (!$parent) {
                $this->flash('error', 'The comment you are replying to is unavailable.');

                return $this->redirectBack();
            }

            // Keep threads one level deep: replying to a reply attaches to its top-level parent
            if (!empty($parent['parent_comment_id'])) {
                $parentId = (int) $parent['parent_comment_id'];
            }
        }

        $isReply = $parentId > 0;
        $autoPublish = $isReply
            ? $this->blogSettings->repliesAutoPublish((int) $post['blog_id'])
            : $this->blogSettings->commentsAutoPublish((int) $post['blog_id']);

        $comment = [
            'post_id' => $postId,
            'user_id' => $userId,
            'parent_comment_id' => $isReply ? $parentId : null,
            'content' => $content,
            'status' => $autoPublish ? 'approved' : 'pending',
        ];

        if (!$this->commentModel->insert($comment)) {
            $this->flash('error', 'Failed to create comment. Please try again.');

            return $this->redirectBack();
        }

        audit()->log(
            $userId ?? 0,
            'comment.created',
            'comment',
            $this->commentModel->getInsertID(),
            [
                'post_id' => $postId,
                'is_guest' => $userId === null,
                'parent_comment_id' => $isReply ? $parentId : null,
            ],
            $this->request->ip()
        );

        $this->notifyBlogTeam($post, $content, $user, !$autoPublish);

        if ($autoPublish) {
            $this->flash('success', $isReply ? 'Reply posted.' : 'Comment posted.');
        } else {
            $this->flash('success', 'Thanks! Your comment was submitted and is awaiting moderation.');
        }

        return $this->redirectBack();
    }

    /**
     * Notify the blog owner, active editors, and the post author about a new
     * comment. The commenter is skipped so people don't get notified about
     * their own comments; recipients can opt out via notify_comments.
     *
     * @param  array<string, mixed>  $post  The commented post row
     * @param  string  $content  Raw comment text
     * @param  array<string, mixed>|null  $commenter  Authenticated commenter, null for guests
     * @param  bool  $awaitingModeration  Whether the comment is held for approval
     */
    private function notifyBlogTeam(array $post, string $content, ?array $commenter, bool $awaitingModeration): void
    {
        $blog = $this->blogModel->getBlog((int) $post['blog_id']);
        if (!$blog) {
            return;
        }

        $recipients = [$blog->ownerId(), (int) ($post['author_id'] ?? 0)];
        foreach ($blog->users() as $member) {
            if ((int) ($member['is_active'] ?? 0) === 1
                && $this->blogModel->baseRoleFor((string) ($member['role'] ?? '')) === 'editor') {
                $recipients[] = (int) $member['user_id'];
            }
        }

        $commenterId = $commenter ? (int) $commenter['id'] : null;
        $recipients = array_unique(array_filter($recipients, static fn (int $id): bool => $id > 0 && $id !== $commenterId));

        $payload = [
            'post_id' => (int) $post['id'],
            'post_title' => (string) ($post['title'] ?? ''),
            'post_slug' => (string) ($post['slug'] ?? ''),
            'blog_id' => (int) $post['blog_id'],
            'blog_slug' => $blog->slug(),
            'commenter_name' => $commenter
                ? (string) ($commenter['display_name'] ?? $commenter['username'] ?? 'A reader')
                : 'A guest',
            'comment_excerpt' => truncate($content, 140),
            'awaiting_moderation' => $awaitingModeration,
        ];

        foreach ($recipients as $userId) {
            $this->notifications->dispatch($userId, 'comment.created', $payload);
        }
    }
}
