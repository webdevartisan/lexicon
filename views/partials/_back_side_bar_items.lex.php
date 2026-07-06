{% cache $sidebar_cache_key ttl=3600 %}
<?php
/**
 * Dashboard Sidebar Navigation
 *
 * We render navigation items with server-side translations via $t() function.
 * Navigation is organized into global items (always visible) and contextual items
 * (only shown when a blog is selected).
 */

/**
 * Navigation Icons Mapping
 *
 * We map navigation item tags to Lucide icon names.
 * Add new icons here as you expand your navigation.
 */
$icons = [
    // Global Navigation Icons
    'home' => 'home',
    'dashboard' => 'layout-dashboard',
    'create-new-blog' => 'file-plus',
    'new-blog' => 'file-plus',
    'all-blogs' => 'book-open',
    'shared' => 'inbox',
    'my-blogs' => 'book-open',
    'account-settings' => 'settings',

    // Blog Management Icons
    'blog-overview' => 'layout-grid',
    'edit-blog-settings' => 'sliders',
    'appearance-/-theme' => 'palette',
    'collaborators' => 'users',
    'team' => 'users',

    // Content Management Icons
    'new-post' => 'pen',
    'all-posts' => 'files',
    'my-posts' => 'files',
    'categories-/-tags' => 'folder-tree',
    'media-library' => 'image',

    // Analytics Icons
    'traffic' => 'trending-up',
    'comments' => 'message-square',

    // Admin Icons
    'users' => 'users',
    'blogs' => 'book-open',
    'posts' => 'files',
    'categories' => 'folder-tree',
    'tags' => 'tags',
    'roles' => 'shield',
    'moderation' => 'shield-check',
    'audit-log' => 'history',
    'system' => 'server',
    'cache-management' => 'database-zap',
    'email-test' => 'mail-warning',
    'settings' => 'settings',
];

/**
 * We separate navigation items by scope for better organization
 */
$globalItems = array_filter($nav_items, fn ($item) => ($item['scope'] ?? null) === 'global');
$contextualItems = array_filter($nav_items, fn ($item) => ($item['scope'] ?? null) === 'contextual');
$legacyItems = array_filter($nav_items, fn ($item) => !isset($item['scope'])); // Backward compatibility
?>

<!-- Global Navigation Section -->
<?php if (!empty($globalItems) || !empty($legacyItems)) { ?>
    <li class="px-4 py-1 text-vertical-menu-item  group-data-[sidebar=brand]:text-vertical-menu-item-brand group-data-[sidebar=modern]:text-vertical-menu-item-modern uppercase font-medium text-[11px] cursor-default tracking-wider group-data-[sidebar-size=sm]:hidden inline-block group-data-[sidebar-size=md]:block group-data-[sidebar-size=md]:underline group-data-[sidebar-size=md]:text-center">
        <span><?= $t('common.navigation') ?></span>
    </li>

    <?php foreach (array_merge($legacyItems, $globalItems) as $it) { ?>
        <?php if (($it['type'] ?? 'link') === 'section_header') { ?>
        <li class="px-4 py-2 mt-2 text-vertical-menu-item  group-data-[sidebar=brand]:text-vertical-menu-item-brand group-data-[sidebar=modern]:text-vertical-menu-item-modern uppercase font-semibold text-[10px] cursor-default tracking-widest opacity-60 group-data-[sidebar-size=sm]:hidden block group-data-[sidebar-size=md]:block group-data-[sidebar-size=md]:text-center">
            <span>
                <?php echo !empty($it['key']) ? $t($it['key']) : e($it['label']); ?>
            </span>
        </li>
        <?php continue; } ?>
        <li class="relative group/sm">
            <a class="sidebar-menu-item group/menu-link"
               href="<?= e($it['href']) ?>"
               data-nav-path="<?= e(lurl($it['href'])) ?>"
               >
                
                <span class="min-w-[1.75rem] group-data-[sidebar-size=sm]:h-[1.75rem] inline-block text-start text-[16px] group-data-[sidebar-size=md]:block group-data-[sidebar-size=sm]:flex group-data-[sidebar-size=sm]:items-center">
                    <i data-lucide="<?= $icons[$it['tag']] ?? 'circle' ?>" 
                       class="h-4 group-data-[sidebar-size=sm]:h-5 group-data-[sidebar-size=sm]:w-5 transition group-hover/menu-link:animate-icons fill-slate-100 group-hover/menu-link:fill-blue-200 group-data-[sidebar=dark]:fill-vertical-menu-item-bg-active-dark group-data-[sidebar=dark]:dark:fill-zink-600 group-data-[sidebar=brand]:fill-vertical-menu-item-bg-active-brand group-data-[sidebar=modern]:fill-vertical-menu-item-bg-active-modern group-data-[sidebar=dark]:group-hover/menu-link:fill-vertical-menu-item-bg-active-dark group-data-[sidebar=dark]:group-hover/menu-link:dark:fill-custom-500/20 group-data-[sidebar=brand]:group-hover/menu-link:fill-vertical-menu-item-bg-active-brand group-data-[sidebar=modern]:group-hover/menu-link:fill-vertical-menu-item-bg-active-modern group-data-[sidebar-size=md]:block group-data-[sidebar-size=md]:mx-auto group-data-[sidebar-size=md]:mb-2"></i>
                </span>
                <span class="group-data-[sidebar-size=sm]:ltr:pl-10 group-data-[sidebar-size=sm]:rtl:pr-10 align-middle group-data-[sidebar-size=sm]:group-hover/sm:block group-data-[sidebar-size=sm]:hidden">
                    <?php
                    // render server-side translation with fallback to label
                    echo !empty($it['key']) ? $t($it['key']) : e($it['label']);
        ?>
                </span>
            </a>
        </li>
    <?php } ?>
<?php } ?>

