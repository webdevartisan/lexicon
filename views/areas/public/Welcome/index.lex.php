{% extends "front.lex.php" %}

{% block title %}<?= e($t('setup.welcome.title')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<?php
$choices = [
    ['href' => lurl('/dashboard/blog/new'), 'title' => 'setup.welcome.startTitle', 'text' => 'setup.welcome.startText', 'key' => 'start'],
    ['href' => lurl('/').'?welcome=1', 'title' => 'setup.welcome.readTitle', 'text' => 'setup.welcome.readText', 'key' => 'read'],
];
?>
<section class="lx-wrap lx-account-narrow lx-welcome">
    <header class="lx-account-head">
        <h1><?= e($t('setup.welcome.title')) ?></h1>
    </header>

    <ul class="lx-welcome-choices">
        <?php foreach ($choices as $choice) { ?>
        <li>
            <a class="lx-welcome-choice" href="<?= e($choice['href']) ?>" data-welcome-choice="<?= e($choice['key']) ?>">
                <span class="lx-welcome-choice-title"><?= e($t($choice['title'])) ?></span>
                <span class="lx-welcome-choice-text"><?= e($t($choice['text'])) ?></span>
            </a>
        </li>
        <?php } ?>
    </ul>
</section>
{% endblock %}
