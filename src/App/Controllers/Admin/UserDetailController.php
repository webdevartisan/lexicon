<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Gate;
use App\Models\UserModel;
use App\Resources\SystemResource;
use App\Services\AccountErasureService;
use App\Services\ImpersonationService;
use App\Services\ProfileService;
use App\Services\UserDossierService;
use Framework\Core\Response;
use Framework\Exceptions\NotFoundException;

/**
 * Read-only views of one account: the investigation page, and what the public
 * sees of it.
 *
 * Nothing here writes. Viewing an account leaves no trace on it, which is why
 * the page reads through UserDossierService rather than the findOrCreate()
 * helpers the settings pages use.
 */
class UserDetailController extends ManagedUserController
{
    public function __construct(
        UserModel $users,
        AccountErasureService $erasure,
        private UserDossierService $dossier,
        private ProfileService $profiles,
        private ImpersonationService $impersonation,
    ) {
        parent::__construct($users, $erasure);
    }

    public function show(string $id): Response
    {
        $user = $this->target($id);
        $userId = (int) $user['id'];
        $actor = $this->actor();

        return $this->view('user.show', array_merge($this->dossier->build($user), [
            'user' => $user,
            'isSelf' => $userId === $this->actorId(),
            'targetIsAdmin' => $this->isAdministrator($userId),
            'actorIsAdmin' => Gate::allows('actOnAdministrators', SystemResource::class, $actor),
            'canAssignSiteRoles' => Gate::allows('assignSystemRoles', SystemResource::class, $actor),
            'impersonationRefusal' => Gate::allows('impersonateUsers', SystemResource::class, $actor)
                ? $this->impersonation->refusalReason($actor, $userId)
                : 'Only administrators can sign in as another account.',
            'publicVisibility' => $this->publicVisibility($user),
        ]));
    }

    /**
     * Send the admin to the real public profile, or explain why there is none.
     *
     * Deliberately not a copy of the public page and not a bypass of its
     * rules: it asks ProfileService, the same code a visitor goes through, so
     * the answer here cannot drift from what a visitor actually gets.
     */
    public function preview(string $id): Response
    {
        $user = $this->target($id);
        $visibility = $this->publicVisibility($user);

        if ($visibility['visible']) {
            return $this->redirect('/profile/'.rawurlencode((string) $user['handle']));
        }

        return $this->view('user.preview', [
            'user' => $user,
            'visibility' => $visibility,
        ]);
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array{visible: bool, reasons: list<string>}
     */
    private function publicVisibility(array $user): array
    {
        try {
            $this->profiles->getPublicProfile((string) $user['handle']);

            return ['visible' => true, 'reasons' => []];
        } catch (NotFoundException) {
            // The public page answers only "not found", so the reasons are
            // worked out here for the admin, from the same fields it checks.
        }

        $reasons = [];
        $profile = $this->dossier->profile((int) $user['id']);

        if ((int) $user['is_active'] !== 1) {
            $reasons[] = $user['suspended_at'] !== null
                ? 'The account is suspended, and a suspended account has no public page.'
                : 'The account is deactivated (it may be waiting out a self-requested deletion).';
        }

        if ($profile === null) {
            $reasons[] = 'The account has never saved a profile, so there is no public page yet.';
        } elseif ((int) $profile['is_public'] !== 1) {
            $reasons[] = 'The profile is set to private.';
        }

        if ($reasons === []) {
            $reasons[] = 'The public page could not be shown for a reason this screen does not recognise. Check the error log.';
        }

        return ['visible' => false, 'reasons' => $reasons];
    }
}
