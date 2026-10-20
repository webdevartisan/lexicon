<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\AppController;
use App\Exceptions\AccountDeletedException;
use App\Exceptions\AccountSuspendedException;
use App\Services\Captcha\CaptchaInterface;
use App\Services\Captcha\CaptchaResult;
use App\Services\LoginRateLimiter;
use App\Services\TwoFactor\TwoFactorService;
use App\Traits\AnswersSignIn;
use Framework\Core\Response;

/**
 * Handle login and logout.
 *
 * GET  /login          → index()
 * POST /login          → submit()   (HTML form or AJAX from the blog-front modal)
 * POST /login/identify → identify() (modal step 1: does this email have an account?)
 * POST /logout         → logout()
 */
final class AuthController extends AppController
{
    use AnswersSignIn;

    /**
     * Show the main login form.
     *
     * Blog-front links pass ?return_to= so readers land back on the page
     * they were reading instead of the dashboard.
     */
    public function index(): Response
    {
        if (auth()->check()) {
            // See the same query string in Dashboard\HomeController::index():
            // a guest's browser can hold a cached, logged-out "/" for up to a
            // minute, and this redirect would otherwise replay it unchanged.
            return $this->redirect('/?welcome=1');
        }

        return $this->view('auth.login.index', [
            'return_to' => safe_return_to((string) ($this->request->get['return_to'] ?? '')),
        ]);
    }

    /**
     * Modal step 1: report whether an account exists for the email.
     *
     * This intentionally reveals account existence. The email-first modal
     * needs it to branch into login or inline registration. So it is
     * throttled by IP to keep enumeration expensive. Registration and
     * password reset already disclose the same fact.
     */
    public function identify(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $validator = $this->validate(['email' => 'required|email']);

        if ($validator->fails()) {
            return $this->jsonError('Please enter a valid email address.', 422);
        }

        $email = strtolower(trim((string) $validator->validated()['email']));

        $limiter = app(\App\Services\IdentifyRateLimiter::class);
        $ip = $this->request->ip() ?? 'unknown';

        // Record first, then check. Recording blocked attempts is what stops
        // the decayed score sliding back under the limit and leaking roughly
        // one lookup a minute to a client that just keeps retrying.
        $limiter->hit($ip);

        if ($limiter->tooManyAttempts($ip)) {
            $this->response->addHeader('Retry-After', (string) $limiter->availableIn($ip));

            return $this->jsonError('Too many attempts. Please try again later.', 429);
        }

        $exists = (bool) app(\App\Models\UserModel::class)->findByEmail($email);

        return $this->jsonSuccess(['exists' => $exists]);
    }

    /**
     * Render the masthead auth controls for the current session.
     *
     * The modal logs a reader in without navigating, which leaves the
     * server-rendered "Log in" pill on screen. This hands back the same
     * partial the layouts use so the header can be swapped in place.
     */
    public function nav(): Response
    {
        $variant = ($this->request->get['variant'] ?? '') === 'platform' ? 'platform' : 'theme';
        $returnTo = safe_return_to((string) ($this->request->get['return_to'] ?? '')) ?? '/';

        $html = $this->viewer()->render('partials/_auth_nav.lex.php', [
            'authNavVariant' => $variant,
            'authNavReturnTo' => $returnTo,
            'viewer' => app(\App\Services\ViewerContext::class)->current(),
        ]);

        // Never cache: the markup is specific to one logged-in reader.
        $this->response->addHeader('Cache-Control', 'no-store, must-revalidate');

        return $this->jsonSuccess(['html' => $html]);
    }

