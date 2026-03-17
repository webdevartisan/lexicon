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
     * @return array Profile data with keys: profile, socialLinks, posts
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

        $socialLinks = $this->enrichSocialLinksWithIcons(
            $this->socialLinks->listByUser($profile->userId())
        );

        $posts = $this->getPublicPostsWithBlogSlugs($profile->userId());

        return [
            'profile' => $profile,
            'socialLinks' => $socialLinks,
            'posts' => $posts,
        ];
    }

    /**
     * Enrich social links with Font Awesome icon classes.
     *
     * Maps network names to icon classes. Twitter uses X (Twitter) branding,
     * others use standard Font Awesome naming. Falls back to generic icon
     * for unknown networks.
     *
     * @param  array  $socialLinks  Raw social link data
     * @return array Social links with icon field added
     */
    private function enrichSocialLinksWithIcons(array $socialLinks): array
    {
        return array_map(function ($link) {
            $link['icon'] = match ($link['network']) {
                'twitter' => 'fa-brands fa-x-twitter',
                'facebook' => 'fa-brands fa-facebook',
                'instagram' => 'fa-brands fa-instagram',
                'linkedin' => 'fa-brands fa-linkedin',
                'github' => 'fa-brands fa-github',
                'youtube' => 'fa-brands fa-youtube',
                default => 'fa fa-'.$link['network']
            };

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
     * @return array Posts with blog_slug field
     */
    private function getPublicPostsWithBlogSlugs(int $userId): array
    {
        // TODO: Make limit configurable via config/profile.php
        $posts = $this->posts->listByAuthorVisibility($userId, ['public'], 10);

        if (empty($posts)) {
            return [];
        }

        // Get blog slugs for all posts in single query
        $blogIds = array_unique(array_column($posts, 'blog_id'));
        $blogs = $this->blogs->findByIds($blogIds);
        $blogSlugs = array_column($blogs, 'blog_slug', 'id');

        return array_map(function ($post) use ($blogSlugs) {
            $post['blog_slug'] = $blogSlugs[$post['blog_id']] ?? null;

            return $post;
        }, $posts);
    }
}
