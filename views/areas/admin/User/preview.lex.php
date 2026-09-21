{% extends "back.lex.php" %}

{% block title %}Public Profile Preview{% endblock %}
{% block subtitle %}What a visitor gets at /profile/<?= e($user['handle']) ?>.{% endblock %}

{% block body %}
<?php $showUrl = '/admin/users/'.(int) $user['id']; ?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-xl">
    <div class="card">
        <div class="card-body">
            <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-2">Visitors see a “not found” page</h3>
            <p class="text-sm text-slate-500 dark:text-zink-300 mb-3">
                The public page answers every visitor with the same “not found”, whether the profile is private or does not exist,
                so nothing about the account leaks. This is why, for @<?= e($user['handle']) ?>:
            </p>
            <ul class="text-sm text-slate-600 dark:text-zink-300 list-disc ltr:pl-5 rtl:pr-5 mb-4">
                <?php foreach ($visibility['reasons'] as $reason) { ?>
                <li><?= e($reason) ?></li>
                <?php } ?>
            </ul>
            <p class="text-xs text-slate-400 dark:text-zink-400 mb-3">
                This is the same check the public page runs, not a copy of it. There is no admin bypass to view a hidden profile, so nothing here can show you something visitors cannot see.
            </p>
            <a href="<?= e($showUrl) ?>" class="text-sm text-custom-500 hover:underline">Back to the account</a>
        </div>
    </div>
</div>
{% endblock %}
