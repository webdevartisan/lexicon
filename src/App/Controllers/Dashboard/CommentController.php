<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\CommentModel;
use App\Models\CommentReportModel;
use App\Resources\BlogResource;
use App\Services\CommentRemovalService;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

class CommentController extends AppController
{
    public function __construct(
        private CommentModel $model,
        private BlogModel $blogModel,
        private CommentRemovalService $removal,
        private CommentReportModel $reports
    ) {}

    public function index(string $blogId): Response
    {
        $user = auth()->user();
        $blog = $this->getBlog((int) $blogId);
        Gate::authorize('update', $blog, $user);

        $status = trim((string) ($this->request->get['status'] ?? 'pending'));
        if (!in_array($status, ['all', 'pending', 'approved', 'spam', 'reported'], true)) {
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
        $counts['reported'] = $this->model->countReportedByBlogId((int) $blogId);

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

        // Same removal path the post page uses: a comment with replies under it
        // becomes a tombstone instead of taking those replies down with it.
        $purged = $this->removal->remove((int) $id, CommentRemovalService::BY_MODERATOR);

        audit()->log(
            (int) $user['id'],
            'comment.deleted',
            'comment',
            (int) $id,
            ['post_id' => $comment['post_id'] ?? null, 'purged' => $purged],
            $this->request->ip()
        );

        $this->flash('success', $purged ? 'Comment deleted.' : 'Comment removed; its replies were kept.');

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
            ? $this->removeMany($ids)
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

    /**
     * Remove a selection one at a time so each keeps its replies.
     *
     * A single bulk DELETE would cascade through parent_comment_id and take
     * replies nobody selected with it.
     *
     * @param  int[]  $ids  Comment ids to remove
     * @return int How many were removed
     */
    private function removeMany(array $ids): int
    {
        foreach ($ids as $id) {
            $this->removal->remove($id, CommentRemovalService::BY_MODERATOR);
        }

        return count($ids);
    }

    private function transition(int $id, string $status, string $auditAction, string $message): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        [$comment, $blog] = $this->resolveCommentBlog($id);
        Gate::authorize('update', $blog, $user);

        $this->model->updateStatus($id, $status);

        // Ruling a comment fine settles the reports against it. Leaving them
        // would keep the queue arguing with a decision it already made.
        if ($status === 'approved') {
            $this->reports->clearFor($id);
        }

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

        if (in_array($status, ['all', 'pending', 'approved', 'spam', 'reported'], true)) {
            $url .= '?status='.$status;
        }

        return $url;
    }

    /**
     * @return array{0: array<string, mixed>, 1: BlogResource}
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
