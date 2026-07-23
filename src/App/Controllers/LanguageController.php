<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserPreferencesModel;
use App\Services\LocaleRegistry;
use Framework\Core\Response;

/**
 * The language switcher's signed-in half.
 *
 * A guest switches language with a plain link: the URL carries the locale and
 * nothing needs saving. A signed-in reader's interface follows their stored
 * preference instead, so for them the same click has to write that preference
 * or the switcher would change the URL and visibly do nothing.
 *
 * That is also why guests keep links rather than sharing this form: a POST form
 * in the markup makes CacheMiddleware bypass the full-page cache, and putting
 * one in the footer would uncache every public page on the site.
 */
final class LanguageController extends AppController
{
    public function __construct(
        private readonly UserPreferencesModel $preferences,
        private readonly LocaleRegistry $locales,
    ) {}

    /**
     * Save the signed-in reader's interface language, then continue to the
     * locale-prefixed URL they picked.
     */
    public function switch(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        // Only ever a site-local path. Without this the switcher would forward
        // to any host an attacker managed to put in the form.
        $target = safe_return_to((string) $this->request->postParam('return_to', '')) ?? '/';

        $locale = $this->locales->normalize((string) $this->request->postParam('locale', ''));

        // An unsupported code means a tampered or stale form. Send them on to a
        // safe local page rather than storing junk.
        if ($locale === null) {
            return $this->redirect($target);
        }

        if (auth()->check()) {
            $this->preferences->upsert((int) auth()->user()['id'], ['locale' => $locale]);
        }

        return $this->redirect($target);
    }
}
