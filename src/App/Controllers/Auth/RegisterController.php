<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Auth;
use App\Controllers\AppController;
use App\Mail\WelcomeEmail;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Models\UserProfileModel;
use App\Services\UsernameValidationService;
use Exception;
use Framework\Core\Response;

/**
 * Handle user registration.
 *
 * GET  /register → index()
 * POST /register → submit()
 */
final class RegisterController extends AppController
{
    /**
     * Inject user model and auth service.
     *
     * The container resolves these dependencies automatically,
     * and AppController::__construct() still runs to handle flashes.
     */
    public function __construct(
        protected Auth $auth,
        protected UserModel $users,
        private UserProfileModel $profiles,
        private UserPreferencesModel $user_preferences,
        private UsernameValidationService $usernameValidator,
    ) {}

    /**
     * Show the registration form.
     */
    public function show(): Response
    {
        // Redirect authenticated users away from registration
        if (auth()->check()) {
            return $this->redirect('/');
        }

        return $this->view('register.index');
    }

    public function submit(): Response
    {
        // Enforce CSRF token for registration POST.
        csrf()->assertValid($this->request->postParam('_token'));
        // Define validation rules using the fluent validator
        $validator = $this->validateOrFail([
            'first_name' => 'required|alpha|min:2|max:50',
            'last_name' => 'required|alpha|min:2|max:50',
            'username' => 'required|alphaNum|min:3|max:20|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|password:basic',
            'confirm_password' => 'required|same:password',
        ], [
            'username.required' => 'Please provide a username.',
            'username.alpha_num' => 'Username may only contain letters and numbers.',
            'username.unique' => 'This username is already taken.',
            'email.unique' => 'This email address is already registered.',
            'confirm_password.same' => 'Password confirmation does not match.',
        ]);

        // get only validated fields
        $validated = $validator->validated();
        unset($validated['confirm_password']);

        // Check username availability with fuzzy matching
        if (!$this->usernameValidator->isAvailable($validated['username'])) {
            $this->flash('error', 'Please correct the errors and try again.');
            $this->session->set('_errors', ['username' => ['This username is already taken or reserved.']]);

            return $this->redirectBack();
        }

        // hash password before storage
        $validated['password'] = password_hash($validated['password'], PASSWORD_DEFAULT);

        // Attempt to insert the user
        $userId = $this->users->insert($validated);

        if (!$userId) {
            $this->flash('error', 'Registration failed. Please try again.');

            return $this->redirect('/register');
        }

        // assign default role (author = 3)
        // TODO: Move role constant to config
        $this->users->insertUserRoles($userId, [3]);

        // generate and assign a unique slug for the public profile URL
        $slug = $this->generateUniqueSlug($validated['username'], $userId);
        $this->profiles->upsert($userId, [
            'slug' => $slug,
            'is_public' => 1, // default new profiles to public
        ]);

        $this->user_preferences->findOrCreate($userId);

        // send welcome email asynchronously (non-blocking)
        try {
            mailer()->send(new WelcomeEmail($validated));
        } catch (Exception $e) {
            // log email failures but don't block registration
            error_log('Failed to send welcome email: '.$e->getMessage());
        }

        // log the user in automatically after successful registration
        $this->auth->login($validated['email'], $this->request->post['password']);

        // redirect to dashboard
        $this->flash('success', 'Welcome! Create your first blog to get started.');

        return $this->redirect('/dashboard');
    }

    /**
     * Generate a unique slug for a new user profile.
     *
     * prefer using the username as the slug for consistency and simplicity.
     * If the username is reserved or already used as a slug by another profile,
     * fall back to generating a unique identifier.
     *
     * @param  string  $username  The user's chosen username
     * @param  int  $userId  The newly created user ID
     * @return string A guaranteed unique slug
     */
    private function generateUniqueSlug(string $username, int $userId): string
    {
        // first try to use the username directly as the slug
        if ($this->profiles->isSlugAvailable($username)) {
            return $username;
        }

        // If username is taken/reserved, generate a unique fallback
        // Using a short random suffix keeps URLs reasonably clean
        $maxAttempts = 10;
        for ($i = 0; $i < $maxAttempts; $i++) {
            // create slugs like: username-a3f2, username-b8d1, etc.
            $candidate = $username.'-'.bin2hex(random_bytes(2));

            if ($this->profiles->isSlugAvailable($candidate)) {
                return $candidate;
            }
        }

        // If all random attempts fail (extremely unlikely), fall back to user ID
        // This guarantees uniqueness but is less user-friendly
        return 'user-'.$userId;
    }
}
