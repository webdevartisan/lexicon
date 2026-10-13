<?php

declare(strict_types=1);

/**
 * Breadcrumbs Configuration
 *
 * configure breadcrumb behavior and define translation keys for common patterns.
 * Translation keys follow the pattern: "breadcrumbs.{item}"
 * Example: "breadcrumbs.overview" → /locales/en.json → {"breadcrumbs": {"overview": "Overview"}}
 */

return [
    /**
     * Whether to show the home breadcrumb
     */
    'show_home' => false,

    /**
     * Home breadcrumb configuration
     */
    'home_label' => 'Home',
    'home_url' => '/',
    'home_translation_key' => 'breadcrumbs.home',

    /**
     * Area names with translation keys
     * use these for root-level breadcrumbs in different sections
     */
    'area_names' => [
        'front' => ['label' => 'Home', 'key' => 'breadcrumbs.home'],
        'back' => ['label' => 'Overview', 'key' => 'breadcrumbs.overview'],
        'admin' => ['label' => 'Admin Panel', 'key' => 'breadcrumbs.adminPanel'],
    ],

    /**
     * URL segments to hide from breadcrumbs
     * hide action verbs and internal identifiers from user-facing trails
     */
    'hidden_segments' => [
        'submit',
        'update',
        'create',
        'destroy',
        'delete',
        'api',
        'render-html',
        'preview',
        'autosave',
    ],

    /**
     * Segment label overrides with translation keys
     * provide human-readable labels for common URL segments
     */
    'segment_labels' => [
        'new' => ['label' => 'Create New', 'key' => 'breadcrumbs.createNew'],
        'edit' => ['label' => 'Edit', 'key' => 'breadcrumbs.edit'],
        'show' => ['label' => 'View Details', 'key' => 'breadcrumbs.viewDetails'],
        'users' => ['label' => 'Manage Users', 'key' => 'breadcrumbs.manageUsers'],
        'settings' => ['label' => 'Settings', 'key' => 'breadcrumbs.settings'],
        'profile' => ['label' => 'My Profile', 'key' => 'breadcrumbs.myProfile'],
    ],

    /**
     * Contextual breadcrumb patterns with translation keys
     *
     * define complete breadcrumb trails for specific URL patterns.
     * Format: 'path/pattern' => [
     *     ['label' => 'Fallback Label', 'url' => '/url or null', 'key' => 'breadcrumbs.key'],
     * ]
     */
    'patterns' => [
        // Account settings moved to the front under /account, which has no
        // breadcrumb, so the old dashboard settings patterns were removed here.

        // Dashboard pages work on the active blog, so every trail starts at its Overview.
        '/dashboard/blog/{id}/settings' => [
            ['label' => 'Overview', 'url' => '/dashboard', 'key' => 'breadcrumbs.overview'],
            ['label' => 'Blog Settings', 'url' => null, 'key' => 'breadcrumbs.blogSettings'],
        ],
        '/dashboard/blog/{id}/team' => [
            ['label' => 'Overview', 'url' => '/dashboard', 'key' => 'breadcrumbs.overview'],
            ['label' => 'Collaborators', 'url' => null, 'key' => 'breadcrumbs.collaborators'],
        ],
        '/dashboard/blog/{id}/appearance' => [
            ['label' => 'Overview', 'url' => '/dashboard', 'key' => 'breadcrumbs.overview'],
            ['label' => 'Appearance', 'url' => null, 'key' => 'breadcrumbs.appearance'],
        ],
        '/dashboard/blog/{id}/categories' => [
            ['label' => 'Overview', 'url' => '/dashboard', 'key' => 'breadcrumbs.overview'],
            ['label' => 'Categories / Tags', 'url' => null, 'key' => 'breadcrumbs.categoriesTags'],
        ],
        '/dashboard/blog/{id}/media' => [
            ['label' => 'Overview', 'url' => '/dashboard', 'key' => 'breadcrumbs.overview'],
            ['label' => 'Media Library', 'url' => null, 'key' => 'breadcrumbs.mediaLibrary'],
        ],

        '/dashboard/post/{id}/edit' => [
            ['label' => 'Overview', 'url' => '/dashboard', 'key' => 'breadcrumbs.overview'],
            ['label' => 'Posts', 'url' => '/dashboard/post', 'key' => 'breadcrumbs.posts'],
            ['label' => 'Edit Post', 'url' => null, 'key' => 'breadcrumbs.editPost'],
        ],
        '/dashboard/post/new' => [
            ['label' => 'Overview', 'url' => '/dashboard', 'key' => 'breadcrumbs.overview'],
            ['label' => 'Posts', 'url' => '/dashboard/post', 'key' => 'breadcrumbs.posts'],
            ['label' => 'Create New Post', 'url' => null, 'key' => 'breadcrumbs.createNewPost'],
        ],

        // Admin patterns
        '/admin/users/{id}/edit' => [
            ['label' => 'Admin Panel', 'url' => '/admin', 'key' => 'breadcrumbs.adminPanel'],
            ['label' => 'Users', 'url' => '/admin/users', 'key' => 'breadcrumbs.users'],
            ['label' => 'Edit User', 'url' => null, 'key' => 'breadcrumbs.editUser'],
        ],
        '/admin/blogs/{id}/show' => [
            ['label' => 'Admin Panel', 'url' => '/admin', 'key' => 'breadcrumbs.adminPanel'],
            ['label' => 'Blogs', 'url' => '/admin/blogs', 'key' => 'breadcrumbs.blogs'],
            ['label' => 'View Blog', 'url' => null, 'key' => 'breadcrumbs.viewBlog'],
        ],
        '/admin/posts/{id}/edit' => [
            ['label' => 'Admin Panel', 'url' => '/admin', 'key' => 'breadcrumbs.adminPanel'],
            ['label' => 'Posts', 'url' => '/admin/posts', 'key' => 'breadcrumbs.posts'],
            ['label' => 'Edit Post', 'url' => null, 'key' => 'breadcrumbs.editPost'],
        ],
    ],

    /**
     * Whether to show numeric IDs in breadcrumbs
     * typically hide IDs for cleaner user experience
     */
    'show_ids' => false,
];
