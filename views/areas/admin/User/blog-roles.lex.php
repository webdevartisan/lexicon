{% extends "back.lex.php" %}

{% block title %}Change Blog Roles{% endblock %}
{% block subtitle %}Their role on each blog they belong to. Each row saves on its own.{% endblock %}

{% block body %}
<?php
$userId = (int) $user['id'];
$showUrl = '/admin/users/'.$userId;
$th = 'px-3.5 py-2.5 font-semibold';
$td = 'px-3.5 py-2.5';
$selectClass = 'form-select border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 py-1.5 text-sm';
$smallBtn = 'btn py-1.5 px-3 text-sm';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-4xl">
    {% include "areas/admin/_errors.lex.php" %}

    <div class="card">
        <div class="card-body p-0">
            <?php if ($blogs === []) { ?>
            <div class="p-5">
                <p class="text-sm text-slate-500 dark:text-zink-300">@<?= e($user['handle']) ?> is not a member of any blog.</p>
            </div>
            <?php } else { ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <caption class="sr-only">Blogs @<?= e($user['handle']) ?> belongs to, with their role on each</caption>
                    <thead class="text-left bg-slate-100 dark:bg-zink-600 text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200">
                        <tr>
                            <th scope="col" class="<?= $th ?>">Blog</th>
                            <th scope="col" class="<?= $th ?>">Role</th>
                            <th scope="col" class="<?= $th ?> text-right">Remove</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-zink-600 text-sm">
                        <?php foreach ($blogs as $blogRow) {
                            $blogId = (int) $blogRow['id'];
                            $blogName = (string) $blogRow['blog_name'];
                            $isOwner = $blogRow['user_role'] === 'owner';
                            $selectId = 'blog-role-'.$blogId; ?>
                        <tr>
                            <th scope="row" class="<?= $td ?> font-medium text-left">
                                <a class="text-custom-500 hover:underline" href="/admin/blogs/<?= $blogId ?>/show"><?= e($blogName) ?></a>
                            </th>
                            <td class="<?= $td ?>">
                                <?php if ($isOwner) { ?>
                                <span>Owner</span>
                                <span class="block text-xs text-slate-400 dark:text-zink-400">Change it by transferring ownership <a class="text-custom-500 hover:underline" href="/admin/blogs/<?= $blogId ?>/edit">on the blog's page</a>. A blog always has exactly one owner.</span>
                                <?php } else { ?>
                                <form method="post" action="<?= e($showUrl) ?>/blog-roles/<?= $blogId ?>" class="flex flex-wrap items-center gap-2">
                                    {{ csrf_field() }}
                                    <label for="<?= $selectId ?>" class="sr-only">Role on <?= e($blogName) ?></label>
                                    <select id="<?= $selectId ?>" name="role" class="<?= $selectClass ?>">
                                        <?php foreach ($roleOptions as $roleOption) { ?>
                                        <option value="<?= e($roleOption) ?>" <?= $roleOption === $blogRow['user_role'] ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $roleOption))) ?></option>
                                        <?php } ?>
                                    </select>
                                    <button type="submit" class="<?= $smallBtn ?> text-white bg-custom-500 border-custom-500 hover:bg-custom-600">Save<span class="sr-only"> role on <?= e($blogName) ?></span></button>
                                </form>
                                <?php } ?>
                            </td>
                            <td class="<?= $td ?> text-right">
                                <?php if (!$isOwner) { ?>
                                <form method="post" action="<?= e($showUrl) ?>/blog-roles/<?= $blogId ?>/remove" class="inline">
                                    {{ csrf_field() }}
                                    <button type="submit" class="<?= $smallBtn ?> text-red-500 bg-white border-red-500 hover:text-white hover:bg-red-600 dark:bg-zink-700"
                                        data-confirm="<?= e('Remove @'.$user['handle'].' from '.$blogName.'? They lose access to it straight away.') ?>">
                                        Remove<span class="sr-only"> from <?= e($blogName) ?></span>
                                    </button>
                                </form>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
        </div>
    </div>

    <p class="mt-4"><a href="<?= e($showUrl) ?>" class="text-sm text-custom-500 hover:underline">Back to the account</a></p>
</div>
{% endblock %}
