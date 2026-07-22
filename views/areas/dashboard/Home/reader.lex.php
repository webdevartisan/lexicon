{% extends "back.lex.php" %}

{% block title %}Your Reading Hub{% endblock %}

{% block body %}
<?php
$readerName = $current_user['display_name_cached'] ?? ($current_user['username'] ?? 'there');
$hasFeed = !empty($feed);
$hasActivity = !empty($replies) || !empty($myComments);
?>

<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    <div class="flex flex-col gap-1 mb-6">
        <h1 class="text-xl font-semibold text-slate-900 dark:text-zink-50">
            Welcome back, <?= e((string) $readerName) ?>
        </h1>
        <p class="text-sm text-slate-500 dark:text-zink-300">
            New posts from your blogs, your saved reading, and your conversations — all in one place.
        </p>
    </div>

    <div class="card mb-6" id="reader-intro" hidden>
        <div class="card-body flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-slate-800 dark:text-zink-100 mb-1">Your Reading Hub</p>
                <p class="text-xs text-slate-500 dark:text-zink-300 mb-2">
                    This is your space for saved posts, subscriptions, and replies across all blogs.
                </p>
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs font-medium">
                    <a href="<?= e(lurl('/library/saved')) ?>" class="text-custom-500 hover:text-custom-600">Explore saved posts &rarr;</a>
                    <a href="<?= e(lurl('/library/subscriptions')) ?>" class="text-custom-500 hover:text-custom-600">Manage subscriptions &rarr;</a>
                </div>
            </div>
            <button type="button" data-dismiss aria-label="Dismiss"
                    class="shrink-0 text-slate-400 hover:text-slate-600 dark:text-zink-300 dark:hover:text-zink-100">
                {% cache 'lucide:x:sm' ttl=31536000 %}<i data-lucide="x" class="size-4"></i>{% endcache %}
            </button>
        </div>
    </div>
    <script>
    (function () {
        var key = 'lexicon_reader_intro_dismissed';
        var card = document.getElementById('reader-intro');
        if (!card) return;
        try {
            if (!localStorage.getItem(key)) card.removeAttribute('hidden');
            card.querySelector('[data-dismiss]').addEventListener('click', function () {
                localStorage.setItem(key, '1');
                card.setAttribute('hidden', '');
            });
        } catch (e) { /* storage unavailable: keep the card hidden */ }
    })();
    </script>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 items-start">
        <div class="xl:col-span-2 flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-zink-300">Your reading feed</h2>
            </div>

            <?php if (!$hasFeed) { ?>
                <div class="card">
                    <div class="card-body py-12 text-center">
                        <p class="text-sm font-medium text-slate-700 dark:text-zink-100 mb-1">Your feed is empty</p>
                        <p class="text-xs text-slate-500 dark:text-zink-300 mb-4">
                            Subscribe to any blog and its new posts will land here.
                        </p>
                        <a href="<?= e(lurl('/blogs')) ?>" class="btn bg-custom-500 border-custom-500 text-white hover:bg-custom-600 hover:border-custom-600 inline-flex items-center gap-1.5">
                            {% cache 'lucide:compass:sm' ttl=31536000 %}<i data-lucide="compass" class="size-4"></i>{% endcache %}
                            Explore blogs
                        </a>
                    </div>
                </div>
            <?php } else { ?>
                <?php foreach ($feed as $p) { ?>
                    <?php $url = lurl('/blog/'.rawurlencode((string) $p['blog_slug']).'/'.rawurlencode((string) $p['slug'])); ?>
                    <div class="card">
                        <div class="card-body flex flex-col gap-1">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-400 dark:text-zink-300">
                                <span class="font-medium text-custom-500"><?= e((string) ($p['blog_name'] ?? '')) ?></span>
                                <?php if (!empty($p['published_at'])) { ?>
                                    <span><?= e(local_datetime($p['published_at'] ?? null, 'M j, Y')) ?></span>
                                <?php } ?>
                            </div>
                            <a href="<?= e($url) ?>" class="text-sm font-medium text-slate-900 dark:text-zink-50 hover:text-custom-600 dark:hover:text-custom-400 transition-colors">
                                <?= e((string) $p['title']) ?>
                            </a>
                            <?php if (!empty($p['excerpt'])) { ?>
                                <p class="text-xs text-slate-500 dark:text-zink-300"><?= e(truncate((string) $p['excerpt'], 160)) ?></p>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>

        <div class="flex flex-col gap-5">
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-zink-100">Saved for later</h3>
                        <a href="<?= e(lurl('/library/saved')) ?>" class="text-xs font-medium text-custom-500 hover:text-custom-600">All saved &rarr;</a>
                    </div>
                    <?php if (empty($savedPosts)) { ?>
                        <p class="text-xs text-slate-500 dark:text-zink-300">Save posts to build your reading list — they sync across your devices.</p>
                    <?php } else { ?>
                        <div class="flex flex-col gap-2">
                            <?php foreach ($savedPosts as $p) { ?>
                                <a href="<?= e(lurl('/blog/'.rawurlencode((string) $p['blog_slug']).'/'.rawurlencode((string) $p['slug']))) ?>"
                                   class="text-xs font-medium text-slate-700 dark:text-zink-100 hover:text-custom-600 dark:hover:text-custom-400 transition-colors">
                                    <?= e((string) $p['title']) ?>
                                    <span class="block font-normal text-[11px] text-slate-400 dark:text-zink-300"><?= e((string) ($p['blog_name'] ?? '')) ?></span>
                                </a>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-zink-100">Your blogs</h3>
                        <a href="<?= e(lurl('/library/subscriptions')) ?>" class="text-xs font-medium text-custom-500 hover:text-custom-600">Manage &rarr;</a>
                    </div>
                    <?php if (empty($subscriptions)) { ?>
                        <p class="text-xs text-slate-500 dark:text-zink-300">Subscribe to any blog to get its new posts here and by email.</p>
                    <?php } else { ?>
                        <div class="flex flex-col gap-2">
                            <?php foreach (array_slice($subscriptions, 0, 5) as $s) { ?>
                                <div class="flex items-center justify-between gap-2">
                                    <a href="<?= e(lurl('/blog/'.rawurlencode((string) $s['blog_slug']))) ?>"
                                       class="text-xs font-medium text-slate-700 dark:text-zink-100 hover:text-custom-600 dark:hover:text-custom-400 transition-colors">
                                        <?= e((string) $s['blog_name']) ?>
                                    </a>
                                    <?php if (!empty($s['latest_post_at'])) { ?>
                                        <span class="text-[11px] text-slate-400 dark:text-zink-300"><?= e(local_datetime($s['latest_post_at'] ?? null, 'M j')) ?></span>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-zink-100">Recent activity</h3>
                        <a href="<?= e(lurl('/library/activity')) ?>" class="text-xs font-medium text-custom-500 hover:text-custom-600">All activity &rarr;</a>
                    </div>
                    <?php if (!$hasActivity) { ?>
                        <p class="text-xs text-slate-500 dark:text-zink-300">Join discussions — replies to your comments will appear here.</p>
                    <?php } else { ?>
                        <div class="flex flex-col gap-2">
                            <?php foreach (array_slice($replies ?? [], 0, 3) as $r) { ?>
                                <a href="<?= e(lurl('/blog/'.rawurlencode((string) $r['blog_slug']).'/'.rawurlencode((string) $r['post_slug'])).'#comment-'.(int) $r['id']) ?>"
                                   class="text-xs text-slate-600 dark:text-zink-200 hover:text-custom-600 dark:hover:text-custom-400 transition-colors">
                                    <span class="font-medium"><?= e((string) $r['author_name']) ?></span> replied on
                                    <span class="font-medium"><?= e((string) $r['post_title']) ?></span>
                                </a>
                            <?php } ?>
                            <?php if (empty($replies)) { ?>
                                <?php foreach (array_slice($myComments ?? [], 0, 3) as $c) { ?>
                                    <span class="text-xs text-slate-600 dark:text-zink-200">
                                        You commented on <span class="font-medium"><?= e((string) $c['post_title']) ?></span>
                                    </span>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-zink-100 mb-1">Email updates</h3>
                    <p class="text-xs text-slate-500 dark:text-zink-300 mb-2">
                        Choose which notifications you get from your subscribed blogs and conversations.
                    </p>
                    <a href="<?= e(lurl('/dashboard/profile')) ?>" class="text-xs font-medium text-custom-500 hover:text-custom-600">Manage email preferences &rarr;</a>
                </div>
            </div>

            <div class="card border-dashed">
                <div class="card-body text-center py-6">
                    <p class="text-xs text-slate-500 dark:text-zink-300 mb-2">Feel like writing?</p>
                    <a href="<?= e(lurl('/dashboard/blog/new')) ?>" class="inline-flex items-center gap-1.5 text-xs font-medium text-custom-500 hover:text-custom-600">
                        {% cache 'lucide:file-plus:sm' ttl=31536000 %}<i data-lucide="file-plus" class="size-3.5"></i>{% endcache %}
                        Start a blog
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
{% endblock %}
