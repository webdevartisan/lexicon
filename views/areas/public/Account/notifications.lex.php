{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.notifications.heading')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<section class="lx-wrap lx-account">
    <?php $accountSection = 'notifications'; ?>
    {% include "partials/_account_shell.lex.php" %}

    <div class="acct-page">
        {% include "partials/account/_notifications.lex.php" %}
    </div>
</section>
{% endblock %}
