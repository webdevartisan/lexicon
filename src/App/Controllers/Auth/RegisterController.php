<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\AppController;
use App\Models\BlogInvitationModel;
use App\Services\AccountRegistrar;
use App\Services\BlogRoleLabels;
use App\Services\CommentService;
use App\Services\InvitationService;
use Framework\Core\Response;

/**
 * Handle user registration.
 *
 * Two fields only (email + password): the handle is generated from the
 * email local part and everything else is progressive profiling later.
 * New accounts start as readers; the creator upgrade happens when they
 * create their first blog.
 *
 * GET  /register        → show()
 * POST /register/submit → submit()
 */
final class RegisterController extends AppController
{
    public function __construct(
        private AccountRegistrar $registrar,
        private BlogInvitationModel $invitationModel,
        private InvitationService $invitationService,
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
            'password' => 'required|password:'.password_policy_preset(),
            'age_confirmed' => 'required|in:1',
        ];
        $ageRequired = chrome_translate('auth.ageRequired', ['age' => minimum_signup_age()]);
        $messages = [
            'email.unique' => 'This email address is already registered. Try logging in instead.',
            'age_confirmed.required' => $ageRequired,
            'age_confirmed.in' => $ageRequired,
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

        $userId = $this->registrar->register($validated['email'], $validated['password'], $this->request);

        if ($userId === null) {
            if ($isAjax) {
                return $this->jsonError('Registration failed. Please try again.', 500);
            }

            $this->flash('error', 'Registration failed. Please try again.');

            return $this->redirectBack();
        }

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

        // If the user got here from a blog invitation link, auto-accept it. A new
        // account has no active blog, so the invited one becomes it and the Overview opens on it.
        $pendingToken = (string) $this->session->get('pending_invite_token', '');
        if ($pendingToken !== '') {
            $invite = $this->invitationModel->findValidByToken(hash('sha256', $pendingToken));
            if ($invite !== false && strcasecmp((string) $invite['email'], $validated['email']) === 0) {
                try {
                    $this->invitationService->accept($pendingToken, $userId, $this->request->ip());
                    $this->session->remove('pending_invite_token');

                    if ($isAjax) {
                        return $this->jsonSuccess(['redirect' => lurl('/dashboard')]);
                    }

                    $this->flash('success', "Welcome! You've joined the blog as ".app(BlogRoleLabels::class)->label($invite['role']).'.');

                    return $this->redirect(lurl('/dashboard'));
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
            return $this->jsonSuccess(['redirect' => $returnTo ?? lurl('/dashboard')]);
        }

        if ($returnTo !== null) {
            $this->flash('success', 'Welcome to Lexicon! Your reader account is ready.');

            return $this->redirect($returnTo);
        }

        $this->flash('success', 'Welcome! This is your reading hub — save posts, follow blogs, join discussions.');

        return $this->redirect('/dashboard');
    }
}
