{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.preferences.heading')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<section class="lx-wrap lx-account">
    <?php $accountSection = 'preferences'; ?>
    {% include "partials/_account_shell.lex.php" %}

    <div class="acct-page">
        {% include "partials/account/_preferences.lex.php" %}
    </div>
</section>
{% endblock %}