<!-- Contextual Navigation Section - Only shown when blog is selected -->
<?php if (!empty($contextualItems) && $has_blog_context) { ?>
    <?php foreach ($contextualItems as $it) { ?>
        <?php if (($it['type'] ?? 'link') === 'section_header') { ?>
            <!-- Section Header -->
            <li class="px-4 py-2 mt-2 text-vertical-menu-item  group-data-[sidebar=brand]:text-vertical-menu-item-brand group-data-[sidebar=modern]:text-vertical-menu-item-modern uppercase font-semibold text-[10px] cursor-default tracking-widest opacity-60 group-data-[sidebar-size=sm]:hidden block group-data-[sidebar-size=md]:block group-data-[sidebar-size=md]:text-center">
                <span>
                    <?php
                        // render server-side translation with fallback to label
                        echo !empty($it['key']) ? $t($it['key']) : e($it['label']);
            ?>
                </span>
            </li>
        <?php } elseif (!empty($it['disabled'])) { ?>
            <li class="relative group/sm">
                <span class="sidebar-menu-item cursor-default opacity-40 select-none"
                      aria-disabled="true"
                      title="Coming soon">
                    <span class="min-w-[1.75rem] group-data-[sidebar-size=sm]:h-[1.75rem] inline-block text-start text-[16px] group-data-[sidebar-size=md]:block group-data-[sidebar-size=sm]:flex group-data-[sidebar-size=sm]:items-center">
                        <i data-lucide="<?= $icons[$it['tag']] ?? 'circle' ?>"
                           class="h-4 group-data-[sidebar-size=sm]:h-5 group-data-[sidebar-size=sm]:w-5 fill-slate-100 group-data-[sidebar=dark]:fill-vertical-menu-item-bg-active-dark group-data-[sidebar=dark]:dark:fill-zink-600 group-data-[sidebar-size=md]:block group-data-[sidebar-size=md]:mx-auto group-data-[sidebar-size=md]:mb-2"></i>
                    </span>
                    <span class="group-data-[sidebar-size=sm]:ltr:pl-10 group-data-[sidebar-size=sm]:rtl:pr-10 align-middle group-data-[sidebar-size=sm]:group-hover/sm:block group-data-[sidebar-size=sm]:hidden inline-flex items-center gap-2">
                        <?php echo !empty($it['key']) ? $t($it['key']) : e($it['label']); ?>
                        <?php if (!empty($it['badge'])) { ?>
                        <span class="text-[9px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded-full bg-slate-200 text-slate-600 dark:bg-zink-600 dark:text-zink-200 group-data-[sidebar-size=md]:hidden group-data-[sidebar-size=sm]:hidden">
                            <?= e($it['badge']) ?>
                        </span>
                        <?php } ?>
                    </span>
                </span>
            </li>
        <?php } else { ?>
            <!-- Regular Navigation Link -->
            <li class="relative group/sm">
                <a class="sidebar-menu-item group/menu-link"
                   href="<?= e($it['href']) ?>"
                   data-nav-path="<?= e(lurl($it['href'])) ?>"
                   >

                    <span class="min-w-[1.75rem] group-data-[sidebar-size=sm]:h-[1.75rem] inline-block text-start text-[16px] group-data-[sidebar-size=md]:block group-data-[sidebar-size=sm]:flex group-data-[sidebar-size=sm]:items-center">
                        <i data-lucide="<?= $icons[$it['tag']] ?? 'circle' ?>"
                           class="h-4 group-data-[sidebar-size=sm]:h-5 group-data-[sidebar-size=sm]:w-5 transition group-hover/menu-link:animate-icons fill-slate-100 group-hover/menu-link:fill-blue-200 group-data-[sidebar=dark]:fill-vertical-menu-item-bg-active-dark group-data-[sidebar=dark]:dark:fill-zink-600 group-data-[sidebar=brand]:fill-vertical-menu-item-bg-active-brand group-data-[sidebar=modern]:fill-vertical-menu-item-bg-active-modern group-data-[sidebar=dark]:group-hover/menu-link:fill-vertical-menu-item-bg-active-dark group-data-[sidebar=dark]:group-hover/menu-link:dark:fill-custom-500/20 group-data-[sidebar=brand]:group-hover/menu-link:fill-vertical-menu-item-bg-active-brand group-data-[sidebar=modern]:group-hover/menu-link:fill-vertical-menu-item-bg-active-modern group-data-[sidebar-size=md]:block group-data-[sidebar-size=md]:mx-auto group-data-[sidebar-size=md]:mb-2"></i>
                    </span>
                    <span class="group-data-[sidebar-size=sm]:ltr:pl-10 group-data-[sidebar-size=sm]:rtl:pr-10 align-middle group-data-[sidebar-size=sm]:group-hover/sm:block group-data-[sidebar-size=sm]:hidden">
                        <?php
                            echo !empty($it['key']) ? $t($it['key']) : e($it['label']);
            ?>
                    </span>
                </a>
            </li>
        <?php } ?>
    <?php } ?>
<?php } ?>

