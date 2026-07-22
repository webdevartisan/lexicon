<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\AppController;
use App\Mail\PasswordResetEmail;
use App\Models\PasswordResetModel;
use App\Models\UserModel;
use App\Services\PasswordResetRateLimiter;
use Exception;
use Framework\Core\Response;

/**
 * Handle password reset workflow.
 *
 * Implements secure, rate-limited password reset using time-limited tokens.
 * Prevents enumeration, brute force, and spam attacks through multi-tier rate limiting.
 */
final class PasswordController extends AppController
{
    // One neutral message for every forgot-flow outcome prevents enumeration
    private const RESET_SENT_MESSAGE = 'If that email exists in our system, a reset link has been sent.';

    /**
     * Inject dependencies for user lookup, tokens, and rate limiting.
     */
    public function __construct(
        private UserModel $users,
        private PasswordResetModel $passwordResets,
        private PasswordResetRateLimiter $limiter
    ) {}

    /**
     * Show "forgot password" form.
     *
     * Public form for email-based reset request.
     */
    public function showForgotForm(): Response
    {
        if (auth()->check()) {
            return $this->redirect('/dashboard');
        }

        return $this->view('auth.password.forgot');
    }

    /**
     * Send password reset link to user's email.
     *
     * Rate limited per email to prevent spam and enumeration attacks.
     * Always returns generic success message regardless of email existence.
     */
    public function submit(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $validator = $this->validate(['email' => 'required|email']);

        if ($validator->fails()) {
            return $this->forgotResponse(false, 'Please enter a valid email address.', 422);
        }

        $email = trim((string) $validator->validated()['email']);
        $ip = $this->request->ip();

        // Check rate limit BEFORE any processing
        if ($this->limiter->tooManyAttempts($ip, $email)) {
            $wait = $this->limiter->availableIn($ip, $email);
            $waitFormatted = $this->formatWaitTime($wait);

            return $this->forgotResponse(false, "Too many password reset attempts. Try again in {$waitFormatted}.", 429);
        }

        // Look up user
        $user = $this->users->findByEmail($email);

        // Always increment rate limit (prevents enumeration)
        $this->limiter->hit($ip, $email);

        // Generic response prevents email enumeration
        if (!$user) {
            audit()->log(
                null,
                'password_reset.attempt_unknown_email',
                'user',
                0,
                ['email' => $email],
                $ip
            );

            // Same success message as if email existed
            return $this->forgotResponse(true, self::RESET_SENT_MESSAGE);
        }

        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        // Store token (replaces any existing for this email)
        if (!$this->passwordResets->replaceForEmail($email, $tokenHash, $expiresAt)) {
            error_log("Failed to store password reset token for {$email}");

            // Still show success to prevent enumeration
            return $this->forgotResponse(true, self::RESET_SENT_MESSAGE);
        }

        // Send email
        try {
            if (env('MAIL_ENABLED', false) === false) {
                error_log("MAIL_ENABLED is false. Skipping password reset email to {$email}");

                // Show success anyway to prevent enumeration
                return $this->forgotResponse(true, self::RESET_SENT_MESSAGE);
            }

            // Queued rather than sent inline. Inline was the one mode with no
            // retry, so a transport blip lost the reset outright. The critical
            // tier worker runs every minute, so this still lands in seconds.
            mail_queue()->enqueue(new PasswordResetEmail($user, $token, 60), 'user', (int) $user['id']);

            // Audit successful email send
            audit()->log(
                (int) $user['id'],
                'password_reset.email_sent',
                'user',
                (int) $user['id'],
                ['email' => $email],
                $ip
            );
        } catch (Exception $e) {
            error_log('Failed to send password reset email: '.$e->getMessage());

            // Still show success to prevent enumeration
            return $this->forgotResponse(true, self::RESET_SENT_MESSAGE);
        }

        return $this->forgotResponse(true, self::RESET_SENT_MESSAGE);
    }

    /**
     * Shared ending for the forgot flow: JSON for the blog-front modal,
     * flash + redirect for the standalone page.
     */
    private function forgotResponse(bool $ok, string $message, int $errorStatus = 422): Response
    {
        if ($this->request->isAjax()) {
            return $ok
                ? $this->jsonSuccess(['message' => $message])
                : $this->jsonError($message, $errorStatus);
        }

        $this->flash($ok ? 'success' : 'error', $message);

        return $this->redirect('/password/forgot');
    }

