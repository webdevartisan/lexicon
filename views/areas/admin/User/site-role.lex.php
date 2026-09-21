{% extends "back.lex.php" %}

{% block title %}Change Site Role{% endblock %}
{% block subtitle %}The account-wide role that opens control panel areas. Blog roles are separate.{% endblock %}

{% block body %}
<?php
$userId = (int) $user['id'];
$showUrl = '/admin/users/'.$userId;
$radioClass = 'form-radio border-slate-300 dark:border-zink-500 text-custom-500 focus:ring-custom-500';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-xl">
    {% include "areas/admin/_errors.lex.php" %}

    <?php if (!$canAssign) { ?>
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-slate-500 dark:text-zink-300 mb-3">Only administrators can change site roles, because Administrator is one of them.</p>
            <a href="<?= e($showUrl) ?>" class="text-sm text-custom-500 hover:underline">Back to the account</a>
        </div>
    </div>
    <?php } else { ?>
    <form method="post" action="<?= e($showUrl) ?>/site-role" class="card">
        {{ csrf_field() }}
        <div class="card-body">
            <fieldset>
                <legend class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1">Site role for @<?= e($user['handle']) ?></legend>
                <p class="text-xs text-slate-400 dark:text-zink-400 mb-4">One role per account. Higher roles are listed first.</p>
                <div class="flex flex-col gap-3">
                    <?php foreach ($roles as $siteRole) {
                        $roleId = (int) $siteRole['id'];
                        $inputId = 'site-role-'.$roleId; ?>
                    <div class="flex items-start gap-3">
                        <input type="radio" id="<?= $inputId ?>" name="role_id" value="<?= $roleId ?>" class="<?= $radioClass ?> mt-1"
                            <?= in_array($siteRole['role_slug'], $currentSlugs, true) ? 'checked' : '' ?>
                            aria-describedby="<?= $inputId ?>-desc">
                        <label for="<?= $inputId ?>" class="text-sm cursor-pointer">
                            <span class="font-medium text-slate-900 dark:text-zink-50"><?= e($siteRole['role_name']) ?></span>
                            <span id="<?= $inputId ?>-desc" class="block text-xs text-slate-500 dark:text-zink-300"><?= e((string) ($siteRole['description'] ?? '')) ?></span>
                        </label>
                    </div>
                    <?php } ?>
                </div>
            </fieldset>
        </div>
        {% cmp="form-footer" cancelHref="{$showUrl}" submitLabel="Save site role" submitIcon="shield" %}
    </form>
    <?php } ?>
</div>
{% endblock %}
