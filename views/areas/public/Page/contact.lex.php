{% extends "front.lex.php" %}

{% block title %}{{ page.title }} | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
{% if page.meta_description %}
<meta name="description" content="{{ page.meta_description }}" />
{% endif %}
{% endblock %}

{% block body %}
<?php
$flashMessages = flash();
$formErrors = errors();
?>
<section>
    <header class="main">
        <h1>{{ page.title }}</h1>
    </header>

    <div class="page-content">
        {{ page.content|raw }}
    </div>

    {% if (!empty($flashMessages['success'])): %}
        {% foreach ($flashMessages['success'] as $msg): %}
        <p class="search-meta"><strong>{{ msg }}</strong></p>
        {% endforeach; %}
    {% endif %}
    {% if (!empty($flashMessages['error'])): %}
        {% foreach ($flashMessages['error'] as $msg): %}
        <p class="search-meta"><strong>{{ msg }}</strong></p>
        {% endforeach; %}
    {% endif %}

    <form method="POST" action="/contact">
        {{ csrf_field() }}

        <!-- Honeypot: humans never see this field, bots fill it -->
        <div style="position:absolute;left:-9999px;" aria-hidden="true">
            <label>Website
                <input type="text" name="website" tabindex="-1" autocomplete="off" />
            </label>
        </div>

        <div class="row gtr-uniform">
            <div class="col-6 col-12-xsmall">
                <input type="text" name="name" placeholder="<?= e($t('pages.contactForm.name')) ?>"
                       value="<?= e(old('name', '')) ?>" required />
                <?php if (!empty($formErrors['name'])) { ?>
                    <p class="search-meta"><?= e(is_array($formErrors['name']) ? implode(' ', $formErrors['name']) : $formErrors['name']) ?></p>
                <?php } ?>
            </div>
            <div class="col-6 col-12-xsmall">
                <input type="email" name="email" placeholder="<?= e($t('pages.contactForm.email')) ?>"
                       value="<?= e(old('email', '')) ?>" required />
                <?php if (!empty($formErrors['email'])) { ?>
                    <p class="search-meta"><?= e(is_array($formErrors['email']) ? implode(' ', $formErrors['email']) : $formErrors['email']) ?></p>
                <?php } ?>
            </div>
            <div class="col-12">
                <input type="text" name="subject" placeholder="<?= e($t('pages.contactForm.subject')) ?>"
                       value="<?= e(old('subject', '')) ?>" required />
                <?php if (!empty($formErrors['subject'])) { ?>
                    <p class="search-meta"><?= e(is_array($formErrors['subject']) ? implode(' ', $formErrors['subject']) : $formErrors['subject']) ?></p>
                <?php } ?>
            </div>
            <div class="col-12">
                <textarea name="message" placeholder="<?= e($t('pages.contactForm.message')) ?>" rows="6" required><?= e(old('message', '')) ?></textarea>
                <?php if (!empty($formErrors['message'])) { ?>
                    <p class="search-meta"><?= e(is_array($formErrors['message']) ? implode(' ', $formErrors['message']) : $formErrors['message']) ?></p>
                <?php } ?>
            </div>
            <div class="col-12">
                <ul class="actions">
                    <li><button type="submit" class="button primary"><?= e($t('pages.contactForm.send')) ?></button></li>
                </ul>
            </div>
        </div>
    </form>
</section>
{% endblock %}
