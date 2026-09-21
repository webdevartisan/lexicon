<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ReportRejectedException;
use App\Models\PostBookmarkModel;
use App\Models\PostModel;
use App\Models\PostVoteModel;
use App\Services\CommentRateLimiter;
use App\Services\ReportIntakeService;
use App\Traits\ThrottlesReaderInteractions;
use Framework\Core\Response;

/**
 * Reader actions on a published post: voting, bookmarking, reporting.
 *
 * Deliberately the same shape as the comment thread's actions, so a reader
 * learns one set of controls and they behave the same wherever they appear.
 */
class PostEngagementController extends AppController
{
    use ThrottlesReaderInteractions;

    public function __construct(
        private PostModel $postModel,
        private PostVoteModel $voteModel,
        private PostBookmarkModel $bookmarkModel,
        private ReportIntakeService $intake,
        private CommentRateLimiter $throttle,
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
     * Report a post to the moderators.
     *
     * Same contract as reporting a comment: the report joins the post's case,
     * and the category's rule decides whether a person has to act first.
     */
    public function report(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        if ($blocked = $this->interactionThrottleResponse()) {
            return $blocked;
        }

        $post = $this->readablePost((int) $id);

        if ($post === null) {
            return $this->jsonError('Post not found.', 404);
        }

        $userId = (int) auth()->user()['id'];

        // Reporting your own post is not a report, it is an edit.
        if (!empty($post['author_id']) && (int) $post['author_id'] === $userId) {
            return $this->jsonError('You cannot report your own post.', 422);
        }

        $details = $this->request->post['details'] ?? null;

        try {
            $result = $this->intake->file(
                $userId,
                'post',
                (int) $id,
                (string) ($this->request->post['reason'] ?? ''),
                is_string($details) ? $details : null
            );
        } catch (ReportRejectedException $e) {
            return $this->jsonError($e->getMessage(), 422);
        }

        if ($result['recorded']) {
            audit()->log(
                $userId,
                'post.reported',
                'post',
                (int) $id,
                ['category' => $result['category'], 'case_id' => $result['case_id']],
                $this->request->ip()
            );
        }

        return $this->jsonSuccess([
            'reported' => true,
            // A repeat report is not an error; it just does not count twice.
            'message' => $result['recorded']
                ? 'Thanks. This post has been sent to the moderators.'
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
