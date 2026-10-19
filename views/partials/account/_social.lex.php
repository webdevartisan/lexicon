<?php
// Social links, shared by the /account/social page and the account dialog.
?>
{% include "partials/account/_field.lex.php" %}
{% include "partials/account/_flash.lex.php" %}

<div class="acct-head">
    <h2 class="acct-title"><?= e($t('account.profile.socialSection')) ?></h2>
</div>

<form method="post" action="<?= e(lurl('/account/profile/update')) ?>" class="acct-form">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="social">
    <div class="acct-grid">
        <?php $field('website', $t('account.profile.website'), 'url', (string) ($user['website'] ?? '')); ?>
        <?php $field('twitter', $t('account.profile.twitter'), 'url', (string) ($user['twitter'] ?? '')); ?>
        <?php $field('instagram', $t('account.profile.instagram'), 'url', (string) ($user['instagram'] ?? '')); ?>
        <?php $field('linkedin', $t('account.profile.linkedin'), 'url', (string) ($user['linkedin'] ?? '')); ?>
        <?php $field('github', $t('account.profile.github'), 'url', (string) ($user['github'] ?? '')); ?>
    </div>

    <div class="acct-actions">
        <button type="submit" class="acct-btn acct-btn-primary"><?= e($t('account.profile.save')) ?></button>
    </div>
</form>

