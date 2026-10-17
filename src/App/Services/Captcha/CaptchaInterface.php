<?php

declare(strict_types=1);

namespace App\Services\Captcha;

/**
 * Issues challenges and checks the answers sent back with a form.
 */
interface CaptchaInterface
{
    /**
     * Create a challenge for the browser to solve.
     *
     * @param  string  $purpose  What the answer may be used for ('login', 'register')
     * @param  string  $subject  What the answer is bound to, such as the email signing in; '' binds nothing
     * @param  int  $level  Difficulty step, clamped to the configured maximum
     * @return array<string, mixed> Challenge as the browser receives it
     */
    public function issue(string $purpose, string $subject = '', int $level = 0): array;

    /**
     * Check an answer and spend it, so it cannot be used again.
     *
     * @param  string  $payload  Answer posted by the browser
     * @param  string  $purpose  Must match the purpose it was issued for
     * @param  string  $subject  Must match the subject it was issued for
     */
    public function verify(string $payload, string $purpose, string $subject = ''): CaptchaResult;
}
