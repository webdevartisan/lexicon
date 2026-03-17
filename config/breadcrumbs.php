<?php

/**
 * Breadcrumbs Configuration
 *
 * configure breadcrumb behavior and define translation keys for common patterns.
 * Translation keys follow the pattern: "breadcrumbs.{item}"
 * Example: "breadcrumbs.dashboard" → /locales/en.json → {"breadcrumbs": {"dashboard": "Dashboard"}}
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
        'back' => ['label' => 'Dashboard', 'key' => 'breadcrumbs.dashboard'],
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
        // Blog management patterns
        '/dashboard/blog/{id}/edit' => [
            ['label' => 'Dashboard', 'url' => '/dashboard', 'key' => 'breadcrumbs.dashboard'],
            ['label' => 'All Blogs', 'url' => '/dashboard/blog', 'key' => 'breadcrumbs.allBlogs'],
            ['label' => 'Edit Blog', 'url' => null, 'key' => 'breadcrumbs.editBlog'],
        ],
        '/dashboard/blog/{id}/show' => [
            ['label' => 'Dashboard', 'url' => '/dashboard', 'key' => 'breadcrumbs.dashboard'],
            ['label' => 'All Blogs', 'url' => '/dashboard/blog', 'key' => 'breadcrumbs.allBlogs'],
            ['label' => 'Blog Overview', 'url' => null, 'key' => 'breadcrumbs.blogOverview'],
        ],
        '/dashboard/blog/{id}/users' => [
            ['label' => 'Dashboard', 'url' => '/dashboard', 'key' => 'breadcrumbs.dashboard'],
            ['label' => 'All Blogs', 'url' => '/dashboard/blog', 'key' => 'breadcrumbs.allBlogs'],
            ['label' => 'Collaborators', 'url' => null, 'key' => 'breadcrumbs.collaborators'],
        ],
        '/dashboard/blog/{id}/theme' => [
            ['label' => 'Dashboard', 'url' => '/dashboard', 'key' => 'breadcrumbs.dashboard'],
            ['label' => 'All Blogs', 'url' => '/dashboard/blog', 'key' => 'breadcrumbs.allBlogs'],
            ['label' => 'Appearance', 'url' => null, 'key' => 'breadcrumbs.appearance'],
        ],
        '/dashboard/blog/{id}/categories' => [
            ['label' => 'Dashboard', 'url' => '/dashboard', 'key' => 'breadcrumbs.dashboard'],
            ['label' => 'All Blogs', 'url' => '/dashboard/blog', 'key' => 'breadcrumbs.allBlogs'],
            ['label' => 'Categories / Tags', 'url' => null, 'key' => 'breadcrumbs.categoriesTags'],
        ],
        '/dashboard/blog/{id}/media' => [
            ['label' => 'Dashboard', 'url' => '/dashboard', 'key' => 'breadcrumbs.dashboard'],
            ['label' => 'All Blogs', 'url' => '/dashboard/blog', 'key' => 'breadcrumbs.allBlogs'],
            ['label' => 'Media Library', 'url' => null, 'key' => 'breadcrumbs.mediaLibrary'],
        ],

        // Post management patterns
        '/dashboard/post/{id}/edit' => [
            ['label' => 'Dashboard', 'url' => '/dashboard', 'key' => 'breadcrumbs.dashboard'],
            ['label' => 'All Posts', 'url' => '/dashboard/post', 'key' => 'breadcrumbs.allPosts'],
            ['label' => 'Edit Post', 'url' => null, 'key' => 'breadcrumbs.editPost'],
        ],
        '/dashboard/post/{id}/review' => [
            ['label' => 'Dashboard', 'url' => '/dashboard', 'key' => 'breadcrumbs.dashboard'],
            ['label' => 'All Posts', 'url' => '/dashboard/post', 'key' => 'breadcrumbs.allPosts'],
            ['label' => 'Review Post', 'url' => null, 'key' => 'breadcrumbs.reviewPost'],
        ],
        '/dashboard/post/new' => [
            ['label' => 'Dashboard', 'url' => '/dashboard', 'key' => 'breadcrumbs.dashboard'],
            ['label' => 'All Posts', 'url' => '/dashboard/post', 'key' => 'breadcrumbs.allPosts'],
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
