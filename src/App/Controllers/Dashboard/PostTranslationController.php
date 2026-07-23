<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\PostTranslationModel;
use App\Resources\PostResource;
use App\Services\LocaleRegistry;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Per-locale translation editing for posts on blogs with localized posts on.
 *
 * Each locale is its own page (a tab on the post edit screen). Only the
 * readable fields are translatable; slug, status, taxonomy, and images stay
 * on the base post. Authorization mirrors editing the post itself.
 */
final class PostTranslationController extends AppController
{
    public function __construct(
        private PostModel $postModel,
        private PostTranslationModel $translations,
        private BlogSettingsModel $blogSettings,
        private LocaleRegistry $localeRegistry,
    ) {}

    /**
     * Show the translation form for one locale.
     *
     * @param  string  $id  Post ID
     * @param  string  $locale  Target locale (never the blog's default)
     */
    public function edit(string $id, string $locale): Response
    {
        [$post, $blog, $settings] = $this->getContext($id, $locale);

        $translation = $this->translations->findOne((int) $post->id(), $locale);

        breadcrumbs()->set([
            ['label' => 'Dashboard', 'url' => '/dashboard', 'key' => 'breadcrumbs.dashboard'],
            ['label' => 'Edit Post', 'url' => '/dashboard/post/'.$post->id().'/edit', 'key' => 'breadcrumbs.editPost'],
            ['label' => strtoupper($locale), 'url' => null],
        ], true);

        // Explicit path: the dispatcher lowercases route controller names, so
        // inference would look in "Posttranslation/" — wrong on case-sensitive filesystems.
        return $this->view('areas/dashboard/PostTranslation/edit.lex.php', [
            'post' => $post->toArray(),
            'blog' => $blog,
            'locale' => $locale,
            'translation' => $translation,
            'translations' => $this->translations->findForPost((int) $post->id()),
            'defaultLocale' => (string) ($settings['default_locale'] ?? 'en'),
            'availableLocales' => $this->localeRegistry->supported(),
        ]);
    }

    /**
     * Create or update the translation for one locale.
     *
     * @param  string  $id  Post ID
     * @param  string  $locale  Target locale
     */
    public function update(string $id, string $locale): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        [$post, $blog] = $this->getContext($id, $locale);

        $validator = $this->validateOrFail([
            'title' => 'required|title|min:2|max:100',
            'content' => 'required|max:60000',
            'excerpt' => 'max:300',
        ]);
        $data = $validator->validated();

        $this->translations->upsert((int) $post->id(), $locale, $data);

        $this->purgePublicPages($blog);

        audit()->log(
            (int) auth()->user()['id'],
            'post.translation_saved',
            'post',
            (int) $post->id(),
            ['locale' => $locale],
            $this->request->ip()
        );

        $this->flash('success', 'Translation saved.');

        return $this->redirect('/dashboard/post/'.$post->id().'/translations/'.$locale);
    }

    /**
     * Remove one locale's translation; readers fall back to the original.
     *
     * @param  string  $id  Post ID
     * @param  string  $locale  Target locale
     */
    public function destroy(string $id, string $locale): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        [$post, $blog] = $this->getContext($id, $locale);

        if ($this->translations->deleteOne((int) $post->id(), $locale)) {
            $this->purgePublicPages($blog);

            audit()->log(
                (int) auth()->user()['id'],
                'post.translation_deleted',
                'post',
                (int) $post->id(),
                ['locale' => $locale],
                $this->request->ip()
            );

            $this->flash('success', 'Translation deleted.');
        }

        return $this->redirect('/dashboard/post/'.$post->id().'/edit');
    }

    /**
     * Resolve post + blog, authorize, and reject invalid locales.
     *
     * The blog's default locale is not translatable — that content lives on
     * the base post — and blogs without the feature enabled have no
     * translation surface at all.
     *
     * @return array{0: PostResource, 1: array<string, mixed>, 2: array<string, mixed>}
     *
     * @throws PageNotFoundException
     */
    private function getContext(string $id, string $locale): array
    {
        $post = $this->postModel->findResource((int) $id);
        if ($post === false) {
            throw new PageNotFoundException("Post with ID: '{$id}' not found.");
        }

        Gate::authorize('update', $post, auth()->user());

        $blog = $post->blog();
        $settings = $this->blogSettings->findByBlogId((int) $blog->id()) ?? [];

        $defaultLocale = (string) ($settings['default_locale'] ?? 'en');

        if (empty($settings['translations_enabled'])
            || !$this->localeRegistry->isSupported($locale)
            || $locale === $defaultLocale) {
            throw new PageNotFoundException("No translation surface for locale '{$locale}'.");
        }

        return [$post, $blog->toArray(), $settings];
    }

    /**
     * Purge the blog's cached public pages in every locale.
     *
     * @param  array<string, mixed>  $blog
     */
    private function purgePublicPages(array $blog): void
    {
        $slug = (string) ($blog['blog_slug'] ?? '');
        if ($slug !== '') {
            cache()->deletePattern('*:GET:/blog/'.$slug.'*');
        }
    }
}
