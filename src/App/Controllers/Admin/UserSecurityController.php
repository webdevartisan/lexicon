<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Gate;
use App\Models\PasswordResetModel;
use App\Models\UserModel;
use App\Resources\SystemResource;
use App\Services\AccountErasureService;
use App\Services\ImpersonationService;
use App\Services\PasswordResetIssuer;
use Framework\Core\Response;
use RuntimeException;

/**
 * Credential and identity actions on one account: resetting its password and
 * signing in as it.
 *
 * Both are account takeover in the wrong hands, so each action re-checks who
 * may do it against the database at the moment it runs.
 */
class UserSecurityController extends ManagedUserController
{
    public function __construct(
        UserModel $users,
        AccountErasureService $erasure,
        private PasswordResetIssuer $resetIssuer,
        private PasswordResetModel $passwordResets,
        private ImpersonationService $impersonation,
    ) {
        parent::__construct($users, $erasure);
    }

    public function password(string $id): Response
    {
        $user = $this->target($id);
        $this->guardAdministratorTarget($user);

        return $this->view('user.password', [
            'user' => $user,
            'isSelf' => (int) $user['id'] === $this->actorId(),
        ]);
    }

    /**
     * Email the account the same reset link the forgot-password form sends.
     * The recommended path: nobody but the account holder ever sees a password.
     */
    public function sendResetLink(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = $this->target($id);
        $this->guardAdministratorTarget($user);
        $userId = (int) $user['id'];

        try {
            $this->resetIssuer->issue($user);
        } catch (RuntimeException $e) {
            $this->flash('error', $e->getMessage());

            return $this->redirect('/admin/users/'.$userId.'/password');
        }

        audit()->log(
            $this->actorId(),
            'user.password_reset_link_sent',
            'user',
            $userId,
            ['by_admin' => true],
            $this->request->ip()
        );

        $this->flash('success', sprintf(
            'A reset link is on its way to @%s. It works for %d minutes.',
            $user['handle'],
            PasswordResetIssuer::LIFETIME_MINUTES
        ));

        return $this->redirect($this->showUrl($userId));
    }

    /**
     * Set an exact password. Validated by the same rule as sign-up, never a
     * weaker admin-only one, and every session the account had is ended.
     */
    public function setPassword(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = $this->target($id);
        $this->guardAdministratorTarget($user);
        $userId = (int) $user['id'];

        // Your own password belongs on your security page, which asks for the
        // current one. Here it would also sign you straight out.
        if ($userId === $this->actorId()) {
            $this->flash('error', 'Change your own password from your account security page.');

            return $this->redirect('/admin/users/'.$userId.'/password');
        }

        $validator = $this->validateOrFail([
            'password' => 'required|password:'.password_policy_preset(),
            // Named confirm_password because that is one of the fields stripped from
            // old input on a failed validation, so neither copy is echoed back.
            'confirm_password' => 'required|same:password',
        ]);

        $hash = password_hash((string) $validator->validated()['password'], PASSWORD_DEFAULT);

        if (!$this->users->updatePasswordHashById($userId, $hash)) {
            $this->flash('error', 'The password was not changed. Nothing was written, so try again.');

            return $this->redirect('/admin/users/'.$userId.'/password');
        }

        $this->users->bumpSessionEpoch($userId);
        $this->passwordResets->deleteForEmail((string) $user['email']);

        // The password itself is never logged, only that it changed and by whom.
        audit()->log(
            $this->actorId(),
            'user.password_set_by_admin',
            'user',
            $userId,
            ['sessions_ended' => true],
            $this->request->ip()
        );

        $this->flash('success', sprintf(
            'Password changed for @%s. They have been signed out everywhere and any reset links they had are cancelled.',
            $user['handle']
        ));

        return $this->redirect($this->showUrl($userId));
    }

    public function confirmImpersonate(string $id): Response
    {
        Gate::authorize('impersonateUsers', SystemResource::class, $this->actor());

        $user = $this->target($id);

        return $this->view('user.impersonate', [
            'user' => $user,
            'refusal' => $this->impersonation->refusalReason($this->actor(), (int) $user['id']),
            'maxMinutes' => ImpersonationService::MAX_MINUTES,
        ]);
    }

    public function impersonate(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));
        Gate::authorize('impersonateUsers', SystemResource::class, $this->actor());

        $user = $this->target($id);
        $userId = (int) $user['id'];

        $validator = $this->validateOrFail([
            'reason' => 'required|min:3|max:255',
        ]);

        try {
            $this->impersonation->start(
                $this->actor(),
                $userId,
                trim((string) $validator->validated()['reason']),
                $this->request->ip()
            );
        } catch (RuntimeException $e) {
            // start() changes nothing before its guard passes, so the admin is
            // still exactly who they were and can simply be told why.
            $this->flash('error', 'You are still signed in as yourself. '.$e->getMessage());

            return $this->redirect('/admin/users/'.$userId.'/impersonate');
        }

        // The admin area is out of reach as the target, so land on the front.
        return $this->redirect('/');
    }
}
