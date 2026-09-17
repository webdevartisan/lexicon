{% extends "back.lex.php" %}

{% block title %}Delete User{% endblock %}
{% block subtitle %}This action is permanent and cannot be undone.{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-xl">
    <?php if (!$canDelete) { ?>
    <div class="card">
        <div class="card-body">
            <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-2"><?= e($user['handle']) ?> cannot be deleted yet</h3>
            <?php if ($blockers['last_administrator']) { ?>
            <p class="text-sm text-slate-500 dark:text-zink-300 mb-2">This is the only administrator. Give someone else the Administrator role first.</p>
            <?php } ?>
            <?php if ($blockers['reported_content']) { ?>
            <p class="text-sm text-slate-500 dark:text-zink-300 mb-2">Some of what they wrote has an open report. Resolve it on the post or comment itself first.</p>
            <?php } ?>
            <?php if ($blockers['shared_blogs'] !== []) { ?>
            <p class="text-sm text-slate-500 dark:text-zink-300 mb-2">They own blogs that other people write in. Transfer each one to a collaborator, or delete it, first:</p>
            <ul class="text-sm list-disc ltr:pl-5 rtl:pr-5 mb-2">
                <?php foreach ($blockers['shared_blogs'] as $sharedBlog) { ?>
                <li><a class="text-custom-500 hover:underline" href="/admin/blogs/<?= (int) $sharedBlog['id'] ?>/edit"><?= e($sharedBlog['blog_name']) ?></a></li>
                <?php } ?>
            </ul>
            <?php } ?>
            <a href="/admin/users" class="text-sm text-custom-500 hover:underline">Back to users</a>
        </div>
    </div>
    <?php } else { ?>
    <form method="post" action="/admin/users/<?= e((string) $user['id']) ?>/destroy" class="card">
        {{ csrf_field() }}
        <div class="card-body">
            <div class="text-center py-4">
                <i data-lucide="alert-triangle" class="size-12 text-red-500 mx-auto mb-3"></i>
                <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1">
                    Delete <?= e($user['handle']) ?>?
                </h3>
                <p class="text-sm text-slate-500 dark:text-zink-300">
                    The account, profile, settings, notifications, votes, bookmarks and subscriptions are deleted, and they are signed out everywhere.
                </p>
                <p class="text-sm text-slate-500 dark:text-zink-300 mt-2">
                    Their posts and blogs are permanently erased, including anything anyone else wrote on them. A comment someone replied to is emptied instead of removed, so the conversation still makes sense.
                </p>
            </div>
        </div>
        {% cmp="form-footer" cancelHref="/admin/users" submitLabel="Yes, delete user" submitIcon="trash-2" danger %}
    </form>
    <?php } ?>
</div>
{% endblock %}
