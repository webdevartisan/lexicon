{% extends "back.lex.php" %}

{% block title %}Reset Password{% endblock %}
{% block subtitle %}Send them a reset link, or set a password yourself if they cannot receive email.{% endblock %}

{% block body %}
<?php
$userId = (int) $user['id'];
$handle = '@'.$user['handle'];
$showUrl = '/admin/users/'.$userId;
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-xl">
    {% include "areas/admin/_errors.lex.php" %}

    <?php if ($isSelf) { ?>
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-slate-500 dark:text-zink-300">This is your own account. Change your password from your account security page, which asks for the current one first.</p>
            <a href="<?= e(lurl('/account/security')) ?>" class="text-sm text-custom-500 hover:underline">Go to account security</a>
        </div>
    </div>
    <?php } else { ?>

    <form method="post" action="<?= e($showUrl) ?>/password/send-reset" class="card">
        {{ csrf_field() }}
        <div class="card-body">
            <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1">Send a reset link <span class="text-xs font-normal text-slate-400 dark:text-zink-400">(recommended)</span></h3>
            <p class="text-sm text-slate-500 dark:text-zink-300">
                <?= e($handle) ?> gets the same link the forgot-password form sends, at <?= e($user['email']) ?>. It works for an hour and nobody but them ever sees the new password.
            </p>
        </div>
        {% cmp="form-footer" cancelHref="{$showUrl}" submitLabel="Send reset link" submitIcon="mail" %}
    </form>

    <form method="post" action="<?= e($showUrl) ?>/password/set" class="card mt-5" autocomplete="off">
        {{ csrf_field() }}
        <div class="card-body">
            <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1">Set a password yourself</h3>
            <p class="text-sm text-slate-500 dark:text-zink-300 mb-4">
                Only when they cannot receive email. You will know their password, so tell them to change it straight away.
                Saving signs <?= e($handle) ?> out on every device and cancels any reset link they already have.
            </p>
            <div class="grid grid-cols-1 gap-5">
                {% cmp="input" type="password" label="New password" name="password" required underlabel="Same rules as sign-up." %}
                {% cmp="input" type="password" label="Repeat the password" name="confirm_password" required %}
            </div>
        </div>
        {% cmp="form-footer" cancelHref="{$showUrl}" submitLabel="Set password and sign them out" submitIcon="key-round" danger %}
    </form>
    <?php } ?>
</div>
{% endblock %}
