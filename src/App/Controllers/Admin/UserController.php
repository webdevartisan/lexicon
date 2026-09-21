<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Gate;
use App\Models\BlogModel;
use App\Models\RoleModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Models\UserProfileModel;
use App\Models\UserSocialLinkModel;
use App\Resources\SystemResource;
use App\Services\AccountErasureService;
use App\Services\DisplayNameService;
use App\Services\EmailChangeIssuer;
use App\Services\LocaleRegistry;
use App\Services\NotificationPreferenceScope;
use App\Services\PublicCacheInvalidator;
use App\Services\UserDossierService;
use App\ValueObjects\TableSort;
use Framework\Core\Response;

/**
 * The users list, account creation, the general edit form and deletion.
 *
 * The edit form covers every field that has no dedicated flow of its own.
 * Password, site role, blog roles and suspension each have their own screen
 * with their own safeguards, so they are deliberately absent here: two
 * controls for one thing is how one of them ends up skipping a check.
 */
class UserController extends ManagedUserController
{
    public const SOCIAL_NETWORKS = ['website', 'twitter', 'instagram', 'linkedin', 'github'];

    /** New accounts get this site role unless an administrator picks another. */
    private const DEFAULT_SITE_ROLE = 'reader';

    public function __construct(
        UserModel $users,
        AccountErasureService $erasure,
        private RoleModel $roleModel,
        private BlogModel $blogModel,
        private UserProfileModel $profiles,
        private UserPreferencesModel $preferences,
        private UserSocialLinkModel $socials,
        private DisplayNameService $displayNames,
        private PublicCacheInvalidator $cacheInvalidator,
        private EmailChangeIssuer $emailChanges,
        private NotificationPreferenceScope $notificationScope,
        private LocaleRegistry $locales,
        private UserDossierService $dossier,
    ) {
        parent::__construct($users, $erasure);
    }

    public function index(): Response
    {
        $q = trim((string) ($this->request->get['q'] ?? ''));
        $active = trim((string) ($this->request->get['active'] ?? ''));
        $role = trim((string) ($this->request->get['role'] ?? ''));
        $page = max(1, (int) ($this->request->get['page'] ?? 1));

        $sort = TableSort::fromRequest($this->request, [
            'id' => 'u.id',
            'handle' => 'u.handle',
            'email' => 'u.email',
            'posts' => 'u.posts_count',
            'active' => 'u.is_active',
            'last_login' => 'u.last_login',
            'created' => 'u.created_at',
        ], defaultKey: 'created', defaultDirection: 'desc', tiebreaker: 'u.id DESC');

        $result = $this->users->findAllForAdmin($page, 20, $q, $active, $role, $sort->orderBy(), $this->erasure->deletedUserId());

        $actor = $this->actor();

        return $this->view('user.index', [
            'users' => $result['data'],
            'pagination' => $result['pagination'],
            'q' => $q,
            'active' => $active,
            'role' => $role,
            // Only system roles live on an account, so only they make sense as a
            // filter. Blog roles are per-blog and never appear in user_roles.
            'roleOptions' => $this->roleModel->findByScope('system'),
            'sort' => $sort,
            // Decided once here so the row menu offers only what will actually
            // be allowed. The actions re-check on the server regardless.
            'actorId' => $this->actorId(),
            'actorIsAdmin' => Gate::allows('actOnAdministrators', SystemResource::class, $actor),
            'canHandleReports' => Gate::allows('handleReports', SystemResource::class, $actor),
            'canAssignSiteRoles' => Gate::allows('assignSystemRoles', SystemResource::class, $actor),
            'canImpersonate' => Gate::allows('impersonateUsers', SystemResource::class, $actor),
        ]);
    }

    public function new(): Response
    {
        return $this->view('user.new', [
            'roles' => $this->roleModel->findByScope('system'),
            'defaultRole' => self::DEFAULT_SITE_ROLE,
            'canAssignSiteRoles' => Gate::allows('assignSystemRoles', SystemResource::class, $this->actor()),
        ]);
    }

    public function create(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $validator = $this->validateOrFail([
            'handle' => 'required|user_handle|min:2|max:50|unique:users,handle',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|password:'.password_policy_preset(),
            'first_name' => 'max:50',
            'last_name' => 'max:50',
        ]);
        $input = $validator->validated();

        $roleId = $this->roleForNewAccount();

        if ($roleId === null) {
            $this->flash('error', 'Choose one of the site roles listed.');

            return $this->redirectBack();
        }

        $inserted = $this->users->insert([
            'handle' => $input['handle'],
            'email' => $input['email'],
            // The model layer stores columns verbatim, so hash here like
            // RegisterController does or the account cannot log in
            'password' => password_hash($input['password'], PASSWORD_DEFAULT),
            'first_name' => $input['first_name'] ?? null,
            'last_name' => $input['last_name'] ?? null,
            'is_active' => 1,
        ]);

        if (!$inserted) {
            $this->flash('error', 'Could not create the user. Check the logs.');

            return $this->redirect('/admin/users/new');
        }

        $userId = (int) $this->users->getInsertID();
        $this->users->setSystemRole($userId, $roleId, $this->actorId());

        audit()->log(
            $this->actorId(),
            'user.created',
            'user',
            $userId,
            ['handle' => $input['handle'], 'email' => $input['email'], 'role_id' => $roleId],
            $this->request->ip()
        );

        $this->flash('success', 'User created.');

        return $this->redirect($this->showUrl($userId));
    }

