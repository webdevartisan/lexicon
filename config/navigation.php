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
        ['label' => 'Getting Started', 'href' => '/getting-started', 'auth' => null, 'key' => 'navigation.gettingStarted'],
        ['label' => 'About', 'href' => '/about', 'auth' => null, 'key' => 'navigation.about'],
        ['label' => 'Contact', 'href' => '/contact', 'auth' => null, 'key' => 'navigation.contact'],
        ['label' => 'Admin', 'href' => '/admin', 'auth' => true, 'roles' => ['administrator'], 'key' => 'navigation.admin'],
    ],

    /**
     * Dashboard navigation (authenticated users)
     */
    'back' => [
        // === READER ITEMS ===
        // Every account reads, only some write, so these stay visible after a
        // reader starts a blog. Gating them on isReader used to orphan a
        // creator's saves and subscriptions: the pages kept working but
        // nothing linked to them any more.
        [
            'label' => 'Library',
            'href' => '/library',
            'auth' => true,
            'scope' => 'global',
            'key' => 'navigation.library',
            // Collapsible group. The parent is a real link to the hub, and the
            // sidebar auto-expands it whenever the current path sits under it.
            'children' => [
                [
                    'label' => 'Liked',
                    'href' => '/library/likes',
                    'key' => 'navigation.liked',
                ],
                [
                    'label' => 'Saved',
                    'href' => '/library/saved',
                    'key' => 'navigation.saved',
                ],
                [
                    'label' => 'Subscriptions',
                    'href' => '/library/subscriptions',
                    'key' => 'navigation.subscriptions',
                ],
                [
                    'label' => 'Activity',
                    'href' => '/library/activity',
                    'key' => 'navigation.activity',
                ],
            ],
        ],
        [
            // Creators already reach this from the topbar user menu, so it
            // only needs a sidebar slot for reader-only accounts.
            'label' => 'Profile',
            'href' => '/dashboard/profile',
            'auth' => true,
            'scope' => 'global',
            'show_if' => 'isReader',
            'key' => 'navigation.profile',
        ],
        [
            // Same reasoning as Profile: readers have no topbar user menu entry
            // for the private settings, so they get a sidebar slot instead.
            'label' => 'Account',
            'href' => '/dashboard/account',
            'auth' => true,
            'scope' => 'global',
            'show_if' => 'isReader',
            'key' => 'navigation.account',
        ],

        // === GLOBAL ITEMS ===
        [
            'label' => 'Dashboard',
            'href' => '/dashboard',
            'auth' => true,
            'scope' => 'global',
            'show_if' => 'isCreator',
            'key' => 'navigation.dashboard',
        ],
        [
            'label' => 'All Blogs',
            'href' => '/dashboard/blog',
            'auth' => true,
            'scope' => 'global',
            'show_if' => 'isCreator',
            'key' => 'navigation.allBlogs',
        ],
        [
            'label' => 'Shared',
            'href' => '/dashboard/shared',
            'auth' => true,
            'scope' => 'global',
            'show_if' => 'isCollaborator',
            'key' => 'navigation.shared',
        ],
        /*
        [
            'label' => 'Create New Blog',
            'href' => '/dashboard/blog/new',
            'auth' => true,
            'scope' => 'global',
            'key' => 'navigation.createNewBlog',
        ],*/

        // === CONTEXTUAL ITEMS ===

        [
            'label' => 'Content',
            'href' => '#',
            'auth' => true,
            'scope' => 'contextual',
            'type' => 'section_header',
            'blog_roles' => ['owner', 'editor', 'author', 'contributor'],
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
            'blog_roles' => ['owner', 'editor', 'author', 'contributor'],
            'key' => 'navigation.allPosts',
        ],
        [
            'label' => 'Comments',
            'href' => '/dashboard/blog/{blogId}/comments',
            'auth' => true,
            'scope' => 'contextual',
            'replace_blog_id' => true,
            'policy' => 'update',
            'key' => 'navigation.comments',
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
            'blog_roles' => ['owner', 'editor', 'author', 'contributor'],
            'key' => 'navigation.mediaLibrary',
        ],
        [
            'label' => 'Manage Blog',
            'href' => '#',
            'auth' => true,
            'scope' => 'contextual',
            'type' => 'section_header',
            'blog_roles' => ['owner', 'editor', 'author', 'contributor'],
            'key' => 'navigation.blogSection',
        ],
        [
            'label' => 'Blog Overview',
            'href' => '/dashboard/blog/{blogId}/show',
            'auth' => true,
            'scope' => 'contextual',
            'replace_blog_id' => true,
            'policy' => 'view',
            'blog_roles' => ['owner', 'editor', 'author', 'contributor'],
            'key' => 'navigation.blogOverview',
        ],
        [
            'label' => 'Blog Settings',
            'href' => '/dashboard/blog/{blogId}/settings',
            'auth' => true,
            'scope' => 'contextual',
            'replace_blog_id' => true,
            'policy' => 'update',
            'key' => 'navigation.editBlogSettings',
        ],
        [
            'label' => 'Team',
            'href' => '/dashboard/blog/{blogId}/team',
            'auth' => true,
            'scope' => 'contextual',
            'replace_blog_id' => true,
            'policy' => 'manageUsers',
            'key' => 'navigation.team',
        ],
        [
            'label' => 'Subscribers',
            'href' => '/dashboard/blog/{blogId}/subscribers',
            'auth' => true,
            'scope' => 'contextual',
            'replace_blog_id' => true,
            'policy' => 'manageUsers',
            'key' => 'navigation.subscribers',
        ],
        [
            'label' => 'Appearance',
            'href' => '/dashboard/blog/{blogId}/appearance',
            'auth' => true,
            'scope' => 'contextual',
            'replace_blog_id' => true,
            'policy' => 'update',
            'key' => 'navigation.appearanceTheme',
        ],
        [
            'label' => 'Insights',
            'href' => '#',
            'auth' => true,
            'scope' => 'contextual',
            'type' => 'section_header',
            'blog_roles' => ['owner', 'editor', 'author', 'contributor'],
            'key' => 'navigation.analyticsSection',
        ],
        [
            'label' => 'Traffic',
            'href' => '/dashboard/blog/{blogId}/analytics/traffic',
            'auth' => true,
            'scope' => 'contextual',
            'replace_blog_id' => true,
            'policy' => 'view',
            'blog_roles' => ['owner', 'editor', 'author', 'contributor'],
            'key' => 'navigation.traffic',
            'disabled' => true,
            'badge' => 'Soon',
        ],
    ],

    /**
     * Admin navigation.
     *
     * Administrators see everything via the roles gate; other roles see an
     * item when they hold any permission listed on it (the same slugs
     * SystemPolicy uses to authorize the matching controllers). Section
     * headers carry the union of their children's permissions so a partial
     * panel still renders coherent sections.
     */
    'admin' => [
        ['label' => 'Home', 'href' => '/admin', 'auth' => true, 'roles' => ['administrator'], 'permissions' => ['access_control_panel', 'manage_all_blogs', 'manage_all_posts', 'moderate_comments', 'manage_taxonomy', 'manage_all_users', 'manage_roles', 'view_audit_log', 'view_system_health', 'manage_cache', 'manage_site_settings'], 'key' => 'navigation.home'],

        ['label' => 'Content', 'href' => '#', 'type' => 'section_header', 'auth' => true, 'roles' => ['administrator'], 'permissions' => ['manage_all_blogs', 'manage_all_posts', 'moderate_comments', 'manage_taxonomy'], 'key' => 'navigation.contentSection'],
        ['label' => 'Blogs', 'href' => '/admin/blogs', 'auth' => true, 'roles' => ['administrator'], 'permissions' => ['manage_all_blogs'], 'key' => 'navigation.blogs'],
        ['label' => 'Posts', 'href' => '/admin/posts', 'auth' => true, 'roles' => ['administrator'], 'permissions' => ['manage_all_posts'], 'key' => 'navigation.posts'],
        ['label' => 'Moderation', 'href' => '/admin/comments', 'auth' => true, 'roles' => ['administrator'], 'permissions' => ['moderate_comments'], 'key' => 'navigation.moderation'],
        ['label' => 'Categories', 'href' => '/admin/categories', 'auth' => true, 'roles' => ['administrator'], 'permissions' => ['manage_taxonomy'], 'key' => 'navigation.categories'],
        ['label' => 'Tags', 'href' => '/admin/tags', 'auth' => true, 'roles' => ['administrator'], 'permissions' => ['manage_taxonomy'], 'key' => 'navigation.tags'],

        ['label' => 'People', 'href' => '#', 'type' => 'section_header', 'auth' => true, 'roles' => ['administrator'], 'permissions' => ['manage_all_users', 'manage_roles'], 'key' => 'navigation.peopleSection'],
        ['label' => 'Users', 'href' => '/admin/users', 'auth' => true, 'roles' => ['administrator'], 'permissions' => ['manage_all_users'], 'key' => 'navigation.users'],
        ['label' => 'Roles', 'href' => '/admin/roles', 'auth' => true, 'roles' => ['administrator'], 'permissions' => ['manage_roles'], 'key' => 'navigation.roles'],

        ['label' => 'System', 'href' => '#', 'type' => 'section_header', 'auth' => true, 'roles' => ['administrator'], 'permissions' => ['view_audit_log', 'view_system_health', 'manage_cache', 'manage_mail_queue', 'manage_site_settings'], 'key' => 'navigation.systemSection'],
        ['label' => 'Audit Log', 'href' => '/admin/audit-log', 'auth' => true, 'roles' => ['administrator'], 'permissions' => ['view_audit_log'], 'key' => 'navigation.auditLog'],
        ['label' => 'System', 'href' => '/admin/system', 'auth' => true, 'roles' => ['administrator'], 'permissions' => ['view_system_health'], 'key' => 'navigation.system'],
        ['label' => 'Cache Management', 'href' => '/admin/cache', 'auth' => true, 'roles' => ['administrator'], 'permissions' => ['manage_cache'], 'key' => 'navigation.cacheManagement'],
        ['label' => 'Email Templates', 'href' => '/admin/email-test', 'auth' => true, 'roles' => ['administrator'], 'permissions' => ['manage_site_settings'], 'key' => 'navigation.emailTest'],
        ['label' => 'Mail Queue', 'href' => '/admin/mail-queue', 'auth' => true, 'roles' => ['administrator'], 'permissions' => ['manage_mail_queue'], 'key' => 'navigation.mailQueue'],
        ['label' => 'Front Page', 'href' => '/admin/front-page', 'auth' => true, 'roles' => ['administrator'], 'permissions' => ['manage_site_settings'], 'key' => 'navigation.frontPage'],
        ['label' => 'Pages', 'href' => '/admin/pages', 'auth' => true, 'roles' => ['administrator'], 'permissions' => ['manage_site_settings'], 'key' => 'navigation.pages'],
        ['label' => 'Settings', 'href' => '/admin/settings', 'auth' => true, 'roles' => ['administrator'], 'permissions' => ['manage_site_settings'], 'key' => 'navigation.settings'],
    ],
];
