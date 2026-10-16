<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Captcha\CaptchaInterface;
use App\Services\LoginRateLimiter;
use Framework\Core\Response;

/**
 * Hands out sign-in check challenges on demand.
 *
 * POST /captcha/challenge → challenge() (JSON, never cached)
 *
 * Challenges are fetched when a form needs one rather than printed into the
 * page, because the pages carrying the auth modal come from the public cache.
 * POST keeps the email out of URLs and logs, and the CSRF middleware means a
 * session already exists by the time one is asked for.
 */
final class CaptchaController extends AppController
{
    public function __construct(
        private CaptchaInterface $captcha,
        private LoginRateLimiter $loginLimiter,
    ) {}

    /**
     * Issue a challenge for login (bound to the email) or registration.
     */
    public function challenge(): Response
    {
        $this->response->addHeader('Cache-Control', 'no-store');

        $purpose = (string) ($this->request->post['purpose'] ?? '');
        if (!in_array($purpose, ['login', 'register'], true)) {
            return $this->jsonError('Unknown purpose.', 422);
        }

        $email = '';
        if ($purpose === 'login') {
            $validator = $this->validate(['email' => 'required|email']);
            if ($validator->fails()) {
                return $this->jsonError(chrome_translate('auth.emailInvalid'), 422);
            }
            $email = (string) $validator->validated()['email'];
        }

        // Issuing costs the server a PBKDF2 derive, so this must not become free work on demand
        $key = 'captcha:challenge:ip:'.($this->request->ip() ?? 'unknown');
        $perMinute = (int) (require ROOT_PATH.'/config/captcha.php')['challenges_per_minute'];
        if (rateLimiter()->tooManyAttempts($key, $perMinute, 60)) {
            $this->response->addHeader('Retry-After', '60');

            return $this->jsonError(chrome_translate('auth.checkTooMany'), 429);
        }
        rateLimiter()->hit($key, 60);

        $level = $purpose === 'login' ? $this->loginLimiter->challengeLevel($email) : 0;

        return $this->jsonSuccess(['challenge' => $this->captcha->issue($purpose, $email, $level)]);
    }
}
