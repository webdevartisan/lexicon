<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PendingEmailChangeModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\LocaleRegistry;
use App\Services\SessionLocaleSync;
use DateTimeZone;
use Exception;
use Framework\Core\Response;

/**
 * How the interface behaves for one person: language and timezone.
 *
 * The sign-in address is shown here because this is where people look for it,
 * but it is not editable in this form. Changing it is a credential operation
 * with its own password gate and inbox confirmation, owned by
 * AccountEmailController. Public identity lives in AccountProfileController
 * and passwords in AccountSecurityController.
 */
final class AccountPreferencesController extends AppController
{
    public function __construct(
        private UserModel $users,
        private UserPreferencesModel $prefs,
        private PendingEmailChangeModel $pendingEmail,
        private LocaleRegistry $locales,
        private SessionLocaleSync $localeSync
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
            'pendingEmail' => $this->pendingEmail->findForUser($userId) ?: null,
        ]);
    }

    /**
     * Persist interface language and timezone.
     */
    public function update(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $userId = (int) auth()->user()['id'];

        $validator = $this->validateOrFail([
            'timezone' => 'timezone',
            // Built from the registry so adding a locale never means editing this file.
            'locale' => 'in:auto,'.implode(',', $this->locales->supported()),
        ], [
            'timezone.timezone' => chrome_translate('account.preferences.timezoneInvalid'),
            'locale.in' => chrome_translate('account.preferences.languageInvalid'),
        ]);

        $validated = $validator->validated();

        $this->prefs->upsert($userId, [
            'timezone' => $validated['timezone'] ?? null,
            // "auto" is the absence of a preference, stored as NULL so the chrome
            // locale keeps following whatever language the page itself is in.
            'locale' => ($validated['locale'] ?? 'auto') === 'auto' ? null : $validated['locale'],
        ]);

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
     * Load the sign-in address and interface preferences.
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
