<?php
$post = $post ?? [];
$blogSlug = $blogSlug ?? '';
$returnToken = $returnToken ?? '';
// Pages that list posts for one role pass what that person may do with each; without it every action shows.
$can = ($post['can'] ?? []) + ['update' => true, 'open' => true];
$postId = (int) $post['id'];
$viewCount = (int) ($post['views'] ?? 0);

$status = $post['status'] ?? 'draft';
$statusStyles = [
    'published' => ['bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800', $t('components.postCard.status.published')],
    'draft' => ['bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-800 dark:text-zink-100 dark:border-zink-600', $t('components.postCard.status.draft')],
    'pending' => ['bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:border-amber-800', $t('components.postCard.status.pending')],
    'scheduled' => ['bg-violet-100 text-violet-700 border-violet-200 dark:bg-violet-900/40 dark:text-violet-200 dark:border-violet-800', $t('components.postCard.status.scheduled')],
    'archived' => ['bg-slate-800 text-slate-100 border-slate-900 dark:bg-zink-900 dark:text-zink-100', $t('components.postCard.status.archived')],
];
[$badgeClass, $badgeLabel] = $statusStyles[$status] ?? $statusStyles['draft'];

$visibility = (string) ($post['visibility'] ?? 'public');
$visibilityChips = [
    'unlisted' => ['bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:border-amber-800', 'eye-off', $t('components.postCard.visibility.unlisted')],
    'private' => ['bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-800 dark:text-zink-100 dark:border-zink-600', 'lock', $t('components.postCard.visibility.private')],
];

$dateRaw = $post['published_at'] ?? $post['updated_at'] ?? $post['created_at'] ?? '';
$dateDisplay = local_datetime($dateRaw ?: null, 'M j, Y');
?>

<div class="card flex flex-col h-full gap-2 transition border border-slate-200 dark:border-zink-600 rounded-lg peer-checked:border-custom-500 peer-checked:bg-slate-100 dark:peer-checked:bg-zink-800/60">
    <div class="card-body flex flex-col flex-1">
        <div class="flex items-start justify-between gap-2 mb-3">
            <span class="inline-flex flex-wrap items-center gap-1.5">
                <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium rounded-full border <?= $badgeClass ?>">
                    <?= e($badgeLabel) ?>
                </span>
                <?php if (isset($visibilityChips[$visibility])) { [$chipClass, $chipIcon, $chipLabel] = $visibilityChips[$visibility]; ?>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium rounded-full border <?= $chipClass ?>">
                    <i data-lucide="<?= e($chipIcon) ?>" class="size-3" aria-hidden="true"></i>
                    <?= e($chipLabel) ?>
                </span>
                <?php } ?>
            </span>

        <?php
        // The reviewer chip only means something while a post is in the review pipeline.
        if ($status === 'pending') {
            if (!empty($post['reviewer_handle'])) {
                $claimedAgo = !empty($post['reviewer_assigned_at'])
                    ? local_datetime($post['reviewer_assigned_at'] ?? null, 'M j')
                    : '';
                ?>
                <div class="mb-2">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium rounded-full bg-sky-50 text-sky-700 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:border-sky-500/30"
                          title="<?= e($t('components.postCard.underReview')) ?>">
                        {% cache 'lucide:lock:sm' ttl=31536000 %}<i data-lucide="lock" class="size-3"></i>{% endcache %}
                        <?= e($t('components.postCard.reviewedBy', ['reviewer' => $post['reviewer_handle']])) ?><?= $claimedAgo ? ' · '.e($claimedAgo) : '' ?>
                    </span>
                </div>
                <?php
            } else {
                ?>
                <div class="mb-2">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium rounded-full bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30"
                          title="<?= e($t('components.postCard.unclaimed')) ?>">
                        {% cache 'lucide:user-plus:sm' ttl=31536000 %}<i data-lucide="user-plus" class="size-3"></i>{% endcache %}
                        <?= e($t('components.postCard.awaitingReviewer')) ?>
                    </span>
                </div>
                <?php
            }
        }
