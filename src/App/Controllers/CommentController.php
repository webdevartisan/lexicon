<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BlogSettingsModel;
use App\Models\CommentModel;
use App\Models\PostModel;
use Framework\Core\Response;

class CommentController extends AppController
{
    private const MIN_LENGTH = 2;

    private const MAX_LENGTH = 2000;

    public function __construct(
        private PostModel $postModel,
        private CommentModel $commentModel,
        private BlogSettingsModel $blogSettings,
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
        $autoPublish = $isReply && $this->blogSettings->repliesAutoPublish((int) $post['blog_id']);

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

        $this->flash('success', $autoPublish
            ? 'Reply posted.'
            : 'Thanks! Your comment was submitted and is awaiting moderation.');

        return $this->redirectBack();
    }
}
