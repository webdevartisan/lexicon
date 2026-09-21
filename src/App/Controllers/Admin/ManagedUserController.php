<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Gate;
use App\Models\UserModel;
use App\Resources\SystemResource;
use App\Services\AccountErasureService;
use Framework\Exceptions\PageNotFoundException;

/**
 * Shared ground for the admin actions that operate on one account.
 *
 * Every action here re-checks authorization on the server. The row menu hides
 * what a viewer may not do, but hiding is a convenience: the checks below are
 * what stop a forged or replayed request.
 */
abstract class ManagedUserController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageUsers';

    public function __construct(
        protected UserModel $users,
        protected AccountErasureService $erasure,
    ) {}

    /**
     * The account an action targets. The shared deleted-user placeholder is
     * treated as missing: nothing here should ever be done to it.
     *
     * @return array<string, mixed>
     */
    protected function target(string $id): array
    {
        $user = $this->users->find($id);

        if (!$user || $this->erasure->isDeletedUserAccount((int) $user['id'])) {
            throw new PageNotFoundException("User with ID '{$id}' not found.");
        }

        return $user;
    }

    /**
     * Refuse when the target is an administrator and the actor is not.
     *
     * Roles are re-read here rather than trusted from the list the page was
     * drawn from, so a role granted a moment ago still counts.
     *
     * @param  array<string, mixed>  $target
     */
    protected function guardAdministratorTarget(array $target): void
    {
        if ($this->isAdministrator((int) $target['id'])) {
            Gate::authorize('actOnAdministrators', SystemResource::class, $this->actor());
        }
    }

    protected function isAdministrator(int $userId): bool
    {
        return in_array('administrator', $this->users->getUserRoles($userId), true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function actor(): array
    {
        return auth()->user() ?? [];
    }

    protected function actorId(): int
    {
        return (int) ($this->actor()['id'] ?? 0);
    }

    protected function showUrl(int $userId): string
    {
        return '/admin/users/'.$userId;
    }
}
