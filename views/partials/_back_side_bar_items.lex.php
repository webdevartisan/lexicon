{% cache $sidebar_cache_key ttl=31536000 %}
<?php
/**
 * Dashboard and admin sidebar.
 *
 * Global items are always shown; contextual items only once a blog is active.
 * The markup is cached per user, so anything that changes per page (the active
 * row, open groups, badge counts) is filled in by sidebar-active.js.
 */

/**
 * Lucide icon per item tag, for items that don't name their own icon.
 */
$icons = [
    'home' => 'home',
    'overview' => 'layout-dashboard',
    'library' => 'library',
    'liked' => 'heart',
    'saved' => 'bookmark',
    'subscriptions' => 'mail',
    'activity' => 'message-circle',
    'profile' => 'user',
    'account' => 'settings',
    'create-new-blog' => 'file-plus',
    'new-blog' => 'file-plus',
    'my-blogs' => 'book-open',
    'account-settings' => 'settings',
    'edit-blog-settings' => 'sliders',
    'appearance-/-theme' => 'palette',
    'collaborators' => 'users',
    'team' => 'users',
    'subscribers' => 'mail',
    'my-posts' => 'files',
    'categories-/-tags' => 'folder-tree',
    'media-library' => 'image',
    'insights' => 'trending-up',
    'comments' => 'message-square',
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
    'notifications' => 'bell',
    'rate-limits' => 'gauge',
    'email-templates' => 'mail-open',
    'email-delivery' => 'send',
    'mail-queue' => 'inbox',
    'scheduled-tasks' => 'calendar-clock',
    'front-page' => 'layout-template',
    'pages' => 'file-text',
    'blog-settings' => 'sliders',
    'appearance' => 'palette',
];

$iconFor = static fn (array $item): string => $item['icon'] ?? $icons[$item['tag']] ?? 'circle';
$labelFor = static fn (array $item): string => !empty($item['key']) ? $t($item['key']) : e($item['label']);

/**
 * Icon, label and, when the item has a live count, an empty badge slot.
 */
$renderRowInner = static function (array $it) use ($iconFor, $labelFor): void {
    ?>
        <span class="sidebar-menu-icon"><i data-lucide="<?= e($iconFor($it)) ?>"></i></span>
        <span class="sidebar-menu-label"><?= $labelFor($it) ?></span>
        <?php if (!empty($it['counter'])) { ?>
        <span class="sidebar-badge" data-nav-badge="<?= e($it['counter']) ?>" hidden></span>
        <?php } ?>
    <?php
};

/**
 * A collapsible group: the parent links to the group's first page, the button
 * shows or hides the children. sidebar-active.js decides which is open.
 */
$renderGroup = static function (array $it) use ($iconFor, $labelFor, $renderRowInner, $t): void {
    $submenuId = 'nav-group-'.preg_replace('/[^a-z0-9-]/', '', $it['tag']);
    ?>
        <li class="sidebar-entry" data-nav-group data-nav-group-key="<?= e($it['key'] ?? $it['tag']) ?>" data-nav-group-path="<?= e(lurl($it['href'])) ?>">
            <div class="sidebar-menu-row">
                <a class="sidebar-menu-item sidebar-menu-item-group" href="<?= e(lurl($it['href'])) ?>" data-nav-path="<?= e(lurl($it['href'])) ?>">
                    <?php $renderRowInner($it); ?>
                </a>
                <button type="button" data-nav-toggle class="sidebar-menu-toggle"
                        aria-expanded="false" aria-controls="<?= e($submenuId) ?>"
                        aria-label="<?= e($t('navigation.toggleGroup', ['name' => strip_tags($labelFor($it))])) ?>">
                    <i data-lucide="chevron-right"></i>
                </button>
            </div>
            <div id="<?= e($submenuId) ?>" class="sidebar-submenu" data-nav-submenu>
                <ul class="sidebar-submenu-list">
                    <?php foreach ($it['children'] as $child) { ?>
                    <li>
                        <a class="sidebar-menu-item" href="<?= e(lurl($child['href'])) ?>" data-nav-path="<?= e(lurl($child['href'])) ?>">
                            <span class="sidebar-menu-icon"><i data-lucide="<?= e($iconFor($child)) ?>"></i></span>
                            <span class="sidebar-menu-label"><?= $labelFor($child) ?></span>
                        </a>
                    </li>
                    <?php } ?>
                </ul>
            </div>
        </li>
    <?php
};

/**
 * One navigation entry of any kind: section heading, group, disabled row or link.
 */
$renderItem = static function (array $it) use ($renderGroup, $renderRowInner, $labelFor): void {
    if (($it['type'] ?? 'link') === 'section_header') {
        ?><li class="sidebar-heading"><?= $labelFor($it) ?></li><?php
        return;
    }

    if (!empty($it['children'])) {
        $renderGroup($it);

        return;
    }

    if (!empty($it['disabled'])) {
        ?>
        <li class="sidebar-entry">
            <span class="sidebar-menu-item is-disabled" aria-disabled="true">
                <?php $renderRowInner($it); ?>
                <?php if (!empty($it['badge'])) { ?>
                <span class="sidebar-tag"><?= e($it['badge']) ?></span>
                <?php } ?>
            </span>
        </li>
        <?php
        return;
    }
    ?>
        <li class="sidebar-entry">
            <a class="sidebar-menu-item" href="<?= e(lurl($it['href'])) ?>" data-nav-path="<?= e(lurl($it['href'])) ?>">
                <?php $renderRowInner($it); ?>
            </a>
        </li>
    <?php
};

$globalItems = array_filter($nav_items, fn ($item) => ($item['scope'] ?? null) !== 'contextual');
$contextualItems = $has_blog_context
    ? array_filter($nav_items, fn ($item) => ($item['scope'] ?? null) === 'contextual')
    : [];
?>

<?php if (!empty($globalItems)) { ?>
    <li class="sidebar-heading"><?= $t('common.navigation') ?></li>
    <?php foreach ($globalItems as $it) { $renderItem($it); } ?>
<?php } ?>

<?php foreach ($contextualItems as $it) { $renderItem($it); } ?>

<!-- Empty state: no blog is active. A reader who may create one is offered a first blog,
     anyone with blogs is pointed at Blogs to choose one. Only meaningful in the dashboard. -->
<?php if (($area ?? '') === 'back' && !$has_blog_context && (!empty($user_blogs) || !empty($can_create_blog))) { ?>
    <li class="sidebar-empty">
        <?php if (empty($user_blogs)) { ?>
        <span class="block opacity-50 italic mb-2"><?= $t('common.startWritingHint') ?></span>
        <a href="<?= e(lurl('/start')) ?>" data-panel class="inline-flex items-center gap-1.5 font-medium text-custom-500 hover:text-custom-600 not-italic">
            <i data-lucide="file-plus" class="size-3.5"></i>
            <?= $t('common.startWriting') ?>
        </a>
        <?php } else { ?>
        <span class="block opacity-50 italic mb-2"><?= $t('common.selectBlog') ?></span>
        <a href="<?= e(lurl('/dashboard/blog')) ?>" class="inline-flex items-center gap-1.5 font-medium text-custom-500 hover:text-custom-600 not-italic">
            <i data-lucide="book-open" class="size-3.5"></i>
            <?= $t('navigation.blogs') ?>
        </a>
        <?php } ?>
    </li>
<?php } ?>

<script src="<?= e(versioned_asset('/cp-assets/js/sidebar-active.js')) ?>"></script>
{% endcache %}
