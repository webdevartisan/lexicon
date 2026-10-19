<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\AppController;
use App\Models\BlogInvitationModel;
use App\Services\AccountRegistrar;
use App\Services\BlogRoleLabels;
use App\Services\Captcha\CaptchaInterface;
use App\Services\Captcha\CaptchaResult;
use App\Services\CommentService;
use App\Services\InvitationService;
use Framework\Core\Response;
use Framework\Helpers\RateLimiter;

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
    // Accounts per IP decay with a one hour half-life, so a shared network recovers on its own.
    private const REGISTRATION_DECAY_SECONDS = 3600;

    public function __construct(
        private AccountRegistrar $registrar,
        private BlogInvitationModel $invitationModel,
        private InvitationService $invitationService,
        private CaptchaInterface $captcha,
        private RateLimiter $rateLimiter,
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

        if (!registration_open()) {
            return $this->refuseClosed(false);
        }

        return $this->view('register.index', [
            'return_to' => safe_return_to((string) ($this->request->get['return_to'] ?? '')),
            'captcha' => $this->captcha->issue('register'),
        ]);
    }

    /**
     * Turn a sign-up attempt away while registration is switched off in Settings.
     */
    private function refuseClosed(bool $isAjax): Response
    {
        $message = chrome_translate('auth.registrationClosed');

        if ($isAjax) {
            return $this->jsonError($message, 403);
        }

        $this->flash('error', $message);

        return $this->redirect('/login');
    }

    public function submit(): Response
    {
        // Enforce CSRF token for registration POST.
        csrf()->assertValid($this->request->postParam('_token'));

        $isAjax = $this->request->isAjax();

        if (!registration_open()) {
            return $this->refuseClosed($isAjax);
        }

        $refusal = $this->refuseUnchecked($isAjax);
        if ($refusal !== null) {
            return $refusal;
        }

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

        // An invitation link was mailed to this address, so arriving through one already proved the inbox
        $pendingToken = (string) $this->session->get('pending_invite_token', '');
        $invite = $pendingToken !== '' ? $this->invitationModel->findValidByToken(hash('sha256', $pendingToken)) : false;
        $inviteMatches = $invite !== false && strcasecmp((string) $invite['email'], $validated['email']) === 0;

        $userId = $this->registrar->register($validated['email'], $validated['password'], $this->request, $inviteMatches || !email_verification_required());

        if ($userId === null) {
            if ($isAjax) {
                return $this->jsonError('Registration failed. Please try again.', 500);
            }

            $this->flash('error', 'Registration failed. Please try again.');

            return $this->redirectBack();
        }

        $this->rateLimiter->hit($this->registrationKey(), self::REGISTRATION_DECAY_SECONDS);

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
        if ($pendingToken !== '') {
            if ($inviteMatches) {
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

        // Follow what they clicked: "Create a Blog" carries on into Start your
        // blog, a reader goes back to the post, and anyone else gets asked.
        if ($isAjax) {
            // No flash: the modal shows its own success state and usually
            // finishes the reader's action in place instead of navigating
            return $this->jsonSuccess(['redirect' => $returnTo ?? lurl('/welcome')]);
        }

        if ($returnTo !== null && preg_match('#^(/[a-z]{2})?/dashboard/blog/new$#', $returnTo) === 1) {
            $this->flash('success', chrome_translate('setup.signup.welcomeWriter'));

            return $this->redirect($returnTo);
        }

        if ($returnTo !== null) {
            $this->flash('success', 'Welcome to Lexicon! Your reader account is ready.');

            return $this->redirect($returnTo);
        }

        return $this->redirect(lurl('/welcome'));
    }

    /**
     * Refuse a sign-up from a busy IP or without a valid check answer.
     *
     * Runs before validation, so a script cannot probe which addresses are
     * taken or send welcome emails to strangers without paying for each try.
     *
     * @return Response|null The refusal, or null to carry on
     */
    private function refuseUnchecked(bool $isAjax): ?Response
    {
        $perIp = (int) (require ROOT_PATH.'/config/captcha.php')['registrations_per_ip'];
        if ($this->rateLimiter->tooManyAttempts($this->registrationKey(), $perIp, self::REGISTRATION_DECAY_SECONDS)) {
            return $this->refuse($isAjax, chrome_translate('auth.tooManyRegistrations'), 429, false);
        }

        $result = $this->captcha->verify((string) ($this->request->post['altcha'] ?? ''), 'register');
        if ($result->passed()) {
            return null;
        }

        if ($result !== CaptchaResult::Missing) {
            audit()->log(null, 'auth.captcha_failed', 'user', 0, ['reason' => $result->value, 'form' => 'register'], $this->request->ip());
        }

        return $this->refuse($isAjax, chrome_translate('auth.checkFailed'), 422, true);
    }

    /**
     * Send the refusal in the shape the caller expects, keeping what was typed on the page.
     */
    private function refuse(bool $isAjax, string $message, int $status, bool $withChallenge): Response
    {
        if ($isAjax) {
            $body = ['success' => false, 'error' => $message];
            if ($withChallenge) {
                $body['captcha'] = $this->captcha->issue('register');
            }

            return $this->json($body, $status);
        }

        $this->session->set('_old_input', sanitize_old_input($this->request->post));
        $this->flash('error', $message);

        return $this->redirectBack();
    }

    private function registrationKey(): string
    {
        return 'register:ip:'.($this->request->ip() ?? 'unknown');
    }
}
