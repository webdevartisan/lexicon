<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\AppController;
use Framework\Core\Response;

/**
 * Handle login and logout.
 *
 * GET  /login        → index()
 * POST /login        → submit()
 * GET  /login/show   → show()   (if you need a separate view)
 * POST /logout       → logout()
 */
final class AuthController extends AppController
{
    /**
     * Show the main login form.
     */
    public function index(): Response
    {
        if (auth()->check()) {
            return $this->redirect('/');
        }

        return $this->view('auth.login.index');
    }

    /**
     * Optional: show an alternative login view (e.g. modal or embedded).
     */
    public function show(): Response
    {
        return $this->view('Login/show.lex.php');
    }

    /**
     * Handle login form submission.
     * 
     * Validates basic input, delegates authentication to the Auth service,
     * and returns the appropriate response.
     */
    public function submit(): Response
    {
        // Enforce CSRF token for login POST
        csrf()->assertValid($this->request->post['_token'] ?? null);
        
        // Safely read and normalize input
        $email = trim((string) ($this->request->post['email'] ?? ''));
        $password = (string) ($this->request->post['password'] ?? '');
        $ip = $this->request->ip();
        
        // Basic validation before attempting login
        $errors = [];
        
        if ($email === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        
        if ($password === '') {
            $errors[] = 'Password is required.';
        }
        
        if ($errors !== []) {
            // Re-render the form with validation errors and keep the email field filled
            return $this->view('auth.login.index', [
                'error' => implode(' ', $errors),
                'email' => $email,
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
                $wait = ceil($wait / 60) . ' minutes';
            } else {
                $wait = $wait . ' seconds';
            }

            $this->flash('error', "Too many login attempts. Try again in {$wait}.");
            return $this->redirect('/login');
        }

        // ---------------------------------------------------------

        // Delegate authentication to the Auth service
        if (auth()->login($email, $password)) {

            $limiter->clear($ip, $email);

            // Retrieve intended URL with fallback to dashboard
            $intendedUrl = $this->session->get('intended_url', '/dashboard');
            
            // Clear the stored URL
            $this->session->remove('intended_url');
            
            return $this->redirect($intendedUrl);
        }

        // ---------------------------------------------------------
        // FAILED LOGIN → RECORD ATTEMPT
        // ---------------------------------------------------------
        $limiter->hit($ip, $email);
        // ---------------------------------------------------------

        // Authentication failed - flash error and redirect back
        $this->flash('error', 'Invalid credentials');
        
        // Return Response
        return $this->redirectBack();
    }

    /**
     * Log the user out and redirect to the homepage.
     */
    public function logout(): Response
    {
        auth()->logout();

        // Optional: flash a message for the next request.
        // $this->flash('success', 'You have been logged out.');

        return $this->redirect('/');
    }
}
