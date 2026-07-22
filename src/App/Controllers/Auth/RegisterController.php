<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Auth;
use App\Controllers\AppController;
use App\Mail\WelcomeEmail;
use App\Models\BlogInvitationModel;
use App\Models\RoleModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Models\UserProfileModel;
use App\Services\CommentService;
use App\Services\InvitationService;
use App\Services\UsernameValidationService;
use Exception;
use Framework\Core\Response;

/**
 * Handle user registration.
 *
 * Two fields only (email + password): the username is generated from the
 * email local part and everything else is progressive profiling later.
 * New accounts start as readers; the creator upgrade happens when they
 * create their first blog.
 *
 * GET  /register        → show()
 * POST /register/submit → submit()
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
        private BlogInvitationModel $invitationModel,
        private InvitationService $invitationService,
        private RoleModel $roles,
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

        return $this->view('register.index', [
            'return_to' => safe_return_to((string) ($this->request->get['return_to'] ?? '')),
        ]);
    }

    public function submit(): Response
    {
        // Enforce CSRF token for registration POST.
        csrf()->assertValid($this->request->postParam('_token'));

        $isAjax = $this->request->isAjax();

        $rules = [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|password:basic',
        ];
        $messages = [
            'email.unique' => 'This email address is already registered. Try logging in instead.',
        ];

        // The modal needs errors as JSON; the full page keeps the
        // flash-and-redirect flow that validateOrFail provides.
        if ($isAjax) {
            $validator = $this->validate($rules, $messages);
            if ($validator->fails()) {
                return $this->jsonError($this->validationErrorLine($validator->errors()), 422);
            }
        } else {
            $validator = $this->validateOrFail($rules, $messages);
        }

        $validated = $validator->validated();
        $returnTo = safe_return_to((string) ($this->request->post['return_to'] ?? ''));

        $username = $this->generateUsername($validated['email']);

        $userId = $this->users->insert([
            'email' => $validated['email'],
            'username' => $username,
            'password' => password_hash($validated['password'], PASSWORD_DEFAULT),
        ]);

        if (!$userId) {
            if ($isAjax) {
                return $this->jsonError('Registration failed. Please try again.', 500);
            }

            $this->flash('error', 'Registration failed. Please try again.');

            return $this->redirectBack();
        }

        // New accounts are readers; creating a blog later upgrades them
        $readerRole = $this->roles->findBySlug('reader');
        if ($readerRole !== null) {
            $this->users->insertUserRoles($userId, [(int) $readerRole['id']]);
        }

        // generate and assign a unique slug for the public profile URL
        $slug = $this->generateUniqueSlug($username, $userId);
        $this->profiles->upsert($userId, [
            'slug' => $slug,
            'is_public' => 1, // default new profiles to public
        ]);

        $this->user_preferences->findOrCreate($userId);

        // queued, so a transport problem does not cost the welcome email
        try {
            mail_queue()->enqueue(new WelcomeEmail([
                'email' => $validated['email'],
                'username' => $username,
            ]), 'user', $userId);
        } catch (Exception $e) {
            // queueing is a single insert, so this only fires if the database
            // is in trouble. Registration itself already succeeded either way.
            error_log('Failed to queue welcome email: '.$e->getMessage());
        }

        // log the user in automatically after successful registration
        $this->auth->login($validated['email'], $this->request->post['password']);

        // A reply captured before signup gets posted now and wins the
        // redirect: the new reader lands back on the comment they answered.
        $resume = app(CommentService::class)->resumePending(auth()->user(), $this->request->ip());
        if ($resume !== null) {
            if ($isAjax) {
                return $this->jsonSuccess(['redirect' => $resume['path']]);
            }

            $this->flash($resume['ok'] ? 'success' : 'error', 'Welcome to Lexicon! '.$resume['message']);

            return $this->redirect($resume['path']);
        }

        // If the user got here from a blog invitation link, auto-accept it so
        // they land directly on the blog they were invited to.
        $pendingToken = (string) $this->session->get('pending_invite_token', '');
        if ($pendingToken !== '') {
            $invite = $this->invitationModel->findValidByToken(hash('sha256', $pendingToken));
            if ($invite !== false && strcasecmp((string) $invite['email'], $validated['email']) === 0) {
                try {
                    $this->invitationService->accept($pendingToken, $userId);
                    $this->session->remove('pending_invite_token');

                    if ($isAjax) {
                        return $this->jsonSuccess(['redirect' => lurl("/dashboard/blog/{$invite['blog_id']}/show")]);
                    }

                    $this->flash('success', "Welcome! You've joined the blog as {$invite['role']}.");

                    return $this->redirect(lurl("/dashboard/blog/{$invite['blog_id']}/show"));
                } catch (\RuntimeException) {
                    // Fall through to the default welcome flow if the token expired.
                }
            }
            // Stale or mismatched token — discard so it can't surface later.
            $this->session->remove('pending_invite_token');
        }

        // Readers who signed up mid-read go straight back to where they were;
        // everyone else lands on their new reading hub.
        if ($isAjax) {
            // No flash: the modal shows its own success state and usually
            // finishes the reader's action in place instead of navigating
            return $this->jsonSuccess(['redirect' => $returnTo ?? '/dashboard']);
        }

        if ($returnTo !== null) {
            $this->flash('success', 'Welcome to Lexicon! Your reader account is ready.');

            return $this->redirect($returnTo);
        }

        $this->flash('success', 'Welcome! This is your reading hub — save posts, follow blogs, join discussions.');

        return $this->redirect('/dashboard');
    }

    /**
     * Build a username from the email local part.
     *
     * Sanitized to the same alphanumeric 3-20 charset the profile editor
     * enforces. Collisions and reserved-word matches get a short random
     * suffix; a fully random handle is the terminal fallback because the
     * fuzzy reserved-word filter can reject every variant of a local part.
     *
     * @param  string  $email  The registration email
     * @return string An available username
     */
    private function generateUsername(string $email): string
    {
        $base = strtolower((string) strstr($email, '@', true));
        $base = (string) preg_replace('/[^a-z0-9]/', '', $base);
        $base = substr($base, 0, 20);

        if (strlen($base) >= 3 && $this->usernameValidator->isAvailable($base)) {
            return $base;
        }

        $stem = strlen($base) >= 3 ? substr($base, 0, 15) : 'reader';
        for ($i = 0; $i < 10; $i++) {
            $candidate = $stem.bin2hex(random_bytes(2));
            if ($this->usernameValidator->isAvailable($candidate)) {
                return $candidate;
            }
        }

        do {
            $candidate = 'u'.bin2hex(random_bytes(4));
        } while (!$this->usernameValidator->isAvailable($candidate));

        return $candidate;
    }

    /**
     * Generate a unique slug for a new user profile.
     *
     * prefer using the username as the slug for consistency and simplicity.
     * If the username is reserved or already used as a slug by another profile,
     * fall back to generating a unique identifier.
     *
     * @param  string  $username  The user's generated username
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
