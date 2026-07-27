{% extends "back.lex.php" %}

{% block title %}Subscriptions{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    <div class="flex flex-col gap-2 mb-6">
        <h1 class="text-xl font-semibold text-slate-900 dark:text-zink-50">Subscriptions</h1>
        <p class="text-sm text-slate-500 dark:text-zink-300">
            Every blog you follow. New posts show up in your feed and land in your inbox.
        </p>
    </div>

    <?php if (empty($subscriptions)) { ?>
        <div class="card">
            <div class="card-body py-12 text-center">
                <p class="text-sm font-medium text-slate-700 dark:text-zink-100 mb-1">You're not following any blogs yet</p>
                <p class="text-xs text-slate-500 dark:text-zink-300 mb-4">
                    Subscribe on any blog page and it will show up here.
                </p>
                <a href="<?= e(lurl('/blogs')) ?>" class="btn bg-custom-500 border-custom-500 text-white hover:bg-custom-600 hover:border-custom-600 inline-flex items-center gap-1.5">
                    {% cache 'lucide:compass:sm' ttl=31536000 %}<i data-lucide="compass" class="size-4"></i>{% endcache %}
                    Explore blogs
                </a>
            </div>
        </div>
    <?php } else { ?>
        <div class="flex flex-col gap-3">
            <?php foreach ($subscriptions as $s) { ?>
                <?php $isLive = ($s['blog_status'] ?? '') === 'published'; ?>
                <div class="card">
                    <div class="card-body flex flex-wrap items-start justify-between gap-3">
                        <div class="flex flex-col gap-1 min-w-0">
                            <?php if ($isLive) { ?>
                                <a href="<?= e(lurl('/blog/'.rawurlencode((string) $s['blog_slug']))) ?>"
                                   class="text-sm font-medium text-slate-900 dark:text-zink-50 hover:text-custom-600 dark:hover:text-custom-400 transition-colors">
                                    <?= e((string) $s['blog_name']) ?>
                                </a>
                            <?php } else { ?>
                                <span class="text-sm font-medium text-slate-500 dark:text-zink-300"><?= e((string) $s['blog_name']) ?></span>
                            <?php } ?>
                            <?php if (!empty($s['description'])) { ?>
                                <p class="text-xs text-slate-500 dark:text-zink-300"><?= e(truncate((string) $s['description'], 140)) ?></p>
                            <?php } ?>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-400 dark:text-zink-300 mt-1">
                                <span><?= (int) ($s['post_count'] ?? 0) ?> post<?= (int) ($s['post_count'] ?? 0) === 1 ? '' : 's' ?></span>
                                <?php if (!empty($s['latest_post_at'])) { ?>
                                    <span>Latest: <?= e(local_datetime($s['latest_post_at'] ?? null, 'M j, Y')) ?></span>
                                <?php } ?>
                                <?php if (!empty($s['created_at'])) { ?>
                                    <span>Subscribed <?= e(local_datetime($s['created_at'] ?? null, 'M j, Y')) ?></span>
                                <?php } ?>
                                <?php if (!$isLive) { ?>
                                    <span class="text-amber-500">Not published right now</span>
                                <?php } ?>
                            </div>
                        </div>
                        <form action="/library/subscriptions/<?= (int) $s['id'] ?>/unsubscribe" method="post"
                              data-confirm="Unsubscribe from <?= e((string) $s['blog_name']) ?>?">
                            <?= csrf_field() ?>
                            <button type="submit"
                                    class="btn bg-white text-slate-500 border-slate-200 hover:text-red-500 hover:border-red-200 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-200 dark:hover:text-red-400 text-xs">
                                Unsubscribe
                            </button>
                        </form>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>
{% endblock %}
