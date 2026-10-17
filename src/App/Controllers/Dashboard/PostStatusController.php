<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\PostModel;
use App\Resources\PostResource;
use App\Services\SubscriberNotificationService;
use App\Services\WorkflowService;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * The one-click status and visibility actions on a dashboard post: publish,
 * unpublish, back to draft, archive, and public, private or unlisted.
 *
 * Every action here uses the publish gate. Editing the post itself stays in
 * PostController.
 */
class PostStatusController extends AppController
{
    public function __construct(
        private PostModel $model,
        private WorkflowService $workflowService,
        private SubscriberNotificationService $subscriberNotifier,
    ) {}

    /**
     * Publish post.
     *
     * Publishing needs the publish gate, the same rule as the editor and the
     * bulk action. Publishing a post that is still in review counts as approving it.
     *
     * @param  string  $id  Post ID
     */
    public function publish(string $id): Response
    {
        $post = $this->guardStatusChange((int) $id);
        if ($post instanceof Response) {
            return $post;
        }

        $user = auth()->user();

        $this->workflowService->concludeReview($post, 'published', (int) $user['id']);
        $this->model->publishNow((int) $id);
        $this->subscriberNotifier->notifyPostPublished((int) $id);

        audit()->log(
            $user['id'],
            'post.published',
            'post',
            (int) $id,
            ['workflow_state' => $post->workflowState()],
            $this->request->ip()
        );

        $this->flash('success', 'Post published successfully.');

        return $this->redirectBack();
    }

    /**
     * Take a published post back to draft.
     *
     * Approvals live in the review history, so going back to draft never grants
     * or removes one.
     *
     * @param  string  $id  Post ID
     */
    public function unpublish(string $id): Response
    {
        $post = $this->guardStatusChange((int) $id);
        if ($post instanceof Response) {
            return $post;
        }

        if ($post->status() !== 'published') {
            $this->flash('error', 'Only a published post can be unpublished.');

            return $this->redirectBack();
        }

        $user = auth()->user();

        $this->model->updateStatus((int) $id, 'draft');

        audit()->log(
            $user['id'],
            'post.unpublished',
            'post',
            (int) $id,
            ['status' => 'draft', 'workflow_state' => $post->workflowState()],
            $this->request->ip()
        );

        $this->flash('success', 'Post unpublished successfully.');

        return $this->redirect('/dashboard');
    }

    /**
     * Revert post to draft status.
     *
     * @param  string  $id  Post ID
     */
    public function draft(string $id): Response
    {
        $post = $this->guardStatusChange((int) $id);
        if ($post instanceof Response) {
            return $post;
        }

        $user = auth()->user();

        $this->model->updateStatus((int) $id, 'draft');

        audit()->log(
            $user['id'],
            'post.reverted_to_draft',
            'post',
            (int) $id,
            [],
            $this->request->ip()
        );

        $this->flash('success', 'Post reverted to draft.');

        return $this->redirectBack();
    }

    /**
     * Archive post.
     *
     * @param  string  $id  Post ID
     */
    public function archive(string $id): Response
    {
        $post = $this->guardStatusChange((int) $id);
        if ($post instanceof Response) {
            return $post;
        }

        $user = auth()->user();

        $this->model->updateStatus((int) $id, 'archived');

        audit()->log(
            $user['id'],
            'post.archived',
            'post',
            (int) $id,
            [],
            $this->request->ip()
        );

        $this->flash('success', 'Post archived successfully.');

        return $this->redirectBack();
    }

    /**
     * List a post publicly again: blog index, search and discover.
     *
     * @param  string  $id  Post ID
     */
    public function makePublic(string $id): Response
    {
        return $this->changeVisibility((int) $id, 'public', 'Post is now public.');
    }

    /**
     * Restrict a post to the blog's team.
     *
     * @param  string  $id  Post ID
     */
    public function makePrivate(string $id): Response
    {
        return $this->changeVisibility((int) $id, 'private', 'Post is now private.');
    }

    /**
     * Keep a post reachable by direct link only, out of listings.
     *
     * @param  string  $id  Post ID
     */
    public function unlist(string $id): Response
    {
        return $this->changeVisibility((int) $id, 'unlisted', 'Post is now unlisted.');
    }

    /**
     * Check the request and the user's right to change this post's status, and
     * hand back the post, or the response to send instead when a moderator's
     * hold blocks the change.
     *
     * @throws PageNotFoundException
     */
    private function guardStatusChange(int $id): PostResource|Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $post = $this->getPost($id);
        Gate::authorize('publish', $post, auth()->user());

        return $this->refuseWhileModerated($post) ?? $post;
    }

    /**
     * Refuse a status change on a post a moderator hid, and say why.
     *
     * The model refuses the write on its own; this is what stops the action
     * reporting success, notifying subscribers, and logging a publish that
     * never happened.
     */
    private function refuseWhileModerated(PostResource $post): ?Response
    {
        if ($post->status() !== 'moderated') {
            return null;
        }

        $this->flash('error', 'A moderator hid this post after readers reported it, so its status cannot be changed here.');

        return $this->redirectBack();
    }

    /**
     * Shared body for the three visibility actions above.
     *
     * Uses the publish gate: whoever may take a post live may also decide how widely it shows.
     */
    private function changeVisibility(int $id, string $visibility, string $successMessage): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost($id);
        Gate::authorize('publish', $post, $user);

        $this->model->updateVisibility($id, $visibility);

        audit()->log(
            $user['id'],
            'post.visibility_changed',
            'post',
            $id,
            ['visibility' => $visibility],
            $this->request->ip()
        );

        $this->flash('success', $successMessage);

        return $this->redirectBack();
    }

    /**
     * Get post resource or throw 404.
     *
     * @throws PageNotFoundException
     */
    private function getPost(int $id): PostResource
    {
        $post = $this->model->findResource($id);

        if ($post === false) {
            throw new PageNotFoundException("Post with ID: '$id' not found.");
        }

        return $post;
    }
}
