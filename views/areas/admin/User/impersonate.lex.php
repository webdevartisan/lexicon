{% extends "back.lex.php" %}

{% block title %}Log In As{% endblock %}
{% block subtitle %}Use their account as they would, to reproduce a problem they reported.{% endblock %}

{% block body %}
<?php
$userId = (int) $user['id'];
$handle = '@'.$user['handle'];
$showUrl = '/admin/users/'.$userId;
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-xl">
    {% include "areas/admin/_errors.lex.php" %}

    <?php if ($refusal !== null) { ?>
    <div class="card">
        <div class="card-body">
            <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-2">You cannot sign in as <?= e($handle) ?></h3>
            <p class="text-sm text-slate-500 dark:text-zink-300 mb-3"><?= e($refusal) ?></p>
            <a href="<?= e($showUrl) ?>" class="text-sm text-custom-500 hover:underline">Back to the account</a>
        </div>
    </div>
    <?php } else { ?>
    <form method="post" action="<?= e($showUrl) ?>/impersonate" class="card">
        {{ csrf_field() }}
        <div class="card-body">
            <div class="text-center py-2 mb-4">
                <i data-lucide="log-in" class="size-12 text-yellow-500 mx-auto mb-3" aria-hidden="true"></i>
                <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1">Sign in as <?= e($handle) ?>?</h3>
            </div>
            <ul class="text-sm text-slate-600 dark:text-zink-300 list-disc ltr:pl-5 rtl:pr-5 mb-4 flex flex-col gap-1">
                <li>You act with their full account. Anything you post, comment, change or delete is saved as them.</li>
                <li>Every one of those actions is also recorded against you in the audit log, and so is the start and end of this session.</li>
                <li>A banner stays on every page until you return. The session ends by itself after <?= (int) $maxMinutes ?> minutes.</li>
                <li>You will not see the control panel while you are them. Use the banner to come back.</li>
            </ul>
            {% cmp="input" type="text" label="Reason" name="reason" required underlabel="Kept in the audit trail, e.g. a support ticket number." %}
        </div>
        {% cmp="form-footer" cancelHref="{$showUrl}" submitLabel="Sign in as them" submitIcon="log-in" danger %}
    </form>
    <?php } ?>
</div>
{% endblock %}
