{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.profile.heading')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<section class="lx-wrap lx-account">
    <?php $accountSection = 'profile'; ?>
    {% include "partials/_account_shell.lex.php" %}

    <div class="acct-page">
        {% include "partials/account/_profile.lex.php" %}
    </div>
</section>
{% endblock %}

{% block scripts %}
<script src="/assets/js/account-profile.js" defer></script>
{% endblock %}
