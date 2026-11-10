{% extends "front.lex.php" %}

{% block title %}{{ page.title }} | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<?= platform_page_meta($page['title'], (string) ($page['meta_description'] ?? ''), $head['canonicalUrl']) ?>
{% endblock %}

{% block body %}
<?php
$formErrors = errors();
$intro = trim((string) ($page['content'] ?? ''));

$fieldError = static function ($errors) {
    if (empty($errors)) {
        return null;
    }
    return is_array($errors) ? implode(' ', $errors) : $errors;
};
?>
<section class="lx-contact" aria-labelledby="contact-heading">
    <div class="lx-contact-imprint">
        <h1 id="contact-heading">{{ page.title }}</h1>

        <?php if ($intro !== '') { ?>
            <div class="lx-page-content">{{ page.content|raw }}</div>
        <?php } else { ?>
            <p class="lx-contact-lede"><?= e($t('contact.body')) ?></p>
        <?php } ?>

        <dl class="lx-contact-facts">
            <?php if ($contactEmail !== '') { ?>
            <div>
                <dt><?= e($t('pages.contactForm.imprintEmail')) ?></dt>
                <dd><a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a></dd>
            </div>
            <?php } ?>
            <div>
                <dt><?= e($t('pages.contactForm.imprintReplies')) ?></dt>
                <dd><?= e($t('pages.contactForm.replyTime')) ?></dd>
            </div>
        </dl>
    </div>

    <div class="lx-contact-body">
        <?php if ($sent) { ?>
        <div class="lx-contact-sent" role="status">
            <h2><?= e($t('pages.contactForm.sentHeading')) ?></h2>
            <p><?= e($t('pages.contactForm.sentBody')) ?></p>
            <a href="<?= e(lurl('/contact')) ?>" class="lx-btn"><?= e($t('pages.contactForm.sendAnother')) ?></a>
        </div>
        <?php } else { ?>
        <form method="POST" action="<?= e(lurl('/contact')) ?>" novalidate>
            {{ csrf_field() }}

            <p class="lx-contact-note"><?= e($t('pages.contactForm.required')) ?></p>

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
                <textarea class="lx-field-input" id="contact-message" name="message" rows="7" required><?= e(old('message', '')) ?></textarea>
                <?php if ($err = $fieldError($formErrors['message'] ?? null)) { ?>
                    <p class="lx-field-error"><?= e($err); ?></p>
                <?php } ?>
            </div>

            <div class="lx-form-actions">
                <button type="submit" class="lx-btn lx-btn-primary"><?= e($t('pages.contactForm.send')) ?></button>
            </div>
        </form>
        <?php } ?>
    </div>
</section>
{% endblock %}
