{% extends "auth.lex.php" %}

{% block title %}<?= e($t('auth.inviteJoinedTitle')) ?>{% endblock %}

{% block heading %}<?= e($t('auth.inviteJoinedTitle')) ?>{% endblock %}

{% block sub %}<?= e($t('auth.inviteJoinedSub')) ?>{% endblock %}

{% block body %}
<?php $inviteStep = 4; ?>
<div class="lx-invite">
    {% include "partials/_invite_steps.lex.php" %}

    <section class="lx-invite-panel">
        <div class="lx-invite-body lx-invite-done">
            <span class="lx-invite-done-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
            </span>
            <h2 class="lx-invite-title"><?= e($t('auth.inviteJoinedBody', ['blog' => $blogName, 'role' => $roleLabel])) ?></h2>

            <dl class="lx-invite-facts">
                <div>
                    <dt><?= e($t('auth.inviteFactBlog')) ?></dt>
                    <dd><?= e($blogName) ?></dd>
                </div>
                <div>
                    <dt><?= e($t('auth.inviteFactRole')) ?></dt>
                    <dd><?= e($roleLabel) ?></dd>
                </div>
                <div>
                    <dt><?= e($t('auth.inviteFactSignedInAs')) ?></dt>
                    <dd><?= e($accountEmail) ?></dd>
                </div>
            </dl>
        </div>

        <footer class="lx-invite-foot lx-invite-foot-end">
            <a class="lx-btn lx-btn-primary lx-invite-primary" href="<?= e(lurl('/dashboard')) ?>"><?= e($t('auth.inviteJoinedCta')) ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </footer>
    </section>
</div>
{% endblock %}
