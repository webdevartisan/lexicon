<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\AppController;
use App\Models\UserModel;
use App\Services\TwoFactor\TwoFactorRateLimiter;
use App\Services\TwoFactor\TwoFactorService;
use App\Traits\AnswersSignIn;
use Framework\Core\Response;

/**
 * The second sign-in step for accounts with an authenticator app.
 *
 * The password step parks the user id in the session (see AuthController); this
 * controller turns that into a signed-in session once a valid code, or one of
 * the recovery codes, arrives. Wrong codes are charged to the account, not the
 * address, so moving around does not buy more guesses. The full page and the
 * blog modal post to the same action; the modal gets JSON back.
 *
 * GET  /login/two-factor → show()
 * POST /login/two-factor → submit()
 */
final class TwoFactorChallengeController extends AppController
{
    use AnswersSignIn;

    public const PENDING_KEY = 'two_factor_pending';

    private const WINDOW_SECONDS = 600;

    public function __construct(
        private TwoFactorService $twoFactor,
        private TwoFactorRateLimiter $throttle,
        private UserModel $users,
    ) {}

    public function show(): Response
    {
        $pending = $this->pending();
        if ($pending === null) {
            return $this->redirect('/login');
        }

        return $this->view('auth.login.two-factor', ['return_to' => $pending['return_to']]);
    }

    public function submit(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $pending = $this->pending();
        if ($pending === null) {
            // 419 tells the modal the step is gone and it has to start over
            return $this->backToLogin(chrome_translate('authModal.sessionExpired'), 419);
        }

        $userId = (int) $pending['user_id'];

        if ($this->throttle->tooManyAttempts($userId)) {
            return $this->refuse($pending, chrome_translate('auth.twoFactorTooMany'), 429);
        }

        $code = (string) ($this->request->post['code'] ?? '');

        if ($code === '' || !$this->twoFactor->verify($userId, $code)) {
            $this->throttle->hit($userId);
            audit()->log($userId, 'auth.two_factor_failed', 'user', $userId, [], $this->request->ip());

            return $this->refuse($pending, chrome_translate('auth.twoFactorWrong'), 422);
        }

        $this->throttle->clear($userId);

        $user = $this->users->findById($userId);
        $this->session->remove(self::PENDING_KEY);

        // Suspended or removed between the two steps: the same answer a wrong password gets
        if (!$user || (int) $user['is_active'] !== 1) {
            return $this->backToLogin(chrome_translate('auth.invalidCredentials'), 401);
        }

        auth()->startSession($user);

        return $this->finishSignIn($pending['return_to']);
    }

    /**
     * The parked sign-in, or null when there is none or its window has closed.
     *
     * @return array{user_id: int, return_to: string|null, at: int}|null
     */
    private function pending(): ?array
    {
        $pending = $this->session->get(self::PENDING_KEY);

        if (!is_array($pending) || !isset($pending['user_id'], $pending['at'])) {
            return null;
        }

        if (time() - (int) $pending['at'] > self::WINDOW_SECONDS) {
            $this->session->remove(self::PENDING_KEY);

            return null;
        }

        $pending['return_to'] = $pending['return_to'] ?? null;

        return $pending;
    }

    /**
     * Turn a code down and keep the step open for another try.
     *
     * @param  array{user_id: int, return_to: string|null, at: int}  $pending
     */
    private function refuse(array $pending, string $message, int $status): Response
    {
        if ($this->request->isAjax()) {
            return $this->jsonError($message, $status);
        }

        $response = $this->view('auth.login.two-factor', [
            'error' => $message,
            'return_to' => $pending['return_to'],
        ]);
        $response->setStatusCode($status);

        return $response;
    }
}
