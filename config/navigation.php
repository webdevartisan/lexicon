<?php

declare(strict_types=1);

/**
 * Navigation configuration.
 *
 * Defines navigation items for different areas (front, back, admin).
 * Each item references a translation key in a hierarchical format to support i18n.
 *
 * Translation keys follow the pattern: "navigation.{item}".
 * Example: "navigation.dashboard" corresponds to:
 *   /locales/en.json = {"navigation": {"dashboard": "Dashboard"}}.
 */

return [
    /**
     * Front-end navigation (public pages)
     */
    'front' => [
        ['label' => 'Home', 'href' => '/', 'auth' => null, 'key' => 'navigation.home'],
        ['label' => 'Create a Blog', 'href' => '/login', 'auth' => false, 'key' => 'navigation.createBlog'],
        ['label' => 'Create a Blog', 'href' => '/dashboard', 'auth' => true, 'key' => 'navigation.createBlog'],
        ['label' => 'Explore Blogs', 'href' => '/blogs', 'auth' => null, 'key' => 'navigation.exploreBlogs'],
        ['label' => 'Admin', 'href' => '/admin', 'auth' => true, 'roles' => ['administrator'], 'key' => 'navigation.admin'],
    ],

    /**
     * Dashboard navigation (authenticated users)
     */
    'back' => [
        // === GLOBAL ITEMS - Always visible ===
        [
            'label' => 'Dashboard',
            'href' => '/dashboard',
            'auth' => true,
            'scope' => 'global',
            'key' => 'navigation.dashboard',
        ],
        [
            'label' => 'Create New Blog',
            'href' => '/dashboard/blog/new',
            'auth' => true,
            'scope' => 'global',
            'key' => 'navigation.createNewBlog',
        ],
        [
            'label' => 'All Blogs',
            'href' => '/dashboard/blog',
            'auth' => true,
            'scope' => 'global',
            'key' => 'navigation.allBlogs',
        ],
        [
            'label' => 'Account Settings',
            'href' => '/account/settings',
            'auth' => true,
            'scope' => 'global',
            'key' => 'navigation.accountSettings',
        ],

        // === CONTEXTUAL ITEMS - Only when blog selected ===
        // Blog Management Section
        [
            'label' => 'Blog Section',
            'href' => '#',
            'auth' => true,
            'scope' => 'contextual',
            'type' => 'section_header',
            'key' => 'navigation.blogSection',
        ],
        [
            'label' => 'Blog Overview',
            'href' => '/dashboard/blog/{blogId}/show',
            'auth' => true,
            'scope' => 'contextual',
            'replace_blog_id' => true,
            'policy' => 'view',
            'key' => 'navigation.blogOverview',
        ],
        [
            'label' => 'Edit Blog Settings',
            'href' => '/dashboard/blog/{blogId}/edit',
            'auth' => true,
            'scope' => 'contextual',
            'replace_blog_id' => true,
            'policy' => 'update',
            'key' => 'navigation.editBlogSettings',
        ],
        [
            'label' => 'Appearance / Theme',
            'href' => '/dashboard/blog/{blogId}/theme',
            'auth' => true,
            'scope' => 'contextual',
            'replace_blog_id' => true,
            'policy' => 'update',
            'key' => 'navigation.appearanceTheme',
        ],
        [
            'label' => 'Collaborators',
            'href' => '/dashboard/blog/{blogId}/users',
            'auth' => true,
            'scope' => 'contextual',
            'replace_blog_id' => true,
            'policy' => 'manageUsers',
            'key' => 'navigation.collaborators',
        ],

        // Content Management Section
        [
            'label' => 'Content Section',
            'href' => '#',
            'auth' => true,
            'scope' => 'contextual',
            'type' => 'section_header',
            'key' => 'navigation.contentSection',
        ],
        [
            'label' => 'New Post',
            'href' => '/dashboard/post/new',
            'auth' => true,
            'scope' => 'contextual',
            'policy' => 'createPost',
            'key' => 'navigation.newPost',
        ],
        [
            'label' => 'All Posts',
            'href' => '/dashboard/post',
            'auth' => true,
            'scope' => 'contextual',
            'policy' => 'view',
            'key' => 'navigation.allPosts',
        ],
        [
            'label' => 'Categories / Tags',
            'href' => '/dashboard/blog/{blogId}/categories',
            'auth' => true,
            'scope' => 'contextual',
            'replace_blog_id' => true,
            'policy' => 'update',
            'key' => 'navigation.categoriesTags',
        ],
        [
            'label' => 'Media Library',
            'href' => '/dashboard/blog/{blogId}/media',
            'auth' => true,
            'scope' => 'contextual',
            'replace_blog_id' => true,
            'policy' => 'view',
            'key' => 'navigation.mediaLibrary',
        ],

        // Analytics Section
        [
            'label' => 'Analytics Section',
            'href' => '#',
            'auth' => true,
            'scope' => 'contextual',
            'type' => 'section_header',
            'key' => 'navigation.analyticsSection',
        ],
        [
            'label' => 'Traffic',
            'href' => '/dashboard/blog/{blogId}/analytics/traffic',
            'auth' => true,
            'scope' => 'contextual',
            'replace_blog_id' => true,
            'policy' => 'view',
            'key' => 'navigation.traffic',
        ],
        [
            'label' => 'Comments',
            'href' => '/dashboard/blog/{blogId}/analytics/comments',
            'auth' => true,
            'scope' => 'contextual',
            'replace_blog_id' => true,
            'policy' => 'view',
            'key' => 'navigation.comments',
        ],
    ],

    /**
     * Admin navigation (administrators only)
     */
    'admin' => [
        ['label' => 'Home', 'href' => '/admin', 'auth' => true, 'roles' => ['administrator'], 'key' => 'navigation.home'],
        ['label' => 'Users', 'href' => '/admin/users', 'auth' => true, 'roles' => ['administrator'], 'key' => 'navigation.users'],
        ['label' => 'Blogs', 'href' => '/admin/blogs', 'auth' => true, 'roles' => ['administrator'], 'key' => 'navigation.blogs'],
        ['label' => 'Posts', 'href' => '/admin/posts', 'auth' => true, 'roles' => ['administrator'], 'key' => 'navigation.posts'],
        ['label' => 'Categories', 'href' => '/admin/categories', 'auth' => true, 'roles' => ['administrator'], 'key' => 'navigation.categories'],
        ['label' => 'Tags', 'href' => '/admin/tags', 'auth' => true, 'roles' => ['administrator'], 'key' => 'navigation.tags'],
        ['label' => 'Roles', 'href' => '/admin/roles', 'auth' => true, 'roles' => ['administrator'], 'key' => 'navigation.roles'],
        ['label' => 'Cache Management', 'href' => '/admin/cache', 'auth' => true, 'roles' => ['administrator'], 'key' => 'navigation.cacheManagement'],
        ['label' => 'Email Test', 'href' => '/admin/email-test', 'auth' => true, 'roles' => ['administrator'], 'key' => 'navigation.emailTest'],
        ['label' => 'Settings', 'href' => '/admin/settings', 'auth' => true, 'roles' => ['administrator'], 'key' => 'navigation.settings'],
    ],
];
