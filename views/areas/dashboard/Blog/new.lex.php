{% extends "front.lex.php" %}

{% block title %}<?= e($t('setup.start.title')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<?php
$step = 1;
$fieldErrors = errors();
$addressBase = base_url().'/blog/';
$addressShown = (string) old('slug') !== '' || isset($fieldErrors['slug']);
?>
<section class="lx-wrap start-wrap">
  <div class="acct-page start-page">
    {% include "partials/dashboard/_start_steps.lex.php" %}

    <?php if (!empty($error)) { ?>
    <p class="panel-flash is-error" role="alert"><?= e($error) ?></p>
    <?php } ?>

    <form id="blog-create-form" method="post" action="<?= e(lurl('/start')) ?>" class="acct-form start-step"
          data-start-step data-blog-address="<?= e(lurl('/dashboard/slug-check')) ?>?type=blog">
      <?= csrf_field() ?>
      <div class="acct-field<?= isset($fieldErrors['name']) ? ' is-invalid' : '' ?>">
        <label for="name" class="start-question"><?= e($t('setup.start.name.question')) ?></label>
        <input id="name" name="name" type="text" required minlength="2" maxlength="50" autocomplete="off" autofocus
          value="<?= e((string) old('name')) ?>" aria-describedby="name-hint" class="acct-input start-input">
        <p id="name-hint" class="acct-hint"><?= e($t('setup.start.name.hint')) ?></p>
        <?php foreach ($fieldErrors['name'] ?? [] as $message) { ?>
        <p class="acct-error"><?= e($message) ?></p>
        <?php } ?>
      </div>

      <div class="start-address" data-address-box<?= $addressShown ? '' : ' hidden' ?>>
        <p class="acct-hint" data-address hidden>
          <?= e($t('setup.start.address.label')) ?>
          <span dir="ltr" class="start-address-url"><?= e($addressBase) ?><strong data-address-slug></strong></span>
        </p>
        <details class="start-address-change" data-address-change<?= isset($fieldErrors['slug']) ? ' open' : '' ?>>
          <summary><?= e($t('setup.start.address.change')) ?></summary>
          <div class="acct-field<?= isset($fieldErrors['slug']) ? ' is-invalid' : '' ?>">
            <label for="slug" class="acct-sr-only"><?= e($t('setup.start.address.field')) ?></label>
            <div class="start-slug" dir="ltr" data-slug-row>
              <span class="start-slug-base"><?= e($addressBase) ?></span>
              <input id="slug" name="slug" type="text" maxlength="50" autocomplete="off" spellcheck="false"
                value="<?= e((string) old('slug')) ?>" class="acct-input">
            </div>
            <?php foreach ($fieldErrors['slug'] ?? [] as $message) { ?>
            <p id="slug_error" class="acct-error"><?= e($message) ?></p>
            <?php } ?>
            <p class="acct-hint"><?= e($t('setup.start.address.fixed')) ?></p>
          </div>
        </details>
      </div>

      <?php if (!empty($needsName)) { ?>
      <fieldset class="start-fieldset">
        <legend class="start-question"><?= e($t('setup.start.writer.question')) ?></legend>
        <div class="acct-grid">
          <?php foreach (['first_name' => ['setup.start.writer.first', 'given-name'], 'last_name' => ['setup.start.writer.last', 'family-name']] as $nameField => [$nameLabel, $nameComplete]) { ?>
          <div class="acct-field<?= isset($fieldErrors[$nameField]) ? ' is-invalid' : '' ?>">
            <label for="<?= $nameField ?>" class="acct-sr-only"><?= e($t($nameLabel)) ?></label>
            <input id="<?= $nameField ?>" name="<?= $nameField ?>" type="text" required minlength="2" maxlength="50" autocomplete="<?= $nameComplete ?>"
              value="<?= e((string) old($nameField)) ?>" placeholder="<?= e($t($nameLabel)) ?>" class="acct-input">
            <?php foreach ($fieldErrors[$nameField] ?? [] as $message) { ?>
            <p class="acct-error"><?= e($message) ?></p>
            <?php } ?>
          </div>
          <?php } ?>
        </div>
        <p class="acct-hint"><?= e($t('setup.start.writer.hint')) ?></p>
      </fieldset>
      <?php } ?>

      <div class="acct-actions">
        <button type="submit" class="acct-btn acct-btn-primary"><?= e($t('setup.start.continue')) ?></button>
      </div>
    </form>
  </div>
</section>
{% endblock %}

{% block scripts %}
<script src="<?= e(versioned_asset('/cp-assets/js/slug-field.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/blog-start.js')) ?>"></script>
{% endblock %}
