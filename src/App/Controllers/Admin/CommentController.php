<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\CommentModel;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Admin comment moderation controller.
 *
 * Allows editors and administrators to view, approve, and delete comments.
 * Access restricted via role:editor,administrator middleware.
 */
class CommentController extends AppController
{
    public function __construct(
        private CommentModel $model
    ) {}

    /**
     * Display all comments for moderation.
     */
    public function index(): Response
    {
        $comments = $this->model->findAll();

        return $this->view([
            'comments' => $comments,
        ]);
    }

    /**
     * Display single comment details.
     *
     * @param  string  $id  Comment ID
     */
    public function show(string $id): Response
    {
        $comment = $this->getComment($id);

        return $this->view([
            'comment' => $comment,
        ]);
    }

    /**
     * Approve a comment.
     *
     * Updates comment status to 'approved' and logs the action.
     *
     * @param  string  $id  Comment ID
     */
    public function approve(string $id): Response
    {
        // Enforce CSRF protection on state-changing actions
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $comment = $this->getComment($id);

        $this->model->update($id, ['status' => 'approved']);

        // Audit log the approval
        audit()->log(
            (int) auth()->user()['id'],
            'comment.approved',
            'comment',
            (int) $id,
            [
                'post_id' => $comment['post_id'] ?? null,
                'previous_status' => $comment['status'] ?? null,
            ],
            $this->request->ip()
        );

        $this->flash('success', 'Comment approved successfully.');

        return $this->redirect('/admin/comments');
    }

    /**
     * Display comment deletion confirmation page.
     *
     * @param  string  $id  Comment ID
     */
    public function delete(string $id): Response
    {
        $comment = $this->getComment($id);

        return $this->view([
            'comment' => $comment,
        ]);
    }

    /**
     * Permanently delete a comment.
     *
     * Removes comment from database and logs the deletion for audit trail.
     *
     * @param  string  $id  Comment ID
     */
    public function destroy(string $id): Response
    {
        // Enforce CSRF protection on destructive actions
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $comment = $this->getComment($id);

        $this->model->delete($id);

        // Audit log the deletion
        audit()->log(
            (int) auth()->user()['id'],
            'comment.deleted',
            'comment',
            (int) $id,
            [
                'post_id' => $comment['post_id'] ?? null,
                'content_preview' => substr($comment['content'] ?? '', 0, 100),
            ],
            $this->request->ip()
        );

        $this->flash('success', 'Comment deleted successfully.');

        return $this->redirect('/admin/comments');
    }

    /**
     * Retrieve comment by ID or throw 404.
     *
     * @param  string  $id  Comment ID
     * @return array Comment data
     *
     * @throws PageNotFoundException If comment not found
     */
    private function getComment(string $id): array
    {
        $comment = $this->model->find($id);

        if (!$comment) {
            throw new PageNotFoundException("Comment with ID '$id' not found.");
        }

        return $comment;
    }
}
