{% extends "back.lex.php" %}

{% block title %}{{ post.title }}{% endblock %}
{% block subtitle %}{% endblock %}
{% block body %}
<?php
$notice = match ($lock) {
    'in_review' => [
        'icon' => 'eye',
        'heading' => 'A reviewer is reading this post',
        'text' => 'It stays as you submitted it while they review it. To change something, withdraw it: it goes back to your drafts and leaves the review queue, and you can submit it again when you are done.',
    ],
    'approved' => [
        'icon' => 'check',
        'heading' => 'Approved and waiting to be published',
        'text' => 'An editor will publish it. If you withdraw it to make changes, it needs a new review.',
    ],
    default => [
        'icon' => 'lock',
        'heading' => $post['status'] === 'published' ? 'This post is live' : 'This post is no longer a draft',
        'text' => 'Changes to it go through an editor of this blog, so ask one of them if something needs fixing.',
    ],
};
?>
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <section class="bg-sky-50 border border-sky-200 rounded-lg dark:bg-sky-500/10 dark:border-sky-500/30">
                <div class="p-4 flex items-start gap-3">
                    <i data-lucide="<?= e($notice['icon']) ?>" class="size-5 text-sky-600 dark:text-sky-300 shrink-0 mt-0.5"></i>
                    <div class="space-y-1">
                        <h2 class="text-sm font-semibold text-sky-900 dark:text-sky-100"><?= e($notice['heading']) ?></h2>
                        <p class="text-sm text-sky-800 dark:text-sky-200"><?= e($notice['text']) ?></p>
                    </div>
                </div>
            </section>

            <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
                <div class="p-4 border-b border-slate-200 dark:border-zink-600">
                    <h1 class="text-base font-semibold text-slate-900 dark:text-zink-100">{{ post.title }}</h1>
                    <?php if (!empty($post['excerpt'])) { ?>
                    <p class="mt-1 text-sm text-slate-500 dark:text-zink-300">{{ post.excerpt }}</p>
                    <?php } ?>
                </div>
                <div class="p-4 md:p-5 space-y-4 text-slate-700 dark:text-zink-200">
                    {{ post.content|raw }}
                </div>
            </section>
        </div>

        <div class="space-y-6">
            <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
                <div class="p-4 space-y-2">
                    <?php if ($canWithdraw) { ?>
                    <form method="post" action="/dashboard/posts/<?= (int) $post['id'] ?>/workflow/reset" class="m-0">
                        {{ csrf_field() }}
                        {% cmp="btn" type="submit" variant="blue" icon="undo-2" label="Withdraw to draft" %}
                    </form>
                    <?php } ?>

                    <?php if ($post['status'] === 'published') { ?>
                    {% cmp="btn" href="{$postUrl}" variant="slate" icon="external-link" label="View post" %}
                    <?php } ?>

                    {% cmp="btn" href="{$backUrl}" variant="slate" icon="step-back" label="Back to posts" %}
                </div>
            </section>
        </div>
    </div>
</div>
{% endblock %}