?>
            <?php if ($dateDisplay) { ?>
            <span class="text-[11px] text-slate-500 dark:text-zink-300 whitespace-nowrap">
                <?= e($dateDisplay) ?>
            </span>
            <?php } ?>
        </div>

        <div class="flex flex-wrap items-start gap-1 mb-2" data-post-title data-title="<?= e((string) $post['title']) ?>" data-rename-url="<?= e(lurl('/dashboard/post/'.$postId.'/rename')) ?>">
            <?php if (!empty($post['is_featured'])) { ?>
            <span class="shrink-0 mt-1">{% cmp="featured-star" %}</span>
            <?php } ?>
            <h6 class="flex-1 min-w-0 text-15 line-clamp-2" data-post-title-text>
                <?php if ($can['open']) { ?>
                <a href="<?= e(lurl('/dashboard/post/'.$postId.'/edit?r='.urlencode($returnToken))) ?>" class="hover:text-custom-500 transition-colors">{{ post.title }}</a>
                <?php } else { ?>
                {{ post.title }}
                <?php } ?>
            </h6>
            <?php if ($can['update']) { ?>
            <button type="button" data-post-rename title="<?= e($t('dashboard.posts.rename.button')) ?>" aria-label="<?= e($t('dashboard.posts.rename.button')) ?>"
                class="shrink-0 p-1 rounded-md text-slate-400 hover:text-custom-500 hover:bg-slate-100 dark:text-zink-300 dark:hover:bg-zink-600 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500">
                {% cache 'lucide:pencil:rename' ttl=31536000 %}<i data-lucide="pencil" class="size-3.5" aria-hidden="true"></i>{% endcache %}
            </button>
            <?php } ?>
        </div>
        <p class="text-slate-500 dark:text-zink-200 line-clamp-3 text-sm flex-1">
            <?= e(post_excerpt($post, 120)) ?>
        </p>

        <div class="flex items-center flex-wrap gap-x-3 gap-y-1 mt-3 text-[11px] text-slate-500 dark:text-zink-300">
            <?php if (!empty($post['category_name'])) { ?>
            <span class="inline-flex items-center gap-1">
                {% cache 'lucide:folder:sm' ttl=31536000 %}<i data-lucide="folder" class="size-3"></i>{% endcache %}
                <?= e((string) $post['category_name']) ?>
            </span>
            <?php } ?>
            <?php if (isset($post['comment_count'])) { ?>
            <span class="inline-flex items-center gap-1" title="<?= (int) $post['comment_count'] ?> comments">
                {% cache 'lucide:message-square:sm' ttl=31536000 %}<i data-lucide="message-square" class="size-3"></i>{% endcache %}
                <?= (int) $post['comment_count'] ?>
            </span>
            <?php } ?>
            <?php if (!empty($post['author_handle']) && (int) ($post['author_id'] ?? 0) !== (int) (auth()->user()['id'] ?? 0)) { ?>
            <span class="inline-flex items-center gap-1">
                {% cache 'lucide:user:sm' ttl=31536000 %}<i data-lucide="user" class="size-3"></i>{% endcache %}
                @<?= e((string) $post['author_handle']) ?>
            </span>
            <?php } ?>
            <?php if (!empty($post['blog_name'])) { ?>
            <span class="inline-flex items-center gap-1">
                {% cache 'lucide:book-open:sm' ttl=31536000 %}<i data-lucide="book-open" class="size-3"></i>{% endcache %}
                <?= e((string) $post['blog_name']) ?>
            </span>
            <?php } ?>
            <?php if ($viewCount > 0) { ?>
            <a href="<?= e(lurl('/dashboard/blog/'.(int) $post['blog_id'].'/insights/posts/'.(int) $post['id'])) ?>" class="inline-flex items-center gap-1 hover:text-custom-500 transition-colors" title="<?= e($t('analytics.topPosts.details')) ?>">
                {% cache 'lucide:bar-chart-3:traffic-post-views' ttl=31536000 %}<i data-lucide="bar-chart-3" class="size-3" aria-hidden="true"></i>{% endcache %}
                <?= e($t($viewCount === 1 ? 'analytics.viewsOne' : 'analytics.viewsMany', ['count' => number_format($viewCount)])) ?>
            </a>
            <?php } ?>
            <?php if (!empty($post['tags'])) { ?>
                <?php foreach (array_slice($post['tags'], 0, 3) as $tg) { ?>
                <a href="<?= e(lurl('/dashboard/post?tag='.(int) $tg['id'])) ?>"
                   class="inline-flex items-center gap-1 hover:text-custom-500 transition-colors">
                    {% cache 'lucide:tag:sm' ttl=31536000 %}<i data-lucide="tag" class="size-3"></i>{% endcache %}
                    <?= e((string) $tg['name']) ?>
                </a>
                <?php } ?>
            <?php } ?>
        </div>

        <div class="flex items-center gap-2 mt-4 pt-3 border-t border-slate-100 dark:border-zink-600">
            <?php if (!$can['update']) { ?>
            <span class="inline-flex items-center gap-1.5 text-xs text-slate-500 dark:text-zink-300">
                {% cache 'lucide:lock:sm' ttl=31536000 %}<i data-lucide="lock" class="size-3"></i>{% endcache %}
                <?= e($t('components.postCard.locked')) ?>
            </span>
            <?php } ?>

            <!-- Secondary actions live in one menu so cards stay readable on small screens. -->
            <div class="ms-auto">
                {% cmp="post-actions" post="{$post}" blogSlug="{$blogSlug}" returnToken="{$returnToken}" %}
            </div>
        </div>
    </div>
</div>