    public function edit(string $id): Response
    {
        $user = $this->target($id);
        $this->guardAdministratorTarget($user);
        $userId = (int) $user['id'];

        return $this->view('user.edit', [
            'user' => $user,
            'profile' => $this->dossier->profile($userId) ?? [],
            'preferences' => $this->dossier->preferences($userId) ?? [],
            'socialLinks' => $this->dossier->socialLinks($userId),
            'networks' => self::SOCIAL_NETWORKS,
            'notifyKeys' => $this->notificationScope->applicableKeys($userId),
            'blogChoices' => $this->accessibleBlogChoices($userId),
            'locales' => $this->locales->supported(),
            'pendingEmail' => $this->dossier->pendingEmail($userId),
        ]);
    }

    public function update(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = $this->target($id);
        $this->guardAdministratorTarget($user);
        $userId = (int) $user['id'];

        $input = $this->validateOrFail($this->updateRules($userId))->validated();

        $defaultBlogId = (int) ($input['default_blog_id'] ?? 0);

        if ($defaultBlogId !== 0 && !array_key_exists($defaultBlogId, $this->accessibleBlogChoices($userId))) {
            $this->flash('error', 'The default blog has to be one this account owns or belongs to.');

            return $this->redirectBack();
        }

        $userChanges = changedFields([
            'handle' => $input['handle'],
            'first_name' => $input['first_name'] ?? '',
            'last_name' => $input['last_name'] ?? '',
        ], $user);

        if ($userChanges !== [] && !$this->users->updateById($userId, $userChanges)) {
            $this->flash('error', 'Nothing was saved: the account details could not be written.');

            return $this->redirect('/admin/users/'.$userId.'/edit');
        }

        $profileChanges = $this->saveProfile($userId, $input);
        $this->saveSocialLinks($userId, $input);
        $this->savePreferences($userId, $input, $defaultBlogId);
        $emailPending = $this->startEmailChange($user, (string) $input['email']);

        $this->refreshPublicIdentity($user, $userChanges, $profileChanges);

        audit()->log(
            $this->actorId(),
            'user.updated',
            'user',
            $userId,
            [
                'fields' => array_merge(array_keys($userChanges), array_keys($profileChanges)),
                'email_change_requested' => $emailPending,
            ],
            $this->request->ip()
        );

        $this->flash('success', $emailPending
            ? 'Saved. The new email address takes effect once they confirm it from that inbox.'
            : 'Saved.');

        return $this->redirect($this->showUrl($userId));
    }

    public function delete(string $id): Response
    {
        $user = $this->target($id);
        $this->guardAdministratorTarget($user);

        return $this->view('user.delete', [
            'user' => $user,
            'blockers' => $this->erasure->blockers((int) $user['id']),
            'canDelete' => $this->erasure->canErase((int) $user['id']),
        ]);
    }

    public function destroy(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        // Deleting yourself from the admin panel would orphan the session mid-request
        if ((int) $id === $this->actorId()) {
            $this->flash('error', 'You cannot delete your own account from here.');

            return $this->redirect('/admin/users');
        }

        $user = $this->target($id);
        $this->guardAdministratorTarget($user);

        if (!$this->erasure->canErase((int) $user['id'])) {
            $this->flash('error', 'This account cannot be deleted yet. The reasons are listed below.');

            return $this->redirect('/admin/users/'.(int) $user['id'].'/delete');
        }

        $this->erasure->erase((int) $user['id'], $this->actorId(), $this->request->ip());

        // Written after the erase succeeds, so it never claims a deletion that
        // failed. It survives the row because activity_log has no foreign key.
        audit()->log(
            $this->actorId(),
            'user.erased',
            'user',
            (int) $user['id'],
            ['handle' => $user['handle']],
            $this->request->ip()
        );

        $this->flash('success', 'User deleted.');

        return $this->redirect('/admin/users');
    }

