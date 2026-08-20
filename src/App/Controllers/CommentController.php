<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Gate;
use App\Models\BlogModel;
use App\Models\CommentModel;
use App\Models\CommentReportModel;
use App\Models\CommentVoteModel;
use App\Services\CommentRateLimiter;
use App\Services\CommentRemovalService;
use App\Services\CommentService;
use Framework\Core\Response;

/**
 * Reader-facing comment actions: posting, removing, voting, reporting, pinning.
 *
 * Creation rules live in CommentService so the post-login resume of a
 * captured guest reply follows exactly the same path as a direct submit, and
 * removal lives in CommentRemovalService so the front of the site and the
 * moderation dashboard cannot drift apart on what deleting a comment means.
 */
class CommentController extends AppController
{
    public function __construct(
        private CommentService $comments,
        private CommentModel $commentModel,
        private CommentVoteModel $votes,
        private CommentReportModel $reports,
        private CommentRemovalService $removal,
        private BlogModel $blogModel,
        private CommentRateLimiter $throttle,
    ) {}

    public function create(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        // Posting is open to guests, and every accepted comment queues mail to
        // the blog team, so an unthrottled POST amplifies into outbound email.
        if ($this->throttle->hitSubmission($this->clientIp())) {
            $this->flash('error', $this->waitMessage($this->throttle->submissionAvailableIn($this->clientIp())));

            return $this->redirectBack();
        }

        $postId = (int) ($this->request->post['post_id'] ?? 0);
        $parentId = (int) ($this->request->post['parent_comment_id'] ?? 0);
        $content = trim((string) ($this->request->post['content'] ?? ''));

        $user = auth()->user();

        // Guest replying: keep the typed reply alive through the login hop
        // instead of throwing it away with an error.
        if ($user === null && $parentId > 0) {
            if ($postId <= 0 || $content === '') {
                $this->flash('error', 'Comment content is required.');

                return $this->redirectBack();
            }

            if ($error = $this->comments->contentLengthError($content)) {
                $this->flash('error', $error);

                return $this->redirectBack();
            }

            $returnPath = $this->refererPath();
            $this->comments->capturePending($postId, $parentId, $content, $returnPath);

            $this->flash('info', 'Log in or create an account to post your reply — we saved it for you.');

            return $this->redirect(lurl('/login').'?return_to='.urlencode($returnPath));
        }

        $result = $this->comments->create($postId, $parentId > 0 ? $parentId : null, $content, $user, $this->request->ip());

        $this->flash($result['ok'] ? 'success' : 'error', $result['message']);

        // Drop the reader on their own comment rather than at the top of a
        // thread they now have to hunt through.
        if ($result['id'] !== null) {
            return $this->redirect($this->backUrlPath().'#comment-'.$result['id']);
        }

        return $this->redirectBack();
    }

    /**
     * Remove a comment as its author, or as a moderator of the owning blog.
     *
     * Posts a form rather than calling an API so the action still works with
     * scripting off, and so a removal that reshapes the thread comes back as a
     * fresh render instead of a patched-up DOM.
     */
    public function destroy(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $commentId = (int) $id;
        $user = auth()->user();
        $comment = $this->commentModel->findById($commentId);

        if ($comment === null) {
            $this->flash('error', 'That comment no longer exists.');

            return $this->redirectBack();
        }

        $isModerator = $this->moderatesComment($commentId, $user);

        if (!$this->removal->canRemove($comment, (int) $user['id'], $isModerator)) {
            $this->flash('error', 'You cannot remove that comment.');

            return $this->redirectBack();
        }

        // Authors keep the author wording even when they also moderate the
        // blog: removing your own words is not a moderation action.
        $isOwnComment = !empty($comment['user_id']) && (int) $comment['user_id'] === (int) $user['id'];
        $by = $isOwnComment ? CommentRemovalService::BY_AUTHOR : CommentRemovalService::BY_MODERATOR;

        $purged = $this->removal->remove($commentId, $by);

        audit()->log(
            (int) $user['id'],
            'comment.removed',
            'comment',
            $commentId,
            ['post_id' => $comment['post_id'] ?? null, 'by' => $by, 'purged' => $purged],
            $this->request->ip()
        );

        $this->flash('success', $isOwnComment ? 'Your comment was removed.' : 'Comment removed.');

        return $this->redirect($this->backUrlPath());
    }

    /**
     * Cast, flip, or clear the viewer's vote on a comment.
     */
    public function vote(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        if ($blocked = $this->interactionThrottleResponse()) {
            return $blocked;
        }

        $commentId = (int) $id;
        $direction = (string) ($this->request->post['direction'] ?? '');

        if (!in_array($direction, ['up', 'down'], true)) {
            return $this->jsonError('Unknown vote direction.', 422);
        }

        $comment = $this->votableComment($commentId);

        if ($comment === null) {
            return $this->jsonError('That comment is not available.', 404);
        }

        $userId = (int) auth()->user()['id'];
        $value = $direction === 'up' ? CommentVoteModel::UP : CommentVoteModel::DOWN;

        $totals = $this->votes->apply($userId, $commentId, $value);

        // The totals live inside the cached thread and "Top" sorts by them, so
        // the next reader has to be handed a rebuilt tree, not a stale one.
        $this->commentModel->forgetThreadCache((int) $comment['post_id']);

        return $this->jsonSuccess($totals);
    }

