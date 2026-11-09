<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\UserModel;
use App\Models\UserProfileModel;
use Framework\Exceptions\PageNotFoundException;

/**
 * What every themed blog page needs before it renders: the blog, its owner, settings,
 * meta, the signed in reader, the blog's languages and the theme's wording.
 *
 * Blog pages and module pages both start here, so a module page looks and behaves
 * like any other page of the blog.
 */
class BlogPageContext
{
    public function __construct(
        private BlogModel $blogs,
        private UserModel $users,
        private BlogSettingsModel $settings,
        private UserProfileModel $profiles,
        private BlogLocales $blogLocales,
        private ThemeWording $themeWording,
        private ViewerContext $viewers,
    ) {}

    /**
     * The page context for a blog address.
     *
     * @return array{user: array<string, mixed>, blog: array<string, mixed>, settings: array<string, mixed>,
     *     meta: array<string, mixed>, viewer: mixed, localeSet: mixed, wording: mixed}
     *
     * @throws PageNotFoundException When the blog or its owner is missing, or it is a draft the viewer may not see
     */
    public function forSlug(string $blogSlug): array
    {
        [$blog, $isPreview] = $this->visibleBlog($blogSlug);
        $user = $this->owner($blog);
        $settings = $this->settings->findByBlogId((int) $blog['id']) ?? [];

        // Themes iterate this as platform => URL, so hand them the decoded map
        $settings['social_links'] = BlogSettingsModel::decodeSocialLinks($settings['social_links'] ?? null);

        $meta = $this->meta($blog, $user, $settings, $isPreview);

        // Full-page cache only serves guests, so viewer-specific markup never leaks between users.
        $viewer = $this->viewers->current();

        // Recorded on LocaleState so the head globals stop advertising translations that do not exist.
        $localeSet = $this->blogLocales->for((int) $blog['id'], $settings);
        LocaleState::setLocaleSet($localeSet);

        $wording = $this->themeWording->forPage(
            (int) $blog['id'],
            isset($settings['theme']) ? (string) $settings['theme'] : null
        );

        return compact('user', 'blog', 'settings', 'meta', 'viewer', 'localeSet', 'wording');
    }

    /**
     * The blog row for a slug, and whether this view is the team previewing a draft blog.
     *
     * @return array{0: array<string, mixed>, 1: bool}
     *
     * @throws PageNotFoundException When the blog is missing or is a draft the viewer may not see
     */
    private function visibleBlog(string $blogSlug): array
    {
        $blog = $this->blogs->getBlogBySlug($blogSlug);
        if (!$blog) {
            throw new PageNotFoundException('Blog not found', 404);
        }

        $isPreview = ($blog['status'] ?? 'published') === 'draft' && $this->viewerCanPreview((int) $blog['id']);
        if (($blog['status'] ?? 'published') !== 'published' && !$isPreview) {
            throw new PageNotFoundException('Blog not found', 404);
        }

        return [$blog, $isPreview];
    }

    /**
     * The blog's owner, with the fields every themed page shows for the author.
     *
     * @param  array<string, mixed>  $blog
     * @return array<string, mixed>
     *
     * @throws PageNotFoundException When the owner account is gone
     */
    private function owner(array $blog): array
    {
        $user = $this->users->findById($blog['owner_id']);
        if (!$user) {
            throw new PageNotFoundException('Blog not found', 404);
        }

        // Back-compat field some templates expect
        $user['blog_name'] = $blog['blog_name'] ?? ($user['handle']."'s Blog");

        // Every themed surface renders the owner as the author (the post guard
        // enforces it), so one lookup covers bylines, cards, and archives
        $user['public_profile_handle'] = $this->profiles->publicHandleFor((int) $user['id']);

        return $user;
    }

    /**
     * Whether the signed-in viewer belongs to the blog and may see what readers can't yet.
     */
    public function viewerCanPreview(int $blogId): bool
    {
        return auth()->check() && $this->blogs->userCanAccessBlog((int) auth()->user()['id'], $blogId);
    }

    /**
     * An upload path or URL as an absolute URL, for share tags that crawlers read off site.
     */
    public function absoluteUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return rtrim(base_url(), '/').'/'.ltrim($url, '/');
    }

    /**
     * @param  array<string, mixed>  $blog
     * @param  array<string, mixed>  $user
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function meta(array $blog, array $user, array $settings, bool $isPreview): array
    {
        $blogUrl = rtrim(base_url(), '/').lurl('/blog/'.rawurlencode((string) ($blog['blog_slug'] ?? '')));

        return [
            'title' => $settings['meta_title'] ?? ($blog['blog_name'] ?? ($user['display_name_cached']."'s Blog")),
            'description' => $settings['meta_description'] ?? ($blog['description'] ?? ''),
            'url' => $blogUrl,
            'site_name' => (string) ($blog['blog_name'] ?? ''),
            'og_type' => 'website',
            'og_title' => null, // themes fall back to title
            'og_description' => null, // themes fall back to description
            'og_image' => $this->absoluteUrl($settings['banner_path'] ?? null),
            'twitter_card' => 'summary_large_image',
            // The blog home is reachable under any locale prefix that survives the
            // redirect guard, so name the content-locale URL as the one to index.
            'canonical' => $blogUrl,
            // Only an explicit indexable=false opts the blog out; a missing settings row must not.
            'robots' => $isPreview || (array_key_exists('indexable', $settings) && !$settings['indexable'])
                ? 'noindex, nofollow'
                : null,
        ];
    }
}
