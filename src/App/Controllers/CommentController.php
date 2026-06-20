<?php

declare(strict_types=1);

namespace App\Controllers;

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

        $comment = [
            'post_id' => $postId,
            'user_id' => $userId,
            'content' => $content,
            'status' => 'pending',
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
            ],
            $this->request->ip()
        );

        $this->flash('success', 'Thanks! Your comment was submitted and is awaiting moderation.');

        return $this->redirectBack();
    }
}
