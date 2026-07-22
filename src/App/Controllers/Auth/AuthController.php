<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\AppController;
use Framework\Core\Response;

/**
 * Handle login and logout.
 *
 * GET  /login          → index()
 * POST /login/submit   → submit()   (HTML form or AJAX from the blog-front modal)
 * POST /login/identify → identify() (modal step 1: does this email have an account?)
 * GET  /logout         → logout()
 */
final class AuthController extends AppController
{
    /**
     * Show the main login form.
     *
     * Blog-front links pass ?return_to= so readers land back on the page
     * they were reading instead of the dashboard.
     */
    public function index(): Response
    {
        if (auth()->check()) {
            return $this->redirect('/');
        }

        return $this->view('auth.login.index', [
            'return_to' => safe_return_to((string) ($this->request->get['return_to'] ?? '')),
        ]);
    }

    /**
     * Optional: show an alternative login view (e.g. modal or embedded).
     */
    public function show(): Response
    {
        return $this->view('Login/show.lex.php');
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
        $limiter = app(\App\Services\LoginRateLimiter::class);

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

        // ---------------------------------------------------------

        // Delegate authentication to the Auth service
        if (auth()->login($email, $password)) {

            $limiter->clear($ip, $email);

            // A reply captured before login gets posted now and wins the
            // redirect: the reader lands back on the comment they answered.
            $resume = app(\App\Services\CommentService::class)->resumePending(auth()->user(), $ip);
            if ($resume !== null) {
                if ($isAjax) {
                    return $this->jsonSuccess(['redirect' => $resume['path']]);
                }

                $this->flash($resume['ok'] ? 'success' : 'error', $resume['message']);

                return $this->redirect($resume['path']);
            }

            // Redirect priority: blog-front return_to beats the stored
            // intended URL, and only when neither exists do we fall back to
            // the dashboard (readers land on their reading hub there).
            $intendedUrl = $this->session->get('intended_url');
            $this->session->remove('intended_url');

            $target = $returnTo ?? ($intendedUrl ?: '/dashboard');

            if ($isAjax) {
                // No flash here: the modal shows its own success state and
                // usually finishes the action in place instead of navigating
                return $this->jsonSuccess(['redirect' => $target]);
            }

            return $this->redirect($target);
        }

        // ---------------------------------------------------------
        // FAILED LOGIN → RECORD ATTEMPT
        // ---------------------------------------------------------
        $limiter->hit($ip, $email);
        // ---------------------------------------------------------

        if ($isAjax) {
            // The modal's email step already confirmed the account exists,
            // so a specific message doesn't disclose anything new
            return $this->jsonError('Incorrect password. Try again or reset it.', 401);
        }

        // Authentication failed - flash error and redirect back
        $this->flash('error', 'Invalid credentials');

        // Return Response
        return $this->redirectBack();
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

        auth()->logout();

        return $this->redirect(safe_return_to((string) ($this->request->post['return_to'] ?? '')) ?? '/');
    }
}
