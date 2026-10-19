{% extends "auth.lex.php" %}

{% block title %}<?= e($t('auth.inviteWizardTitle')) ?>{% endblock %}

{% block heading %}<?= e($t('auth.inviteWizardTitle')) ?>{% endblock %}

{% block sub %}<?= e($t('auth.inviteWizardSub')) ?>{% endblock %}

{% block body %}
<?php
$fieldErrors = errors();
// A failed sign-up comes back here; reopen the form instead of the intro.
$startStep = empty($fieldErrors) ? 1 : 2;
$inviteStep = $startStep;
$inviteStepsInteractive = true;
$arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
$arrowBack = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>';
?>
<div class="lx-invite" data-wizard data-start="<?= $startStep ?>">
    {% include "partials/_invite_steps.lex.php" %}

    <section class="lx-invite-panel" data-wizard-panel="1" tabindex="-1" aria-labelledby="invite-step-1">
        <div class="lx-invite-body">
            <h2 class="lx-invite-title" id="invite-step-1"><?= e($blogName) ?></h2>
            <p class="lx-invite-lede">
                <?php if ($inviterName !== null) { ?>
                    <?= e($t('auth.inviteInvitedBy', ['inviter' => $inviterName, 'role' => $roleLabel])) ?>
                <?php } else { ?>
                    <?= e($t('auth.inviteInvitedNoInviter', ['role' => $roleLabel])) ?>
                <?php } ?>
            </p>

            <dl class="lx-invite-facts">
                <div>
                    <dt><?= e($t('auth.inviteFactRole')) ?></dt>
                    <dd>
                        <?= e($roleLabel) ?>
                        <?php if ($roleDescription !== '') { ?>
                            <span class="lx-invite-facts-note"><?= e($roleDescription) ?></span>
                        <?php } ?>
                    </dd>
                </div>
                <?php if ($inviterName !== null) { ?>
                    <div>
                        <dt><?= e($t('auth.inviteFactInvitedBy')) ?></dt>
                        <dd><?= e($inviterName) ?></dd>
                    </div>
                <?php } ?>
                <div>
                    <dt><?= e($t('auth.inviteFactSentTo')) ?></dt>
                    <dd><?= e($invitedEmail) ?></dd>
                </div>
            </dl>
        </div>

        <footer class="lx-invite-foot">
            <form method="post" action="<?= e(lurl('/invite/'.$token.'/decline')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="lx-btn lx-btn-quiet"><?= e($t('auth.inviteDecline')) ?></button>
            </form>
            <button type="button" class="lx-btn lx-invite-primary" data-wizard-next><?= e($t('auth.inviteContinue')) ?><?= $arrow ?></button>
        </footer>
    </section>

    <section class="lx-invite-panel" data-wizard-panel="2" tabindex="-1" aria-labelledby="invite-step-2">
        <form action="<?= e(lurl('/invite/'.$token.'/register')) ?>" method="post" autocomplete="on">
            <?= csrf_field() ?>
            <?php // Not shown: the address comes from the invitation. Password managers still need it to file the new password. ?>
            <input type="email" id="email" value="<?= e($invitedEmail) ?>" autocomplete="username" hidden>

            <div class="lx-invite-body">
                <h2 class="lx-invite-title" id="invite-step-2"><?= e($t('auth.inviteAccountHeading')) ?></h2>
                <p class="lx-invite-lede"><?= strtr(e($t('auth.inviteAccountBody')), ['{email}' => '<strong>'.e($invitedEmail).'</strong>']) ?></p>

                <div class="lx-field<?= !empty($fieldErrors['password']) ? ' lx-field-invalid' : '' ?>">
                    <label class="lx-field-label" for="password"><?= e($t('auth.passwordLabel')) ?></label>

                    <div class="lx-field-control">
                        <input class="lx-field-input"
                               type="password"
                               name="password"
                               id="password"
                               autocomplete="new-password"
                               aria-describedby="password_help"
                               minlength="<?= password_length_bounds()['min'] ?>"
                               maxlength="<?= password_length_bounds()['max'] ?>"
                               required>
                        <button type="button"
                                class="lx-field-toggle"
                                data-password-toggle="password"
                                data-label-show="<?= e($t('auth.show')) ?>"
                                data-label-hide="<?= e($t('auth.hide')) ?>"
                                data-aria-show="<?= e($t('auth.showPassword')) ?>"
                                data-aria-hide="<?= e($t('auth.hidePassword')) ?>"
                                aria-label="<?= e($t('auth.showPassword')) ?>"
                                aria-pressed="false"><?= e($t('auth.show')) ?></button>
                    </div>

                    <?php foreach ($fieldErrors['password'] ?? [] as $message) { ?>
                        <p class="lx-field-error"><?= e($message) ?></p>
                    <?php } ?>

                    <p class="lx-field-hint" id="password_help"><?= e($t(password_hint_key(), ['min' => password_length_bounds()['min']])) ?></p>

                    <?php
                    $meterLevels = implode('|', [
                        $t('auth.strengthTooShort'),
                        $t('auth.strengthWeak'),
                        $t('auth.strengthFair'),
                        $t('auth.strengthGood'),
                        $t('auth.strengthStrong'),
                    ]);
?>
                    <div class="lx-meter"
                         data-password-meter="password"
                         data-hint-from="email"
                         data-levels="<?= e($meterLevels) ?>"
                         hidden>
                        <div class="lx-meter-track" aria-hidden="true">
                            <span class="lx-meter-seg"></span>
                            <span class="lx-meter-seg"></span>
                            <span class="lx-meter-seg"></span>
                            <span class="lx-meter-seg"></span>
                        </div>
                        <p class="lx-meter-label" data-meter-label role="status" aria-live="polite"></p>
                    </div>
                </div>

                <div class="lx-field<?= !empty($fieldErrors['age_confirmed']) ? ' lx-field-invalid' : '' ?>">
                    <label class="lx-check">
                        <input type="checkbox" name="age_confirmed" value="1" required>
                        <span class="lx-check-title"><?= e($t('auth.ageConfirm', ['age' => minimum_signup_age()])) ?></span>
                    </label>
                    <?php foreach ($fieldErrors['age_confirmed'] ?? [] as $message) { ?>
                        <p class="lx-field-error"><?= e($message) ?></p>
                    <?php } ?>
                </div>

                <p class="lx-field-hint"><?= strtr(e($t('auth.legalNotice')), [
                    '{terms}' => '<a href="'.e(lurl('/terms')).'">'.e($t('auth.termsLink')).'</a>',
                    '{privacy}' => '<a href="'.e(lurl('/privacy')).'">'.e($t('auth.privacyLink')).'</a>',
                ]) ?></p>
            </div>

            <footer class="lx-invite-foot">
                <button type="button" class="lx-btn lx-invite-back" data-wizard-back><?= $arrowBack ?><?= e($t('auth.inviteBack')) ?></button>
                <button type="submit" class="lx-btn lx-invite-primary"><?= e($t('auth.inviteCreateAndJoin')) ?><?= $arrow ?></button>
            </footer>
        </form>
    </section>
</div>
{% endblock %}
