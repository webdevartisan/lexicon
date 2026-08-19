<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\CommentModel;
use App\Services\CommentRemovalService;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Site-wide comment moderation for administrators.
 *
 * One moderation queue across every blog, with status filters, search,
 * and single or bulk transitions. Blog owners moderate their own blogs
 * from the dashboard; this screen exists for whole-site oversight.
 */
class CommentController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'moderateComments';

    private const STATUSES = ['all', 'pending', 'approved', 'spam', 'reported'];

    public function __construct(
        private CommentModel $model,
        private CommentRemovalService $removal
    ) {}

    /**
     * Display the moderation queue with status tabs, search, and pagination.
     */
    public function index(): Response
    {
        $status = trim((string) ($this->request->get['status'] ?? 'all'));
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'all';
        }

        $q = trim((string) ($this->request->get['q'] ?? ''));
        $page = max(1, (int) ($this->request->get['page'] ?? 1));

        $filterStatus = $status === 'all' ? '' : $status;
        $result = $this->model->findAllWithFilters($filterStatus, $q, $page, 20);

        return $this->view([
            'comments' => $result['data'],
            'pagination' => $result['pagination'],
            'counts' => $this->model->countsByStatus(),
            'status' => $status,
            'q' => $q,
        ]);
    }

    public function approve(string $id): Response
    {
        return $this->transition((int) $id, 'approved', 'comment.approved', 'Comment approved.');
    }

    public function unapprove(string $id): Response
    {
        return $this->transition((int) $id, 'pending', 'comment.unapproved', 'Comment moved back to pending.');
    }

    public function spam(string $id): Response
    {
        return $this->transition((int) $id, 'spam', 'comment.spam', 'Comment marked as spam.');
    }

    /**
     * Permanently delete a comment.
     */
    public function destroy(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $comment = $this->getComment((int) $id);

        // Shared removal path: a comment holding replies becomes a tombstone
        // rather than cascading those replies out of the thread.
        $this->removal->remove((int) $id, CommentRemovalService::BY_MODERATOR);

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

        $this->flash('success', 'Comment deleted.');

        return $this->redirect($this->commentsUrl());
    }

    /**
     * Apply an action to a selection of comments in one request.
     */
    public function bulk(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $action = (string) ($this->request->post['bulk_action'] ?? '');
        $ids = array_values(array_filter(array_map('intval', (array) ($this->request->post['comment_ids'] ?? []))));

        $statusMap = [
            'approve' => 'approved',
            'spam' => 'spam',
            'unapprove' => 'pending',
        ];

        if ((!isset($statusMap[$action]) && $action !== 'delete') || empty($ids)) {
            $this->flash('error', 'No comments selected or invalid action.');

            return $this->redirect($this->commentsUrl());
        }

        $affected = $action === 'delete'
            ? $this->removeMany($ids)
            : $this->model->bulkUpdateStatus($ids, $statusMap[$action]);

        audit()->log(
            (int) auth()->user()['id'],
            "comment.bulk_{$action}",
            'comment',
            null,
            ['count' => $affected],
            $this->request->ip()
        );

        $this->flash('success', "{$affected} comment(s) updated.");

        return $this->redirect($this->commentsUrl());
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

    /**
     * Change a comment's status and record it in the audit trail.
     */
    private function transition(int $id, string $status, string $auditAction, string $message): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $comment = $this->getComment($id);

        $this->model->updateStatus($id, $status);

        audit()->log(
            (int) auth()->user()['id'],
            $auditAction,
            'comment',
            $id,
            [
                'post_id' => $comment['post_id'] ?? null,
                'previous_status' => $comment['status'] ?? null,
                'status' => $status,
            ],
            $this->request->ip()
        );

        $this->flash('success', $message);

        return $this->redirect($this->commentsUrl());
    }

    /**
     * Keep the admin on the same moderation tab after an action.
     */
    private function commentsUrl(): string
    {
        $status = (string) ($this->request->post['return_status'] ?? '');
        $url = '/admin/comments';

        if (in_array($status, self::STATUSES, true)) {
            $url .= '?status='.$status;
        }

        return $url;
    }

    /**
     * @return array<string, mixed> Comment record
     */
    private function getComment(int $id): array
    {
        $comment = $this->model->findById($id);

        if ($comment === null) {
            throw new PageNotFoundException("Comment with ID '{$id}' not found.");
        }

        return $comment;
    }
}
