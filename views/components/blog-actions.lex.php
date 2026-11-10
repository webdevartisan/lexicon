<?php
/**
 * The actions for one blog: Open, plus a menu with its settings, team, live site and leave.
 * The Blogs cards and list rows both use it, so the two views always offer the same actions.
 * Renders two siblings (the Open form and the menu) for the caller to lay out.
 *
 * Attributes:
 * blog: the card row from BlogController, with is_owner, can_settings and can_team
 */
$blog = $blog ?? [];
$bid = (int) ($blog['id'] ?? 0);
$blogName = (string) ($blog['blog_name'] ?? '');
$menu = [
    [
        'label' => $t('dashboard.blogs.settings'),
        'icon' => 'sliders',
        'href' => '/dashboard/blog/'.$bid.'/settings',
        'can' => !empty($blog['can_settings']),
    ],
    [
        'label' => $t('dashboard.blogs.team'),
        'icon' => 'users',
        'href' => '/dashboard/blog/'.$bid.'/team',
        'can' => !empty($blog['can_team']),
    ],
    [
        'label' => $t('dashboard.actions.viewLive'),
        'icon' => 'external-link',
        'href' => '/blog/'.($blog['blog_slug'] ?? ''),
        'newTab' => true,
    ],
    [
        'label' => $t('dashboard.blogs.leave'),
        'icon' => 'log-out',
        'post' => '/dashboard/blog/'.$bid.'/team/leave',
        'confirm' => $t('dashboard.blogs.leaveConfirm', ['name' => $blogName]),
        'danger' => true,
        'can' => empty($blog['is_owner']),
    ],
];
?>
<form method="POST" action="/dashboard/active-blog" class="m-0">
    {{ csrf_field() }}
    <input type="hidden" name="blog" value="<?= $bid ?>">
    <input type="hidden" name="return_to" value="/dashboard">
    <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-medium text-custom-500 hover:text-custom-600 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500 rounded">
        {% cache 'lucide:blogs:arrow-right' ttl=31536000 %}<i data-lucide="arrow-right" class="size-4 rtl:rotate-180" aria-hidden="true"></i>{% endcache %}
        <?= e($t('dashboard.blogs.open')) ?><span class="sr-only"> <?= e($blogName) ?></span>
    </button>
</form>
{% cmp="row-actions" title="{$blogName}" items="{$menu}" %}
