<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Resources\BlogResource;
use App\Services\BlogLocales;
use App\Services\FrontPageTexts;
use App\Services\LocaleRegistry;
use App\Services\ThemeWording;
use Framework\Core\Response;

/**
 * Saves the texts an owner edited on the blog's own pages: the front-page
 * texts from the blog settings and the theme's own wording.
 *
 * Logo, banner, social links and theme stay as they are, which is why this
 * does not share the Appearance form's update.
 */
final class BlogTextController extends AppController
{
    public function __construct(
        private BlogModel $blogs,
        private BlogSettingsModel $settings,
        private BlogLocales $locales,
        private FrontPageTexts $frontTexts,
        private ThemeWording $wording
    ) {}

    /**
     * Save what the page sent, each part in the language the page showed it in.
     *
     * @param  string  $blogId  Blog ID
     */
    public function save(string $blogId): Response
    {
        if (!csrf()->isTokenValid($this->request->postParam('_token'))) {
            return $this->json(['error' => 'Your session expired. Reload the page and try again.'], 419);
        }

        $user = auth()->user();
        $blog = $this->blogs->getBlog((int) $blogId);
        if ($blog === false) {
            return $this->json(['error' => 'This blog no longer exists.'], 404);
        }
        if (!Gate::allows('update', $blog, $user)) {
            return $this->json(['error' => 'You cannot edit this blog.'], 403);
        }

        $settings = $this->settings->findOrCreate($blog->id());
        $texts = $this->sentTexts();
        $wording = $this->sentWording();

        $problem = $this->invalidTexts($blog, $settings, $texts) ?? $this->invalidWording($settings, $wording);
        if ($problem !== null) {
            return $this->json(['error' => $problem], 422);
        }

        $changes = [];
        if ($texts !== []) {
            $locale = (string) $this->request->postParam('locale');
            $changes += $this->withLocale($this->frontTexts->save($blog, $settings, $locale, $texts), $locale);
        }
        if ($wording !== []) {
            $locale = (string) $this->request->postParam('wording_locale');
            $changes += $this->withLocale($this->wording->save($blog, $this->themeOf($settings), $locale, $wording), $locale);
        }

        if ($changes !== []) {
            audit()->log($user['id'], 'blog.updated', 'blog', $blog->id(), $changes, $this->request->ip());
        }

        return $this->json(['saved' => array_keys($changes)]);
    }

    /**
     * The front-page texts the page sent; a text not sent is left as it is.
     *
     * @return array<string, string>
     */
    private function sentTexts(): array
    {
        $values = [];
        foreach (array_keys(FrontPageTexts::RULES) as $field) {
            $value = $this->request->postParam($field);
            if ($value !== null) {
                $values[$field] = trim((string) $value);
            }
        }

        return $values;
    }

    /**
     * The theme wording the page sent, keyed by translation key.
     *
     * @return array<string, string>
     */
    private function sentWording(): array
    {
        $sent = $this->request->postParam('wording');
        if (!is_array($sent)) {
            return [];
        }

        $values = [];
        foreach ($sent as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $values[$key] = trim((string) $value);
            }
        }

        return $values;
    }

    /**
     * Why the front-page texts cannot be accepted, or null when they can.
     *
     * @param  array<string, mixed>  $settings  Blog settings row
     * @param  array<string, string>  $values
     */
    private function invalidTexts(BlogResource $blog, array $settings, array $values): ?string
    {
        if ($values === []) {
            return null;
        }

        $locale = (string) $this->request->postParam('locale', '');
        if (!in_array($locale, $this->locales->for($blog->id(), $settings), true)) {
            return 'This blog has no page in that language.';
        }

        $unreadable = $this->unreadable($values);
        if ($unreadable !== null) {
            return $unreadable;
        }

        $validator = $this->validator($values);
        $validator->rules(array_intersect_key(FrontPageTexts::RULES, $values));
        if (!$validator->fails()) {
            return null;
        }

        $field = (string) array_key_first($validator->errors());
        $limit = (int) substr(FrontPageTexts::RULES[$field], strlen('max:'));

        return 'Keep the '.FrontPageTexts::LABELS[$field].' to '.$limit.' characters.';
    }

    /**
     * Why the theme wording cannot be accepted, or null when it can.
     *
     * @param  array<string, mixed>  $settings  Blog settings row
     * @param  array<string, string>  $values
     */
    private function invalidWording(array $settings, array $values): ?string
    {
        if ($values === []) {
            return null;
        }

        if (!LocaleRegistry::instance()->isSupported((string) $this->request->postParam('wording_locale', ''))) {
            return 'That language is not available.';
        }

        foreach (array_keys($values) as $key) {
            if (!$this->wording->isEditable($this->themeOf($settings), $key)) {
                return 'This theme does not let that text be changed. Reload the page and try again.';
            }
        }

        $unreadable = $this->unreadable($values);
        if ($unreadable !== null) {
            return $unreadable;
        }

        foreach ($values as $value) {
            if (mb_strlen($value) > ThemeWording::MAX_LENGTH) {
                return 'Keep each text to '.ThemeWording::MAX_LENGTH.' characters.';
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function unreadable(array $values): ?string
    {
        foreach ($values as $value) {
            if (!mb_check_encoding($value, 'UTF-8')) {
                return 'Some of the text could not be read. Type it again and save.';
            }
        }

        return null;
    }

    /**
     * Changes as the audit log records them, each key with its language.
     *
     * @param  array<string, string|null>  $changes
     * @return array<string, string|null>
     */
    private function withLocale(array $changes, string $locale): array
    {
        $logged = [];
        foreach ($changes as $key => $value) {
            $logged[$key.'@'.$locale] = $value;
        }

        return $logged;
    }

    /**
     * @param  array<string, mixed>  $settings  Blog settings row
     */
    private function themeOf(array $settings): ?string
    {
        $theme = (string) ($settings['theme'] ?? '');

        return $theme === '' ? null : $theme;
    }
}
