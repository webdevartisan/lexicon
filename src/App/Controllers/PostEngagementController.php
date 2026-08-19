<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PostBookmarkModel;
use App\Models\PostModel;
use App\Models\PostReportModel;
use App\Models\PostVoteModel;
use Framework\Core\Response;

/**
 * Reader actions on a published post: voting, bookmarking, reporting.
 *
 * Deliberately the same shape as the comment thread's actions, so a reader
 * learns one set of controls and they behave the same wherever they appear.
 */
class PostEngagementController extends AppController
{
    public function __construct(
        private PostModel $postModel,
        private PostVoteModel $voteModel,
        private PostBookmarkModel $bookmarkModel,
        private PostReportModel $reports,
    ) {}

    /**
     * Cast, flip, or clear the viewer's vote on a post.
     */
    public function vote(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $direction = (string) ($this->request->post['direction'] ?? '');

        if (!in_array($direction, ['up', 'down'], true)) {
            return $this->jsonError('Unknown vote direction.', 422);
        }

        if ($this->readablePost((int) $id) === null) {
            return $this->jsonError('Post not found.', 404);
        }

        $userId = (int) auth()->user()['id'];
        $value = $direction === 'up' ? PostVoteModel::UP : PostVoteModel::DOWN;

        $totals = $this->voteModel->apply($userId, (int) $id, $value);

        audit()->log($userId, 'post.voted', 'post', (int) $id, ['mine' => $totals['mine']], $this->request->ip());

        return $this->jsonSuccess($totals);
    }

    public function toggleBookmark(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        if ($this->readablePost((int) $id) === null) {
            return $this->jsonError('Post not found.', 404);
        }

        $userId = (int) auth()->user()['id'];
        $active = $this->bookmarkModel->toggle($userId, (int) $id);

        audit()->log($userId, 'post.bookmark.toggled', 'post', (int) $id, ['active' => $active], $this->request->ip());

        return $this->jsonSuccess([
            'active' => $active,
            'count' => $this->bookmarkModel->countByPost((int) $id),
        ]);
    }

    /**
     * Flag a post for the blog team.
     *
     * Same contract as reporting a comment: nothing hides on its own, the
     * count is what a person acts on.
     */
    public function report(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $post = $this->readablePost((int) $id);

        if ($post === null) {
            return $this->jsonError('Post not found.', 404);
        }

        $userId = (int) auth()->user()['id'];

        // Reporting your own post is not a report, it is an edit.
        if (!empty($post['author_id']) && (int) $post['author_id'] === $userId) {
            return $this->jsonError('You cannot report your own post.', 422);
        }

        $reason = (string) ($this->request->post['reason'] ?? 'other');
        $recorded = $this->reports->report($userId, (int) $id, $reason);

        if ($recorded) {
            audit()->log($userId, 'post.reported', 'post', (int) $id, ['reason' => $reason], $this->request->ip());
        }

        return $this->jsonSuccess([
            'reported' => true,
            // A repeat report is not an error; it just does not count twice.
            'message' => $recorded
                ? 'Thanks — this post has been sent to the blog team.'
                : 'You already reported this post.',
        ]);
    }

    /**
     * A post readers may act on: published and public.
     *
     * @return array<string, mixed>|null
     */
    private function readablePost(int $postId): ?array
    {
        $post = $this->postModel->find((string) $postId);

        if (!$post || $post['status'] !== 'published' || $post['visibility'] !== 'public') {
            return null;
        }

        return $post;
    }
}
