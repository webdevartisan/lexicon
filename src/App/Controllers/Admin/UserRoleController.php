<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Gate;
use App\Models\BlogModel;
use App\Models\RoleModel;
use App\Models\UserModel;
use App\Resources\SystemResource;
use App\Services\AccountErasureService;
use Framework\Core\Response;

/**
 * The two role axes, kept as two separate screens because they are two
 * separate things: the site role lives on the account (user_roles) and
 * unlocks control panel areas; blog roles live on each membership
 * (blog_users) and decide what someone may do inside one blog.
 */
class UserRoleController extends ManagedUserController
{
    public function __construct(
        UserModel $users,
        AccountErasureService $erasure,
        private RoleModel $roles,
        private BlogModel $blogs,
    ) {
        parent::__construct($users, $erasure);
    }

    public function siteRole(string $id): Response
    {
        $user = $this->target($id);

        return $this->view('user.site-role', [
            'user' => $user,
            'roles' => $this->roles->findByScope('system'),
            'currentSlugs' => $this->users->getUserRoles((int) $user['id']),
            'canAssign' => Gate::allows('assignSystemRoles', SystemResource::class, $this->actor()),
        ]);
    }

    public function updateSiteRole(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        // Administrator is itself a site role, so anyone who could assign site
        // roles could make themselves one. Checked here, not just in the view.
        Gate::authorize('assignSystemRoles', SystemResource::class, $this->actor());

        $user = $this->target($id);
        $userId = (int) $user['id'];
        $back = '/admin/users/'.$userId.'/site-role';

        $roleId = (int) $this->request->postParam('role_id', 0);
        $role = $this->systemRoleById($roleId);

        if ($role === null) {
            $this->flash('error', 'Choose one of the site roles listed.');

            return $this->redirect($back);
        }

        $before = $this->users->getUserRoles($userId);
        $wasAdmin = in_array('administrator', $before, true);

        if ($wasAdmin && $role['role_slug'] !== 'administrator' && $this->users->countAdministrators() <= 1) {
            $this->flash('error', 'This is the last active administrator. Make another account an administrator first.');

            return $this->redirect($back);
        }

        if ($before === [$role['role_slug']]) {
            $this->flash('info', '@'.$user['handle'].' already has that site role. Nothing changed.');

            return $this->redirect($this->showUrl($userId));
        }

        try {
            $this->users->setSystemRole($userId, $roleId, $this->actorId());
        } catch (\Throwable $e) {
            error_log('setSystemRole failed for user '.$userId.': '.$e->getMessage());
            $this->flash('error', 'The site role was not changed. Nothing was saved; the error is in the log.');

            return $this->redirect($back);
        }

        audit()->log(
            $this->actorId(),
            'user.site_role_changed',
            'user',
            $userId,
            ['from' => $before, 'to' => $role['role_slug']],
            $this->request->ip()
        );

        $this->flash('success', sprintf('@%s is now %s.', $user['handle'], $role['role_name']));

        return $this->redirect($this->showUrl($userId));
    }

    public function blogRoles(string $id): Response
    {
        $user = $this->target($id);
        $this->guardAdministratorTarget($user);

        return $this->view('user.blog-roles', [
            'user' => $user,
            'blogs' => $this->blogs->getAccessibleBlogs((int) $user['id']),
            'roleOptions' => $this->blogs->availableCollaboratorRoles(),
        ]);
    }

    public function updateBlogRole(string $id, string $blogId): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = $this->target($id);
        $this->guardAdministratorTarget($user);
        $userId = (int) $user['id'];
        $membership = $this->membershipOrFail($userId, (int) $blogId);

        if ($membership instanceof Response) {
            return $membership;
        }

        $role = (string) $this->request->postParam('role', '');

        if (!in_array($role, $this->blogs->availableCollaboratorRoles(), true)) {
            $this->flash('error', 'Choose one of the blog roles listed.');

            return $this->redirect($this->blogRolesUrl($userId));
        }

        if ($role === $membership['role']) {
            $this->flash('info', 'That is already their role on '.$membership['blog_name'].'. Nothing changed.');

            return $this->redirect($this->blogRolesUrl($userId));
        }

        if (!$this->blogs->addUserToBlog((int) $blogId, $userId, $role, $this->actorId())) {
            $this->flash('error', 'The role on '.$membership['blog_name'].' was not changed. Try again.');

            return $this->redirect($this->blogRolesUrl($userId));
        }

        audit()->log(
            $this->actorId(),
            'user.blog_role_changed',
            'blog',
            (int) $blogId,
            ['user_id' => $userId, 'from' => $membership['role'], 'to' => $role],
            $this->request->ip()
        );

        $this->flash('success', sprintf('@%s is now %s on %s.', $user['handle'], $role, $membership['blog_name']));

        return $this->redirect($this->blogRolesUrl($userId));
    }

    public function removeBlogRole(string $id, string $blogId): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = $this->target($id);
        $this->guardAdministratorTarget($user);
        $userId = (int) $user['id'];
        $membership = $this->membershipOrFail($userId, (int) $blogId);

        if ($membership instanceof Response) {
            return $membership;
        }

        if (!$this->blogs->revokeUserFromBlog((int) $blogId, $userId)) {
            $this->flash('error', '@'.$user['handle'].' was not removed from '.$membership['blog_name'].'. Try again.');

            return $this->redirect($this->blogRolesUrl($userId));
        }

        audit()->log(
            $this->actorId(),
            'user.blog_role_removed',
            'blog',
            (int) $blogId,
            ['user_id' => $userId, 'role' => $membership['role']],
            $this->request->ip()
        );

        $this->flash('success', sprintf('@%s was removed from %s.', $user['handle'], $membership['blog_name']));

        return $this->redirect($this->blogRolesUrl($userId));
    }

    /**
     * The active membership row, or a redirect explaining why there is none.
     *
     * Ownership is refused here on purpose. It is blogs.owner_id, not a
     * membership, so a blog always has exactly one owner and cannot be left
     * with none; moving it is the transfer action on the blog's own page.
     *
     * @return array{role: string, blog_name: string}|Response
     */
    private function membershipOrFail(int $userId, int $blogId): array|Response
    {
        $blog = $this->blogs->getBlogById($blogId);

        if (!$blog) {
            $this->flash('error', 'That blog no longer exists.');

            return $this->redirect($this->blogRolesUrl($userId));
        }

        if ((int) $blog['owner_id'] === $userId) {
            $this->flash('error', 'They own '.$blog['blog_name'].'. Transfer ownership from the blog\'s page before changing their role on it.');

            return $this->redirect($this->blogRolesUrl($userId));
        }

        foreach ($this->blogs->getBlogUsers($blogId) as $member) {
            if ((int) $member['user_id'] === $userId) {
                return ['role' => (string) $member['role'], 'blog_name' => (string) $blog['blog_name']];
            }
        }

        $this->flash('error', 'They are not a member of '.$blog['blog_name'].' any more.');

        return $this->redirect($this->blogRolesUrl($userId));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function systemRoleById(int $roleId): ?array
    {
        foreach ($this->roles->findByScope('system') as $role) {
            if ((int) $role['id'] === $roleId) {
                return $role;
            }
        }

        return null;
    }

    private function blogRolesUrl(int $userId): string
    {
        return '/admin/users/'.$userId.'/blog-roles';
    }
}
