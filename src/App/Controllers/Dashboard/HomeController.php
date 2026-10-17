<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\UserPreferencesModel;
use App\Services\ActiveBlog;
use App\Services\BlogRoleLabels;
use App\Services\GuidedSetupService;
use App\Services\OverviewService;
use Framework\Core\Response;

/**
 * The Overview: one page for every role on the active blog. Each section is
 * shown by permission, so an author, a reviewer and the owner each see the
 * part of the blog they can act on.
 */
class HomeController extends AppController
{
    public function __construct(
        private BlogModel $blogModel,
        private UserPreferencesModel $preference,
        private ActiveBlog $activeBlog,
        private OverviewService $overview,
        private BlogRoleLabels $roleLabels,
        private GuidedSetupService $setup,
    ) {}

    /**
     * Overview of the active blog.
     */
    public function index(): Response
    {
        $user = auth()->user();
        $userId = (int) $user['id'];
        $blogId = (int) ($this->preference->getDefaultBlogId($userId) ?? 0);
        $blog = $blogId > 0 ? $this->blogModel->getBlog($blogId) : false;

        if ($blog === false || !Gate::allows('view', $blog, $user)) {
            // No owned or shared blogs: the landing page is this account's home,
            // and its query string keeps a guest's cached copy of "/" from being
            // replayed right after login or signup.
            return $this->redirect($this->blogModel->listAccessibleBlogs($userId) === [] ? '/?welcome=1' : '/dashboard/blog');
        }

        $isOwner = $blog->ownerId() === $userId;
        breadcrumbs()->clear();

        return $this->view([
            'blog' => ['id' => $blog->id(), 'name' => $blog->name(), 'slug' => $blog->slug(), 'status' => $blog->status()],
            'roleLabel' => $isOwner ? null : $this->roleLabels->label((string) $this->blogModel->storedRoleFor($blogId, $userId)),
            'hideTitle' => true,
            'setup' => $this->setup->forUser($userId, $blog->id()),
            'blogUrl' => base_url().'/blog/'.$blog->slug(),
        ] + $this->overview->for($blog, $user));
    }

    /**
     * Make a blog the one the dashboard works in. Any blog the user may view,
     * owned or shared; the role they hold there decides the rest.
     */
    public function setActiveBlog(): Response
    {
        $user = auth()->user();
        $blogId = (int) $this->request->postParam('blog');

        $blog = $this->blogModel->getBlog($blogId);
        if ($blog === false) {
            $this->flash('error', 'That blog no longer exists.');

            return $this->redirect('/dashboard/blog');
        }

        if (!$this->activeBlog->follow($user, $blog)) {
            $this->flash('error', "You don't have access to that blog.");

            return $this->redirect('/dashboard/blog');
        }

        $back = safe_return_to((string) $this->request->postParam('return_to')) ?? '/dashboard';
        if (preg_match('#/blog/(\d+)(/|$)#', $back, $m) !== 1) {
            return $this->redirect($back);
        }

        // Stay on the same page of the new blog only when the role there allows
        // everything the old one did; otherwise that page would answer with a 403.
        $from = $this->blogModel->getBlog((int) $m[1]);
        $userId = (int) $user['id'];
        if ($from === false || array_diff($from->permissionsForUser($userId), $blog->permissionsForUser($userId)) !== []) {
            return $this->redirect('/dashboard');
        }

        return $this->redirect(preg_replace('#/blog/\d+(/|$)#', '/blog/'.$blogId.'$1', $back, 1));
    }
}
