<?php

namespace App\Controllers;

use App\Models\CommentModel;
use App\Models\PostModel;
use Framework\Core\Response;

/**
 * Handle public comment creation for blog posts.
 *
 * Allows authenticated users and guests to submit comments on published posts.
 * Enforces business rules like post visibility and comment availability.
 */
class CommentController extends AppController
{
    public function __construct(
        private PostModel $postModel,
        private CommentModel $commentModel,
    ) {}

    /**
     * Create a new comment on a blog post.
     *
     * Validates post availability, comment content, and enforces CSRF protection.
     * Supports both authenticated users and guest comments (if enabled).
     *
     * @return Response Redirect to post page with success/error message
     */
    public function create(): Response
    {
        // Enforce CSRF protection on comment submission
        csrf()->assertValid($this->request->post['_token'] ?? null);

        // Normalize input
        $postId = (int) ($this->request->post['post_id'] ?? 0);
        $content = trim($this->request->post['content'] ?? '');

        $user = auth()->user();
        $userId = $user ? (int) $user['id'] : null;

        // Validate required fields
        if ($postId <= 0 || $content === '') {
            $this->flash('error', 'Comment content is required.');
            return $this->redirectBack();
        }

        // Ensure post exists and is publicly accessible
        $post = $this->postModel->find((string) $postId);

        if (!$post) {
            $this->flash('error', 'Post not found.');
            return $this->redirect('/');
        }

        // Enforce business rules: only published public posts allow comments
        if ($post['status'] !== 'published' || $post['visibility'] !== 'public') {
            $this->flash('error', 'Comments are not available for this post.');
            return $this->redirectBack();
        }

        // Build comment data
        $data = [
            'post_id' => $postId,
            'user_id' => $userId, // null for guest comments
            'content' => $content,
        ];

        // Insert comment - ErrorHandler catches database exceptions
        if (!$this->commentModel->insert($data)) {
            $this->flash('error', 'Failed to create comment. Please try again.');
            return $this->redirectBack();
        }

        // Audit log the comment creation
        audit()->log(
            $userId ?? 0, // guest = 0
            'comment.created',
            'comment',
            $this->commentModel->getInsertID(),
            [
                'post_id' => $postId,
                'is_guest' => $userId === null,
            ],
            $this->request->ip()
        );

        $this->flash('success', 'Comment added successfully.');
        return $this->redirectBack();
    }
}
