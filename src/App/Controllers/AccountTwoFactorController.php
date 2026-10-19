<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\Sessions\RecentPasswordConfirmation;
use App\Services\Sessions\SessionRevoker;
use App\Services\TwoFactor\TwoFactorPolicy;
use App\Services\TwoFactor\TwoFactorRateLimiter;
use App\Services\TwoFactor\TwoFactorService;
use App\Traits\RendersAccountPanel;
use Framework\Core\Response;

/**
 * Turning the authenticator-app second step on and off, and its recovery codes.
 *
 * Every change asks for the account password first (the same short-lived
 * confirmation the sessions page uses), so a stolen session cannot switch the
 * protection off. Recovery codes are shown once: they travel through the
 * session for a single page view and are gone after it.
 */
final class AccountTwoFactorController extends AppController
{
    use RendersAccountPanel;

    private const NEW_CODES_KEY = 'two_factor_new_codes';

    public function __construct(
        private TwoFactorService $twoFactor,
        private TwoFactorRateLimiter $throttle,
        private RecentPasswordConfirmation $confirmation,
        private SessionRevoker $revoker,
        private UserModel $users,
        private TwoFactorPolicy $policy,
    ) {}

    public function edit(): Response
    {
        $user = $this->currentUser();
        $newCodes = $this->session->get(self::NEW_CODES_KEY);
        $this->session->remove(self::NEW_CODES_KEY);

        $secret = $this->twoFactor->isEnabled($user) ? null : $this->twoFactor->pendingSecret((int) $user['id']);

        return $this->accountView('two-factor', [
            'user' => $user,
            'enabled' => $this->twoFactor->isEnabled($user),
            'codesLeft' => $this->twoFactor->recoveryCodesLeft($user),
            'newCodes' => is_array($newCodes) ? $newCodes : null,
            'secret' => $secret !== null ? $this->twoFactor->groupedSecret($secret) : null,
            'qr' => $secret !== null ? $this->twoFactor->qrSvg($secret, (string) $user['email'], site_setting('site_name', 'Lexicon')) : null,
            'needsPassword' => !$this->confirmation->isFresh(),
            'required' => $this->policy->isRequiredFor($user),
        ]);
    }

    public function start(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = $this->currentUser();
        if ($this->twoFactor->isEnabled($user)) {
            return $this->back();
        }

        if (!$this->passwordOk($user)) {
            return $this->back();
        }

        $this->twoFactor->beginSetup((int) $user['id']);

        return $this->back();
    }

    public function confirm(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = $this->currentUser();
        $userId = (int) $user['id'];

        if ($this->twoFactor->isEnabled($user)) {
            return $this->back();
        }

        if ($this->throttle->tooManyAttempts($userId)) {
            $this->flash('error', chrome_translate('auth.twoFactorTooMany'));

            return $this->back();
        }

        $codes = $this->twoFactor->confirmSetup($userId, (string) ($this->request->post['code'] ?? ''));

        if ($codes === null) {
            $this->throttle->hit($userId);
            $this->flash('error', chrome_translate('account.twoFactor.wrongCode'));

            return $this->back();
        }

        $this->throttle->clear($userId);
        audit()->log($userId, 'user.two_factor_enabled', 'user', $userId, [], $this->request->ip());

        // Anyone who got in with the password alone is now outside the new protection.
        $this->revoker->endOthers($userId, 'two_factor_enabled');

        $this->session->set(self::NEW_CODES_KEY, $codes);
        $this->flash('success', chrome_translate('account.twoFactor.enabled'));

        return $this->back();
    }

    public function regenerate(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = $this->currentUser();
        $userId = (int) $user['id'];

        if (!$this->twoFactor->isEnabled($user) || !$this->passwordOk($user)) {
            return $this->back();
        }

        $this->session->set(self::NEW_CODES_KEY, $this->twoFactor->regenerateRecoveryCodes($userId));
        audit()->log($userId, 'user.two_factor_codes_renewed', 'user', $userId, [], $this->request->ip());
        $this->flash('success', chrome_translate('account.twoFactor.codesRenewed'));

        return $this->back();
    }

    public function disable(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = $this->currentUser();
        $userId = (int) $user['id'];

        if (!$this->twoFactor->isEnabled($user) || !$this->passwordOk($user)) {
            return $this->back();
        }

        if ($this->policy->isRequiredFor($user)) {
            $this->flash('error', chrome_translate('account.twoFactor.requiredForRole'));

            return $this->back();
        }

        $this->twoFactor->disable($userId);
        audit()->log($userId, 'user.two_factor_disabled', 'user', $userId, [], $this->request->ip());
        $this->revoker->endOthers($userId, 'two_factor_disabled');
        $this->flash('success', chrome_translate('account.twoFactor.disabled'));

        return $this->back();
    }

    /**
     * @return array<string, mixed>
     */
    private function currentUser(): array
    {
        $user = $this->users->findById((int) auth()->user()['id']);

        if (!$user) {
            throw new \RuntimeException('Signed-in user has no row.');
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function passwordOk(array $user): bool
    {
        $password = (string) ($this->request->post['current_password'] ?? '');

        if ($this->confirmation->attempt((int) $user['id'], $password)) {
            return true;
        }

        $this->flash('error', chrome_translate('account.sessions.passwordRefused'));

        return false;
    }

    private function back(): Response
    {
        return $this->redirect(lurl('/account/security/two-factor'));
    }
}
