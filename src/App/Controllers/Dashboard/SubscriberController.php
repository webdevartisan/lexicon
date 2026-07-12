<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\BlogSubscriberModel;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Dashboard management of a blog's email subscribers.
 *
 * Owner-only, same gate as team management: subscriber emails are audience
 * data and shouldn't be readable by every collaborator.
 */
final class SubscriberController extends AppController
{
    private const PER_PAGE = 25;

    public function __construct(
        private BlogModel $blogModel,
        private BlogSubscriberModel $subscribers,
    ) {}

    /**
     * List subscribers with search and pagination.
     *
     * @param  string  $blogId  Blog ID
     */
    public function index(string $blogId): Response
    {
        $blog = $this->getBlog($blogId);
        Gate::authorize('manageUsers', $blog, auth()->user());

        $q = trim((string) ($this->request->get['q'] ?? ''));
        $page = max(1, (int) ($this->request->get['page'] ?? 1));

        $result = $this->subscribers->pageForBlog((int) $blog->id(), $page, self::PER_PAGE, $q);

        return $this->view([
            'blog' => $blog->toArray(),
            'subscribers' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'q' => $q,
        ]);
    }

    /**
     * Remove a subscriber from the blog's list.
     *
     * @param  string  $blogId  Blog ID
     * @param  string  $id  Subscriber row ID
     */
    public function destroy(string $blogId, string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $blog = $this->getBlog($blogId);
        Gate::authorize('manageUsers', $blog, auth()->user());

        if ($this->subscribers->deleteByIdForBlog((int) $id, (int) $blog->id())) {
            audit()->log(
                (int) auth()->user()['id'],
                'subscriber.removed',
                'blog',
                (int) $blog->id(),
                ['subscriber_id' => (int) $id],
                $this->request->ip()
            );
            $this->flash('success', 'Subscriber removed.');
        } else {
            $this->flash('error', 'Subscriber not found.');
        }

        return $this->redirect(lurl("/dashboard/blog/{$blog->id()}/subscribers"));
    }

    /**
     * Get blog resource or throw 404.
     *
     * @throws PageNotFoundException
     */
    private function getBlog(string $blogId): \App\Resources\BlogResource
    {
        $blog = $this->blogModel->getBlog($blogId);

        if (!$blog) {
            throw new PageNotFoundException("Blog with ID '{$blogId}' not found.");
        }

        return $blog;
    }
}
