<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\CommentModel;
use App\Resources\BlogResource;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

class CommentController extends AppController
{
    private const STATUSES = ['pending', 'approved', 'spam'];

    public function __construct(
        private CommentModel $model,
        private BlogModel $blogModel
    ) {}

    public function index(string $blogId): Response
    {
        $user = auth()->user();
        $blog = $this->getBlog((int) $blogId);
        Gate::authorize('update', $blog, $user);

        $status = trim((string) ($this->request->get['status'] ?? 'pending'));
        if (!in_array($status, ['all', 'pending', 'approved', 'spam'], true)) {
            $status = 'pending';
        }

        $q = trim((string) ($this->request->get['q'] ?? ''));
        $page = max(1, (int) ($this->request->get['page'] ?? 1));

        $filterStatus = $status === 'all' ? '' : $status;
        $result = $this->model->findByBlogIdWithFilters((int) $blogId, $filterStatus, $q, $page, 20);

        $counts = [
            'pending' => $this->model->countByBlogIdAndStatus((int) $blogId, 'pending'),
            'approved' => $this->model->countByBlogIdAndStatus((int) $blogId, 'approved'),
            'spam' => $this->model->countByBlogIdAndStatus((int) $blogId, 'spam'),
        ];
        $counts['all'] = $counts['pending'] + $counts['approved'] + $counts['spam'];

        return $this->view([
            'blog' => $blog->toArray(),
            'comments' => $result['data'],
            'pagination' => $result['pagination'],
            'counts' => $counts,
            'status' => $status,
            'q' => $q,
        ]);
    }

    public function approve(string $id): Response
    {
        return $this->transition((int) $id, 'approved', 'comment.approved', 'Comment approved.');
    }

    public function spam(string $id): Response
    {
        return $this->transition((int) $id, 'spam', 'comment.spam', 'Comment marked as spam.');
    }

    public function unapprove(string $id): Response
    {
        return $this->transition((int) $id, 'pending', 'comment.unapproved', 'Comment moved back to pending.');
    }

    public function destroy(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        [$comment, $blog] = $this->resolveCommentBlog((int) $id);
        Gate::authorize('update', $blog, $user);

        $this->model->deleteById((int) $id);

        audit()->log(
            (int) $user['id'],
            'comment.deleted',
            'comment',
            (int) $id,
            ['post_id' => $comment['post_id'] ?? null],
            $this->request->ip()
        );

        $this->flash('success', 'Comment deleted.');

        return $this->redirect($this->commentsUrl($blog->id()));
    }

    public function bulk(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $action = (string) ($this->request->post['bulk_action'] ?? '');
        $blogId = (int) ($this->request->post['blog_id'] ?? 0);
        $ids = array_values(array_filter(array_map('intval', (array) ($this->request->post['comment_ids'] ?? []))));

        $allowed = ['approve', 'spam', 'unapprove', 'delete'];
        if (!in_array($action, $allowed, true) || empty($ids) || $blogId <= 0) {
            $this->flash('error', 'No comments selected or invalid action.');

            return $this->redirectBack();
        }

        $blog = $this->getBlog($blogId);
        Gate::authorize('update', $blog, $user);

        // Ignore ids from other blogs in case the payload was tampered with.
        $ids = array_values(array_filter(
            $ids,
            fn (int $id): bool => $this->model->blogIdForComment($id) === $blogId
        ));

        if (empty($ids)) {
            $this->flash('error', 'No valid comments to update.');

            return $this->redirectBack();
        }

        $statusMap = [
            'approve' => 'approved',
            'spam' => 'spam',
            'unapprove' => 'pending',
        ];

        $affected = $action === 'delete'
            ? $this->model->bulkDelete($ids)
            : $this->model->bulkUpdateStatus($ids, $statusMap[$action]);

        audit()->log(
            (int) $user['id'],
            "comment.bulk_{$action}",
            'comment',
            null,
            ['blog_id' => $blogId, 'count' => $affected],
            $this->request->ip()
        );

        $this->flash('success', "{$affected} comment(s) updated.");

        return $this->redirect($this->commentsUrl($blogId));
    }

    private function transition(int $id, string $status, string $auditAction, string $message): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        [$comment, $blog] = $this->resolveCommentBlog($id);
        Gate::authorize('update', $blog, $user);

        $this->model->updateStatus($id, $status);

        audit()->log(
            (int) $user['id'],
            $auditAction,
            'comment',
            $id,
            ['post_id' => $comment['post_id'] ?? null, 'status' => $status],
            $this->request->ip()
        );

        $this->flash('success', $message);

        return $this->redirect($this->commentsUrl($blog->id()));
    }

    private function commentsUrl(int $blogId): string
    {
        // Keep the user on the same moderation tab after the action.
        $status = (string) ($this->request->post['return_status'] ?? '');
        $url = "/dashboard/blog/{$blogId}/comments";

        if (in_array($status, ['all', 'pending', 'approved', 'spam'], true)) {
            $url .= '?status='.$status;
        }

        return $url;
    }

    /**
     * @return array{0: array, 1: BlogResource}
     *
     * @throws PageNotFoundException
     */
    private function resolveCommentBlog(int $id): array
    {
        $comment = $this->model->findById($id);
        if ($comment === null) {
            throw new PageNotFoundException("Comment with ID '{$id}' not found.");
        }

        $blogId = $this->model->blogIdForComment($id);
        if ($blogId === null) {
            throw new PageNotFoundException("Blog for comment '{$id}' not found.");
        }

        return [$comment, $this->getBlog($blogId)];
    }

    /**
     * @throws PageNotFoundException
     */
    private function getBlog(int $id): BlogResource
    {
        $blog = $this->blogModel->getBlog($id);
        if (!$blog) {
            throw new PageNotFoundException("Blog with ID '{$id}' not found.");
        }

        return $blog;
    }
}
