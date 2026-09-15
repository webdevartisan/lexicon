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

$fieldError = static function ($errors) {
    if (empty($errors)) {
        return null;
    }
    return is_array($errors) ? implode(' ', $errors) : $errors;
};
?>
<section aria-labelledby="contact-heading">
    <header class="lx-section-head">
        <h1 id="contact-heading">{{ page.title }}</h1>
    </header>

    <?php if (!empty(trim((string) ($page->content ?? '')))) { ?>
        <div class="lx-page-content">{{ page.content|raw }}</div>
    <?php } ?>

    <?php foreach (($flashMessages['success'] ?? []) as $msg) { ?>
        <p class="lx-msg lx-msg-success" role="status"><?= e($msg); ?></p>
    <?php } ?>
    <?php foreach (($flashMessages['error'] ?? []) as $msg) { ?>
        <p class="lx-msg lx-msg-error" role="alert"><?= e($msg); ?></p>
    <?php } ?>

    <form method="POST" action="/contact" novalidate>
        {{ csrf_field() }}

        <div class="lx-hp" aria-hidden="true">
            <label>Website
                <input type="text" name="website" tabindex="-1" autocomplete="off" />
            </label>
        </div>

        <div class="lx-grid-2">
            <div class="lx-field<?= !empty($formErrors['name']) ? ' lx-field-invalid' : ''; ?>">
                <label class="lx-field-label" for="contact-name"><?= e($t('pages.contactForm.name')) ?></label>
                <input class="lx-field-input" id="contact-name" type="text" name="name"
                       value="<?= e(old('name', '')) ?>" required autocomplete="name" />
                <?php if ($err = $fieldError($formErrors['name'] ?? null)) { ?>
                    <p class="lx-field-error"><?= e($err); ?></p>
                <?php } ?>
            </div>

            <div class="lx-field<?= !empty($formErrors['email']) ? ' lx-field-invalid' : ''; ?>">
                <label class="lx-field-label" for="contact-email"><?= e($t('pages.contactForm.email')) ?></label>
                <input class="lx-field-input" id="contact-email" type="email" name="email"
                       value="<?= e(old('email', '')) ?>" required autocomplete="email" />
                <?php if ($err = $fieldError($formErrors['email'] ?? null)) { ?>
                    <p class="lx-field-error"><?= e($err); ?></p>
                <?php } ?>
            </div>
        </div>

        <div class="lx-field<?= !empty($formErrors['subject']) ? ' lx-field-invalid' : ''; ?>">
            <label class="lx-field-label" for="contact-subject"><?= e($t('pages.contactForm.subject')) ?></label>
            <input class="lx-field-input" id="contact-subject" type="text" name="subject"
                   value="<?= e(old('subject', '')) ?>" required />
            <?php if ($err = $fieldError($formErrors['subject'] ?? null)) { ?>
                <p class="lx-field-error"><?= e($err); ?></p>
            <?php } ?>
        </div>

        <div class="lx-field<?= !empty($formErrors['message']) ? ' lx-field-invalid' : ''; ?>">
            <label class="lx-field-label" for="contact-message"><?= e($t('pages.contactForm.message')) ?></label>
            <textarea class="lx-field-input" id="contact-message" name="message" rows="6" required><?= e(old('message', '')) ?></textarea>
            <?php if ($err = $fieldError($formErrors['message'] ?? null)) { ?>
                <p class="lx-field-error"><?= e($err); ?></p>
            <?php } ?>
        </div>

        <div class="lx-form-actions">
            <button type="submit" class="lx-btn lx-btn-primary"><?= e($t('pages.contactForm.send')) ?></button>
        </div>
    </form>
</section>
{% endblock %}
