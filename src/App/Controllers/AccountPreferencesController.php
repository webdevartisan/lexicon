<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Mail\EmailChangedMail;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\DisplayNameService;
use App\Services\LocaleRegistry;
use App\Services\MailQueueService;
use App\Services\PasswordConfirmRateLimiter;
use App\Services\SessionLocaleSync;
use DateTimeZone;
use Exception;
use Framework\Core\Response;

/**
 * Private preferences on the front: email, interface language, timezone and the
 * default visibility applied to new posts. Public identity lives in
 * AccountProfileController and credentials in AccountSecurityController.
 *
 * Email is a credential, not a preference sitting next to timezone. Changing it
 * requires the current password, verified only when the submitted address
 * differs from the stored one, and that check is rate limited so a stolen
 * session is not an unlimited password oracle.
 */
final class AccountPreferencesController extends AppController
{
    public function __construct(
        private UserModel $users,
        private UserPreferencesModel $prefs,
        private DisplayNameService $displayNames,
        private LocaleRegistry $locales,
        private SessionLocaleSync $localeSync,
        private PasswordConfirmRateLimiter $passwordThrottle,
        private MailQueueService $mailQueue
    ) {}

    /**
     * Display the preferences form.
     */
    public function edit(): Response
    {
        $userId = (int) auth()->user()['id'];

        return $this->view('public.Account.preferences', [
            'user' => $this->loadAccount($userId),
            'timezones' => $this->getGroupedTimezones(),
            'locales' => $this->localeOptions(),
        ]);
    }

    /**
     * Persist email and posting preferences.
     */
    public function update(): Response
    {
        // enforce CSRF protection
        csrf()->assertValid($this->request->postParam('_token'));

        $userId = (int) auth()->user()['id'];
        $currentEmail = (string) (auth()->user()['email'] ?? '');
        $submittedEmail = strtolower(trim((string) $this->request->postParam('email')));
        $emailChanging = $submittedEmail !== '' && $submittedEmail !== strtolower($currentEmail);

        // Email is a credential: gate the change behind the current password, and
        // throttle that check. A refused attempt fails on the same field with the
        // same wording as any other validation failure, so the field never
        // becomes a password oracle with a nicer error. The hash is verified
        // explicitly rather than through the validation rule, because
        // validateOrFail redirects on failure before the throttle could observe
        // the miss.
        if ($emailChanging) {
            if ($this->passwordThrottle->tooManyAttempts($userId)) {
                return $this->rejectEmailChange($userId);
            }

            $password = (string) $this->request->postParam('current_password');
            if ($password === '' || !$this->users->verifyPassword($userId, $password)) {
                $this->passwordThrottle->hit($userId);

                return $this->rejectEmailChange($userId);
            }

            $this->passwordThrottle->clear($userId);
        }

        $validator = $this->validateOrFail([
            'email' => 'required|email|unique:users,email,'.$userId,
            'display_name' => 'in:name,username',
            'default_visibility' => 'in:public,private,unlisted',
            'timezone' => 'timezone',
            // Built from the registry so adding a locale never means editing this file.
            'locale' => 'in:auto,'.implode(',', $this->locales->supported()),
        ], [
            'email.unique' => chrome_translate('account.flash.emailInUse'),
            'timezone.timezone' => 'Please select a valid timezone.',
            'locale.in' => 'Please choose a language from the list.',
        ]);

        $validated = $validator->validated();

        $userUpdate = changedFields([
            'email' => $validated['email'] ?? '',
        ], auth()->user());

        if (!empty($userUpdate)) {
            $this->users->updateById($userId, $userUpdate);
        }

        $this->prefs->upsert($userId, [
            'display_name_preference' => $validated['display_name'] ?? 'username',
            'default_post_visibility' => $validated['default_visibility'] ?? 'public',
            'timezone' => $validated['timezone'] ?? null,
            // "auto" is the absence of a preference, stored as NULL so the chrome
            // locale keeps following whatever language the page itself is in.
            'locale' => ($validated['locale'] ?? 'auto') === 'auto' ? null : $validated['locale'],
        ]);

        // the display preference decides whether the cached name is the real name or the handle
        $this->displayNames->refreshCached($userId);

        if ($emailChanging) {
            // Queued, not sent inline, so a mail outage cannot fail the save. The
            // old address is told the change happened; the new address is not
            // asked to confirm anything, which is the token flow left to follow-up.
            $this->mailQueue->enqueue(
                new EmailChangedMail($currentEmail, $submittedEmail, gmdate('c')),
                'account',
                $userId
            );
        }

        // Picking a language here has to move the URL too, or the redirect below
        // lands on the locale the visitor was already on and the choice looks
        // like it did nothing. apply() writes the session locale, but lurl() in
        // this same request still resolves the locale captured at bootstrap, so
        // the redirect has to use the language apply() reports rather than the
        // stale one. A null answer means "auto", where the current URL's locale
        // is exactly what should carry over.
        $adopted = $this->localeSync->apply($userId);

        $this->flash('success', chrome_translate('account.flash.preferencesSaved'));

        return $this->redirect(lurl('/account/preferences', $adopted));
    }

    /**
     * Refuse an email change without disclosing whether the throttle or the
     * password was the reason. Nothing is written, including the other fields in
     * the same submission.
     */
    private function rejectEmailChange(int $userId): Response
    {
        $this->session->set('_errors', [
            'current_password' => [chrome_translate('account.flash.preferencesUnchanged')],
        ]);
        $this->session->set('_old_input', $this->request->all());
        $this->flash('error', chrome_translate('account.flash.preferencesUnchanged'));

        return $this->redirect(lurl('/account/preferences'));
    }

    /**
     * Load the account identity fields and posting preferences.
     *
     * @return array<string, mixed>
     */
    private function loadAccount(int $userId): array
    {
        $user = $this->users->findById($userId);

        if (!$user) {
            throw new Exception("User record not found for ID {$userId}");
        }

        $preferences = $this->prefs->findOrCreate($userId) ?: [];
        $merged = array_merge($user, $preferences);

        $merged['display_name'] = $preferences['display_name_preference'] ?? 'username';
        $merged['default_visibility'] = $preferences['default_post_visibility'] ?? 'public';
        $merged['timezone'] = $preferences['timezone'] ?? 'UTC';
        $merged['locale'] = $preferences['locale'] ?? 'auto';

        return $merged;
    }

    /**
     * Get timezones grouped by region for the select dropdown.
     *
     * @return array<string, string[]>
     */
    private function getGroupedTimezones(): array
    {
        $zones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
        $grouped = [];

        foreach ($zones as $zone) {
            $parts = explode('/', $zone, 2);

            // deprecated zones such as "UTC" have no region prefix
            if (count($parts) === 1) {
                $grouped['Other'][] = $zone;
                continue;
            }

            $grouped[$parts[0]][] = $zone;
        }

        return $grouped;
    }

    /**
     * Language options for the form, each named in its own language.
     *
     * @return array<string, string> Locale code => label, led by the "auto" entry
     */
    private function localeOptions(): array
    {
        $options = ['auto' => chrome_translate('account.preferences.languageAuto')];

        foreach ($this->locales->supported() as $code) {
            $options[$code] = $this->locales->nativeName($code);
        }

        return $options;
    }
}