    /**
     * Flag a comment for the blog team.
     *
     * Reporting hides nothing on its own. It raises the comment in the owner's
     * moderation queue with a reason attached, and a person decides from there.
     */
    public function report(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        if ($blocked = $this->interactionThrottleResponse()) {
            return $blocked;
        }

        $commentId = (int) $id;
        $reason = (string) ($this->request->post['reason'] ?? 'other');

        if (($comment = $this->votableComment($commentId)) === null) {
            return $this->jsonError('That comment is not available.', 404);
        }

        $userId = (int) auth()->user()['id'];

        // Reporting your own comment is a removal, not a report.
        if (!empty($comment['user_id']) && (int) $comment['user_id'] === $userId) {
            return $this->jsonError('You cannot report your own comment.', 422);
        }

        $recorded = $this->reports->report($userId, $commentId, $reason);

        if ($recorded) {
            audit()->log($userId, 'comment.reported', 'comment', $commentId, ['reason' => $reason], $this->request->ip());
        }

        return $this->jsonSuccess([
            'reported' => true,
            // A repeat report is not an error; it just does not count twice.
            'message' => $recorded
                ? 'Thanks — this comment has been sent to the blog team.'
                : 'You already reported this comment.',
        ]);
    }

    /**
     * Pin or unpin a top-level comment. Blog moderators only.
     */
    public function pin(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $commentId = (int) $id;
        $user = auth()->user();
        $comment = $this->commentModel->findById($commentId);

        if ($comment === null || !$this->moderatesComment($commentId, $user)) {
            $this->flash('error', 'You cannot pin that comment.');

            return $this->redirectBack();
        }

        $postId = (int) $comment['post_id'];
        $pinning = empty($comment['pinned_at']);

        $pinning ? $this->commentModel->pin($commentId, $postId, (int) $user['id']) : $this->commentModel->unpin($postId);

        audit()->log(
            (int) $user['id'],
            $pinning ? 'comment.pinned' : 'comment.unpinned',
            'comment',
            $commentId,
            ['post_id' => $postId],
            $this->request->ip()
        );

        $this->flash('success', $pinning ? 'Comment pinned to the top.' : 'Comment unpinned.');

        return $this->redirect($this->backUrlPath().'#comment-'.$commentId);
    }

    /**
     * Record a vote/report attempt and build the 429 when the caller is over.
     *
     * @return Response|null The refusal to return, or null to carry on
     */
    private function interactionThrottleResponse(): ?Response
    {
        $ip = $this->clientIp();

        if (!$this->throttle->hitInteraction($ip)) {
            return null;
        }

        $wait = $this->throttle->interactionAvailableIn($ip);
        $this->response->addHeader('Retry-After', (string) $wait);

        return $this->jsonError($this->waitMessage($wait), 429);
    }

    private function clientIp(): string
    {
        return $this->request->ip() ?? 'unknown';
    }

    /**
     * Phrase the refusal in minutes so it reads like a pause, not a failure.
     *
     * @param  int  $seconds  Seconds left on the throttle
     */
    private function waitMessage(int $seconds): string
    {
        if ($seconds < 60) {
            return 'You are going a little fast. Try again in a few seconds.';
        }

        $minutes = (int) ceil($seconds / 60);

        return "You are going a little fast. Try again in {$minutes} minute".($minutes === 1 ? '' : 's').'.';
    }

    /**
     * A comment readers may act on: published, approved, and not a tombstone.
     *
     * @return array<string, mixed>|null
     */
    private function votableComment(int $commentId): ?array
    {
        $comment = $this->commentModel->findById($commentId);

        if ($comment === null || $comment['status'] !== 'approved' || !empty($comment['deleted_at'])) {
            return null;
        }

        return $comment;
    }

    /**
     * Whether the viewer moderates the blog a comment was left on.
     *
     * Deliberately the same Gate check that guards the moderation dashboard:
     * acting on a comment from the post page is a shortcut to a power the
     * viewer already had, not a second way of granting it.
     *
     * @param  array<string, mixed>|null  $user  Authenticated viewer, null for guests
     */
    private function moderatesComment(int $commentId, ?array $user): bool
    {
        if ($user === null) {
            return false;
        }

        $blogId = $this->commentModel->blogIdForComment($commentId);

        if ($blogId === null) {
            return false;
        }

        $blog = $this->blogModel->getBlog($blogId);

        return $blog !== false && Gate::allows('update', $blog, $user);
    }

    /**
     * Local path of the page the comment came from, for return-to routing.
     */
    private function refererPath(): string
    {
        $referer = (string) ($this->request->header('Referer') ?? '');
        $base = rtrim(base_url(), '/');

        if ($referer !== '' && $base !== '' && str_starts_with($referer, $base)) {
            $candidate = safe_return_to(substr($referer, strlen($base)));
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return '/';
    }
}