    /**
     * @return array<string, string>
     */
    private function updateRules(int $userId): array
    {
        $rules = [
            'handle' => 'required|user_handle|min:2|max:50|unique:users,handle,'.$userId,
            'email' => 'required|email|unique:users,email,'.$userId,
            'first_name' => 'max:50',
            'last_name' => 'max:50',
            'bio' => 'max:1000',
            'occupation' => 'max:100',
            'location' => 'max:100',
            'timezone' => 'timezone',
            'locale' => 'in:auto,'.implode(',', $this->locales->supported()),
            'default_blog_id' => 'integer',
        ];

        foreach (self::SOCIAL_NETWORKS as $network) {
            $rules[$network] = 'url|max:255';
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> Columns that changed
     */
    private function saveProfile(int $userId, array $input): array
    {
        $current = $this->dossier->profile($userId) ?? [];

        $changes = changedFields([
            'bio' => $input['bio'] ?? '',
            'occupation' => $input['occupation'] ?? '',
            'location' => $input['location'] ?? '',
            'is_public' => $this->request->postParam('is_public') ? '1' : '0',
        ], $current);

        // Moderation removes an avatar, it never uploads one on someone's behalf.
        if ($this->request->postParam('remove_avatar') && !empty($current['avatar_url'])) {
            $changes['avatar_url'] = null;
            $changes['avatar_source_url'] = null;
            $changes['avatar_crop'] = null;
        }

        if ($changes !== []) {
            $this->profiles->upsert($userId, $changes);
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function saveSocialLinks(int $userId, array $input): void
    {
        $changes = changedFields(
            array_combine(self::SOCIAL_NETWORKS, array_map(static fn (string $n): string => (string) ($input[$n] ?? ''), self::SOCIAL_NETWORKS)),
            $this->dossier->socialLinks($userId)
        );

        foreach ($changes as $network => $url) {
            $this->socials->upsertLink($userId, $network, $url);
        }
    }

    /**
     * Only the notification toggles this account is shown on its own settings
     * page are written. A hidden toggle is absent from the POST as well, and
     * writing 0 for it would switch off a notification they never saw.
     *
     * @param  array<string, mixed>  $input
     */
    private function savePreferences(int $userId, array $input, int $defaultBlogId): void
    {
        $data = [
            'display_name_preference' => $this->request->postParam('show_name') ? 'name' : 'handle',
            'timezone' => ($input['timezone'] ?? '') === '' ? null : $input['timezone'],
            'locale' => ($input['locale'] ?? 'auto') === 'auto' ? null : $input['locale'],
        ];

        foreach ($this->notificationScope->applicableKeys($userId) as $key) {
            $data[$key] = $this->request->postParam($key) ? 1 : 0;
        }

        $this->preferences->upsert($userId, $data);

        // Not an upsert column: these two also clear the cached dashboard sidebar.
        if ($defaultBlogId === 0) {
            $this->preferences->clearDefaultBlogId($userId);
        } else {
            $this->preferences->setDefaultBlogId($userId, $defaultBlogId);
        }
    }

    /**
     * @param  array<string, mixed>  $user
     * @return bool True when a confirmation link was sent
     */
    private function startEmailChange(array $user, string $newEmail): bool
    {
        $newEmail = strtolower(trim($newEmail));

        if ($newEmail === strtolower((string) $user['email'])) {
            return false;
        }

        $this->emailChanges->issue((int) $user['id'], $newEmail);

        return true;
    }

    /**
     * @return array<int, string> blog id => name, for the default blog choice
     */
    private function accessibleBlogChoices(int $userId): array
    {
        $choices = [];

        foreach ($this->blogModel->getAccessibleBlogs($userId) as $blog) {
            $choices[(int) $blog['id']] = (string) $blog['blog_name'];
        }

        return $choices;
    }

    private function roleForNewAccount(): ?int
    {
        $roles = $this->roleModel->findByScope('system');
        $canAssign = Gate::allows('assignSystemRoles', SystemResource::class, $this->actor());

        // A delegate may create accounts but not choose their role: that choice
        // includes Administrator. Whatever they post, the default applies.
        $wanted = $canAssign ? (int) $this->request->postParam('role_id', 0) : 0;

        foreach ($roles as $role) {
            if ($wanted !== 0 && (int) $role['id'] === $wanted) {
                return (int) $role['id'];
            }
        }

        if ($wanted !== 0) {
            return null;
        }

        foreach ($roles as $role) {
            if ($role['role_slug'] === self::DEFAULT_SITE_ROLE) {
                return (int) $role['id'];
            }
        }

        return null;
    }

    /**
     * Recompute the cached display name and clear public pages after anything
     * readers see moved, the same as the profile form does, or they keep seeing
     * the old identity until the cache TTL.
     *
     * @param  array<string, mixed>  $user  The record as it was before the edit
     * @param  array<string, mixed>  $userChanges
     * @param  array<string, mixed>  $profileChanges
     */
    private function refreshPublicIdentity(array $user, array $userChanges, array $profileChanges): void
    {
        $newDisplayName = $this->displayNames->refreshCached((int) $user['id']);

        $moved = isset($userChanges['handle'])
            || isset($profileChanges['is_public'])
            || array_key_exists('avatar_url', $profileChanges)
            || $newDisplayName !== $user['display_name_cached'];

        if ($moved) {
            $this->cacheInvalidator->purgeAuthorSurfaces((string) $user['handle']);
        }
    }
}
