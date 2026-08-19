{% extends "back.lex.php" %}

{% block title %}{% if tab == 'likes' %}Liked posts{% else %}Saved posts{% endif %}{% endblock %}
{% block subtitle %}{% if tab == 'likes' %}Every post you have liked across all blogs, newest first.{% else %}Posts you bookmarked to come back to later.{% endif %}{% endblock %}

{% block body %}
<?php
$isLikes = ($tab ?? 'likes') === 'likes';
$dateKey = $isLikes ? 'liked_at' : 'bookmarked_at';
?>

<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    <div class="flex items-center gap-1 mb-4 border-b border-slate-200 dark:border-zink-600">
        <a href="<?= e(lurl('/library/likes')) ?>"
           class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors <?= $isLikes
               ? 'border-custom-500 text-custom-600 dark:text-custom-400'
               : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-zink-300 dark:hover:text-zink-100' ?>">
            {% cache 'lucide:heart:sm' ttl=31536000 %}<i data-lucide="heart" class="inline-block size-4 align-text-bottom"></i>{% endcache %}
            Liked
        </a>
        <a href="<?= e(lurl('/library/saved')) ?>"
           class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors <?= !$isLikes
               ? 'border-custom-500 text-custom-600 dark:text-custom-400'
               : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-zink-300 dark:hover:text-zink-100' ?>">
            {% cache 'lucide:bookmark:sm' ttl=31536000 %}<i data-lucide="bookmark" class="inline-block size-4 align-text-bottom"></i>{% endcache %}
            Saved
        </a>
    </div>

    <?php if (empty($posts)) { ?>
        <div class="card">
            <div class="card-body py-12 text-center">
                <p class="text-sm font-medium text-slate-700 dark:text-zink-100 mb-1">
                    <?= $isLikes ? 'Nothing liked yet' : 'Nothing saved yet' ?>
                </p>
                <p class="text-xs text-slate-500 dark:text-zink-300">
                    <?= $isLikes
                        ? 'Tap the heart on any post and it will show up here.'
                        : 'Tap save on any post to keep it here for later.' ?>
                </p>
            </div>
        </div>
    <?php } else { ?>
        <div class="flex flex-col gap-3">
            <?php foreach ($posts as $p) { ?>
                <?php $url = lurl('/blog/'.rawurlencode((string) $p['blog_slug']).'/'.rawurlencode((string) $p['slug'])); ?>
                <div class="card">
                    <div class="card-body flex flex-col gap-1">
                        <a href="<?= e($url) ?>" class="text-sm font-medium text-slate-900 dark:text-zink-50 hover:text-custom-600 dark:hover:text-custom-400 transition-colors">
                            <?= e((string) $p['title']) ?>
                        </a>
                        <?php if (!empty($p['excerpt'])) { ?>
                            <p class="text-xs text-slate-500 dark:text-zink-300"><?= e(truncate((string) $p['excerpt'], 140)) ?></p>
                        <?php } ?>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-400 dark:text-zink-300 mt-1">
                            <span><?= e((string) ($p['blog_name'] ?? '')) ?></span>
                            <span><?= e(local_datetime($p[$dateKey] ?? null, 'M j, Y')) ?></span>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>
{% endblock %}
