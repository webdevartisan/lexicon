{% extends "back.lex.php" %}

{% block title %}Activity{% endblock %}
{% block subtitle %}Your comments and the replies people left you, across every blog.{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 items-start">
        <div class="flex flex-col gap-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-zink-300">Replies to you</h2>

            <?php if (empty($replies)) { ?>
                <div class="card">
                    <div class="card-body py-10 text-center">
                        <p class="text-sm font-medium text-slate-700 dark:text-zink-100 mb-1">No replies yet</p>
                        <p class="text-xs text-slate-500 dark:text-zink-300">When someone answers one of your comments, it shows up here.</p>
                    </div>
                </div>
            <?php } else { ?>
                <?php foreach ($replies as $r) { ?>
                    <?php $url = lurl('/blog/'.rawurlencode((string) $r['blog_slug']).'/'.rawurlencode((string) $r['post_slug'])).'#comment-'.(int) $r['id']; ?>
                    <div class="card">
                        <div class="card-body flex flex-col gap-1">
                            <div class="flex flex-wrap items-center gap-x-2 text-xs text-slate-500 dark:text-zink-300">
                                <span class="font-medium text-slate-700 dark:text-zink-100"><?= e((string) $r['author_name']) ?></span>
                                <span>replied on</span>
                                <a href="<?= e($url) ?>" class="font-medium text-custom-500 hover:text-custom-600"><?= e((string) $r['post_title']) ?></a>
                            </div>
                            <p class="text-xs text-slate-600 dark:text-zink-200"><?= e(truncate((string) $r['content'], 180)) ?></p>
                            <?php if (!empty($r['created_at'])) { ?>
                                <span class="text-[11px] text-slate-400 dark:text-zink-300"><?= e(local_datetime($r['created_at'] ?? null, 'M j, Y')) ?></span>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>

        <div class="flex flex-col gap-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-zink-300">Your comments</h2>

            <?php if (empty($myComments)) { ?>
                <div class="card">
                    <div class="card-body py-10 text-center">
                        <p class="text-sm font-medium text-slate-700 dark:text-zink-100 mb-1">No comments yet</p>
                        <p class="text-xs text-slate-500 dark:text-zink-300">Join a discussion on any post — your comments will be collected here.</p>
                    </div>
                </div>
            <?php } else { ?>
                <?php foreach ($myComments as $c) { ?>
                    <?php
                    $approved = ($c['status'] ?? '') === 'approved';
                    $url = lurl('/blog/'.rawurlencode((string) $c['blog_slug']).'/'.rawurlencode((string) $c['post_slug']));
                    // Pending comments aren't rendered on the post page, so no anchor for them
                    if ($approved) {
                        $url .= '#comment-'.(int) $c['id'];
                    }
                    ?>
                    <div class="card">
                        <div class="card-body flex flex-col gap-1">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-zink-300">
                                <span>On</span>
                                <a href="<?= e($url) ?>" class="font-medium text-custom-500 hover:text-custom-600"><?= e((string) $c['post_title']) ?></a>
                                <?php if (!$approved) { ?>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300">
                                        <?= e((string) $c['status']) ?>
                                    </span>
                                <?php } ?>
                            </div>
                            <p class="text-xs text-slate-600 dark:text-zink-200"><?= e(truncate((string) $c['content'], 180)) ?></p>
                            <?php if (!empty($c['created_at'])) { ?>
                                <span class="text-[11px] text-slate-400 dark:text-zink-300"><?= e(local_datetime($c['created_at'] ?? null, 'M j, Y')) ?></span>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </div>
</div>
{% endblock %}