<!-- Empty state - what to say depends on why there's no blog context.
     Only meaningful in the dashboard; admin navigation is complete on its own. -->
<?php if (($area ?? '') === 'back' && (empty($contextualItems) || !$has_blog_context)) { ?>
    <?php if (empty($user_blogs) && !empty($is_collaborator)) { ?>
        <!-- Collaborator without own blogs: their work is on the Shared page -->
        <li class="px-4 py-3 mt-3 text-vertical-menu-item text-center text-xs group-data-[sidebar-size=sm]:hidden">
            <span class="block opacity-50 italic mb-2"><?= $t('common.sharedOnlyHint') ?></span>
            <a href="/dashboard/shared" class="inline-flex items-center gap-1.5 font-medium text-custom-500 hover:text-custom-600 not-italic">
                <i data-lucide="inbox" class="size-3.5"></i>
                <?= $t('common.openShared') ?>
            </a>
        </li>
    <?php } elseif (empty($user_blogs)) { ?>
        <!-- Brand new account: nothing owned, nothing shared -->
        <li class="px-4 py-3 mt-3 text-vertical-menu-item text-center text-xs group-data-[sidebar-size=sm]:hidden">
            <span class="block opacity-50 italic mb-2"><?= $t('common.createFirstBlogHint') ?></span>
            <a href="/dashboard/blog/new" class="inline-flex items-center gap-1.5 font-medium text-custom-500 hover:text-custom-600 not-italic">
                <i data-lucide="file-plus" class="size-3.5"></i>
                <?= $t('common.createFirstBlog') ?>
            </a>
        </li>
    <?php } else { ?>
        <li class="px-4 py-3 mt-3 text-vertical-menu-item  opacity-50 text-center text-xs italic group-data-[sidebar-size=sm]:hidden">
            <span><?= $t('common.selectBlog') ?></span>
        </li>
    <?php } ?>
<?php } ?>

{% endcache %}