    /**
     * Handle login form submission.
     *
     * Serves both the full login page (redirects + flashes) and the
     * blog-front modal (JSON), so the two entry points share one set of
     * rate limits and redirect rules.
     */
    public function submit(): Response
    {
        // Enforce CSRF token for login POST
        csrf()->assertValid($this->request->postParam('_token'));

        $isAjax = $this->request->isAjax();

        // Safely read and normalize input
        $email = trim((string) ($this->request->post['email'] ?? ''));
        $password = (string) ($this->request->post['password'] ?? '');
        $returnTo = safe_return_to((string) ($this->request->post['return_to'] ?? ''));
        $ip = $this->request->ip();

        // Basic validation before attempting login
        $validator = $this->validate([
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
        ]);

        if ($validator->fails()) {
            $error = $this->validationErrorLine($validator->errors());

            if ($isAjax) {
                return $this->jsonError($error, 422);
            }

            // Re-render the form with validation errors and keep the email field filled
            return $this->view('auth.login.index', [
                'error' => $error,
                'email' => $email,
                'return_to' => $returnTo,
            ]);
        }

        // ---------------------------------------------------------
        // RATE LIMIT CHECK (BEFORE AUTH)
        // ---------------------------------------------------------
        $limiter = app(LoginRateLimiter::class);

        $blocked = $limiter->tooManyAttempts($ip, $email);

        if ($blocked) {
            $wait = $limiter->availableIn($ip, $email);

            if ($wait > 120) {
                $wait = ceil($wait / 60).' minutes';
            } else {
                $wait = $wait.' seconds';
            }

            if ($isAjax) {
                return $this->jsonError("Too many login attempts. Try again in {$wait}.", 429);
            }

            $this->flash('error', "Too many login attempts. Try again in {$wait}.");

            return $this->redirect('/login'.($returnTo !== null ? '?return_to='.urlencode($returnTo) : ''));
        }

        // The check comes before the password, so an attempt without a valid
        // answer never reaches it and is not charged to the account.
        $passedCheck = false;
        if ($limiter->requiresChallenge($ip, $email)) {
            $result = app(CaptchaInterface::class)->verify((string) ($this->request->post['altcha'] ?? ''), 'login', $email);

            if (!$result->passed()) {
                return $this->challengeRequired($limiter, $result, $email, $returnTo, $isAjax);
            }

            $passedCheck = true;
        }

        // ---------------------------------------------------------

        try {
            $user = auth()->verifyCredentials($email, $password);
        } catch (AccountSuspendedException|AccountDeletedException $e) {
            if ($isAjax) {
                return $this->jsonError($e->getMessage(), 403);
            }

            $this->flash('error', $e->getMessage());

            return $this->redirectBack();
        }

        if ($user !== null) {
            $limiter->clear($ip, $email);

            if (app(TwoFactorService::class)->isEnabled($user)) {
                return $this->askForSecondStep($user, $returnTo, $isAjax);
            }

            auth()->startSession($user);

            return $this->finishSignIn($returnTo);
        }

        // ---------------------------------------------------------
        // FAILED LOGIN → RECORD ATTEMPT
        // ---------------------------------------------------------
        $limiter->hit($ip, $email, $passedCheck);
        // ---------------------------------------------------------

        // Wrong email and wrong password look identical here, and the typed email is deliberately not stored.
        audit()->log(null, 'auth.login_failed', 'user', 0, [], $ip);

        if ($isAjax) {
            // The modal's email step already confirmed the account exists,
            // so a specific message doesn't disclose anything new
            return $this->jsonError('Incorrect password. Try again or reset it.', 401);
        }

        // Authentication failed - flash error and redirect back
        $this->flash('error', chrome_translate('auth.invalidCredentials'));

        // Return Response
        return $this->redirectBack();
    }

    /**
     * Park a correct password until the code from the authenticator app arrives.
     *
     * Nothing about the account is in the session yet beyond the user id and a
     * ten minute window, and a fresh session id keeps a planted one from being reused.
     *
     * @param  array<string, mixed>  $user
     */
    private function askForSecondStep(array $user, ?string $returnTo, bool $isAjax): Response
    {
        $this->session->regenerate(true);
        $this->session->set(TwoFactorChallengeController::PENDING_KEY, [
            'user_id' => (int) $user['id'],
            'return_to' => $returnTo,
            'at' => time(),
        ]);

        // The modal asks for the code in place; only the full page navigates
        return $isAjax ? $this->jsonSuccess(['two_factor' => true]) : $this->redirect(lurl('/login/two-factor'));
    }

    /**
     * Refuse a login that needs the sign-in check and hand back a fresh challenge.
     *
     * The modal gets 422 JSON with the challenge and retries once it is solved.
     * The page is re-rendered with the challenge, which its script solves
     * before the next submit. Without JavaScript the page shows a reset link.
     *
     * @param  string|null  $returnTo  Validated return path to keep on the form
     */
    private function challengeRequired(
        LoginRateLimiter $limiter,
        CaptchaResult $result,
        string $email,
        ?string $returnTo,
        bool $isAjax
    ): Response {
        $ip = $this->request->ip();
        $limiter->hitIp($ip);

        // A missing answer is the normal first step for someone who just reached the
        // check, so only answers that came back wrong are worth an audit row.
        if ($result !== CaptchaResult::Missing) {
            audit()->log(null, 'auth.captcha_failed', 'user', 0, ['reason' => $result->value], $ip);
        }

        $challenge = app(CaptchaInterface::class)->issue('login', $email, $limiter->challengeLevel($email));
        $message = chrome_translate($result === CaptchaResult::Missing ? 'auth.checkRequired' : 'auth.checkFailed');

        if ($isAjax) {
            return $this->json(['success' => false, 'error' => $message, 'captcha' => $challenge], 422);
        }

        $response = $this->view('auth.login.index', [
            'error' => $message,
            'email' => $email,
            'return_to' => $returnTo,
            'captcha' => $challenge,
        ]);
        $response->setStatusCode(422);

        return $response;
    }

    /**
     * Log the user out.
     *
     * POST-only with a CSRF check: a GET logout lets any third-party page end a
     * visitor's session with nothing more than <img src="/logout">.
     *
     * Blog-front logout forms post return_to so readers stay on the public blog
     * page they were reading; everything else goes home.
     */
    public function logout(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        // Signing out while impersonating must still close the impersonation
        // record, or the trail shows a session that was never ended. The admin
        // is then signed out as well, which is what the button said it would do.
        app(\App\Services\ImpersonationService::class)->stop('logout', $this->request->ip());

        auth()->logout();

        // Otherwise the next person at this browser starts out in the language
        // of the account that just left.
        app(\App\Services\SessionLocaleSync::class)->forget();

        return $this->redirect(safe_return_to((string) ($this->request->post['return_to'] ?? '')) ?? '/');
    }
}