    /**
     * Show password reset form for valid token.
     *
     * Validates token before rendering form.
     * No rate limiting on viewing - users should be able to view their own reset link.
     */
    public function showResetForm(string $token): Response
    {
        if (auth()->check()) {
            return $this->redirect('/dashboard');
        }

        $ip = $this->request->ip();
        $tokenHash = hash('sha256', $token);

        // Look up token to get associated email
        $resetData = $this->passwordResets->findValidByTokenHash($tokenHash);

        if (!$resetData) {
            audit()->log(
                0,
                'password_reset.invalid_token_viewed',
                'password_reset',
                0,
                ['token_hash_prefix' => substr($tokenHash, 0, 8)],
                $ip
            );

            $this->flash('error', 'Invalid or expired password reset link.');

            return $this->redirect('/password/forgot');
        }

        // Render form with pre-filled email
        return $this->view('auth.password.reset', [
            'token' => $token,
            'email' => $resetData['email'],
        ]);
    }

    /**
     * Process password reset form submission.
     *
     * Validates token, checks rate limits, and updates user password.
     * Rate limited to prevent token brute-forcing.
     */
    public function resetPassword(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $token = trim($this->request->post['token'] ?? '');
        $email = trim($this->request->post['email'] ?? '');
        $password = $this->request->post['password'] ?? '';
        $passwordConfirm = $this->request->post['password_confirm'] ?? '';
        $ip = $this->request->ip();

        // Basic validation
        if ($token === '' || $email === '') {
            $this->flash('error', 'Invalid reset request.');

            return $this->redirect('/password/forgot');
        }

        // Rate limit check BEFORE processing
        if ($this->limiter->tooManyAttempts($ip, $email)) {
            $wait = $this->limiter->availableIn($ip, $email);
            $waitFormatted = $this->formatWaitTime($wait);

            $this->flash('error', "Too many password reset attempts. Try again in {$waitFormatted}.");

            return $this->redirect('/password/forgot');
        }

        // Validate password fields
        $validator = $this->validateOrFail([
            'password' => 'required|password:basic',
            'password_confirm' => 'required|same:password',
        ], [
            'password.required' => 'Password is required.',
            'password.password' => 'Password must be at least 8 characters.',
            'password_confirm.required' => 'Password confirmation is required.',
            'password_confirm.same' => 'Password confirmation does not match.',
        ]);

        $validated = $validator->validated();

        // Validate token still valid and matches email
        $tokenHash = hash('sha256', $token);
        $resetData = $this->passwordResets->findValidByTokenHash($tokenHash);

        if (!$resetData || $resetData['email'] !== $email) {
            // Increment rate limit on failed token validation
            $this->limiter->hit($ip, $email);

            audit()->log(
                0,
                'password_reset.failed_token_validation',
                'password_reset',
                0,
                ['email' => $email, 'token_hash_prefix' => substr($tokenHash, 0, 8)],
                $ip
            );

            $this->flash('error', 'Invalid or expired password reset link.');

            return $this->redirect('/password/forgot');
        }

        // Look up user
        $user = $this->users->findByEmail($email);
        if (!$user) {
            $this->limiter->hit($ip, $email);

            $this->flash('error', 'User not found.');

            return $this->redirect('/password/forgot');
        }

        // Update password
        $newHash = password_hash($validated['password'], PASSWORD_DEFAULT);
        if (!$this->users->updatePasswordHashById((int) $user['id'], $newHash)) {
            error_log("Failed to update password for user {$user['id']}");

            $this->flash('error', 'Failed to reset password. Please try again.');

            return $this->redirect('/password/reset/'.$token);
        }

        // Invalidate token
        $this->passwordResets->deleteByTokenHash($tokenHash);

        // Clear rate limits on successful reset
        $this->limiter->clear($ip, $email);

        // Audit success
        audit()->log(
            (int) $user['id'],
            'password_reset.completed',
            'user',
            (int) $user['id'],
            ['email' => $email],
            $ip
        );

        $this->flash('success', 'Your password has been reset successfully. Please log in.');

        return $this->redirect('/login');
    }

    /**
     * Format wait time for user-friendly display.
     *
     * Converts seconds to "X minutes" or "X seconds" for better UX.
     *
     * @param  int  $seconds  Seconds to wait
     * @return string Formatted wait time
     */
    private function formatWaitTime(int $seconds): string
    {
        if ($seconds > 120) {
            return ceil($seconds / 60).' minutes';
        }

        return $seconds.' seconds';
    }
}
