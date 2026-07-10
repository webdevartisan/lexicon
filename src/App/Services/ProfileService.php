<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\UserProfileModel;
use App\Models\UserSocialLinkModel;
use Framework\Exceptions\NotFoundException;

/**
 * Handles public profile data aggregation and business logic.
 *
 * Coordinates profile, social links, and recent posts data for public
 * profile pages. Enriches social links with icon classes and posts
 * with blog slugs for URL generation.
 */
class ProfileService
{
    public function __construct(
        private UserProfileModel $profiles,
        private UserSocialLinkModel $socialLinks,
        private PostModel $posts,
        private BlogModel $blogs
    ) {}

    /**
     * Get public profile data with related content.
     *
     * Returns profile information, social links with icons, and recent
     * public posts enriched with blog slugs. Throws NotFoundException
     * for both missing and private profiles to avoid information disclosure.
     *
     * @param  string  $slug  Public profile slug
     * @return array<string, mixed> Profile data with keys: profile, socialLinks, posts
     *
     * @throws NotFoundException If profile not found or not public
     */
    public function getPublicProfile(string $slug): array
    {
        $profile = $this->profiles->findBySlug($slug);

        // Don't reveal whether profile is private or nonexistent
        if ($profile === null || !$profile->isPublic()) {
            throw new NotFoundException('Profile not found');
        }

        $allLinks = $this->socialLinks->listByUser($profile->userId());

        // the personal website gets its own call-to-action button, so keep it out of the icon row
        $websiteUrl = null;
        $socialLinks = [];

        foreach ($allLinks as $link) {
            if ($link['network'] === 'website') {
                $websiteUrl = $link['url'];
                continue;
            }
            $socialLinks[] = $link;
        }

        $posts = $this->getPublicPostsWithBlogSlugs($profile->userId());

        // count from the posts table directly; users.posts_count includes drafts and private posts
        $stats = [
            'posts' => $this->posts->countByAuthorVisibility($profile->userId(), ['public']),
            'comments' => $this->posts->countPublicCommentsReceived($profile->userId()),
        ];

        return [
            'profile' => $profile,
            'socialLinks' => $this->enrichSocialLinksWithIcons($socialLinks),
            'websiteUrl' => $websiteUrl,
            'posts' => $posts,
            'stats' => $stats,
        ];
    }

    /**
     * Enrich social links with Font Awesome icon classes.
     *
     * Maps network names to the theme's icon classes. Twitter uses X
     * branding; unknown networks fall back to a generic link icon.
     *
     * @param  array<int, array<string, mixed>>  $socialLinks  Raw social link data
     * @return array<int, array<string, mixed>> Social links with icon and iconStyle fields added
     */
    private function enrichSocialLinksWithIcons(array $socialLinks): array
    {
        return array_map(function ($link) {
            // Font Awesome splits glyphs into brand and solid sets, and the
            // theme expects that set name as a companion class on the anchor
            [$style, $icon] = match ($link['network']) {
                'twitter' => ['brands', 'fa-x-twitter'],
                'facebook' => ['brands', 'fa-facebook'],
                'instagram' => ['brands', 'fa-instagram'],
                'linkedin' => ['brands', 'fa-linkedin'],
                'github' => ['brands', 'fa-github'],
                'youtube' => ['brands', 'fa-youtube'],
                default => ['solid', 'fa-link'],
            };

            $link['icon'] = $icon;
            $link['iconStyle'] = $style;

            return $link;
        }, $socialLinks);
    }

    /**
     * Get public posts with blog slug enrichment.
     *
     * Fetches recent public posts and enriches them with blog slugs
     * needed for URL generation. Uses bulk blog lookup to avoid N+1 queries.
     *
     * @param  int  $userId  Author user ID
     * @return array<int, array<string, mixed>> Posts with blog_slug field
     */
    private function getPublicPostsWithBlogSlugs(int $userId): array
    {
        // TODO: Make limit configurable via config/profile.php
        $posts = $this->posts->listByAuthorVisibility($userId, ['public'], 10);

        if (empty($posts)) {
            return [];
        }

        // Get blog slugs and names for all posts in a single query
        $blogIds = array_unique(array_column($posts, 'blog_id'));
        $blogs = $this->blogs->findByIds($blogIds);
        $blogSlugs = array_column($blogs, 'blog_slug', 'id');
        $blogNames = array_column($blogs, 'blog_name', 'id');

        return array_map(function ($post) use ($blogSlugs, $blogNames) {
            $post['blog_slug'] = $blogSlugs[$post['blog_id']] ?? null;
            $post['blog_name'] = $blogNames[$post['blog_id']] ?? null;

            return $post;
        }, $posts);
    }
}
