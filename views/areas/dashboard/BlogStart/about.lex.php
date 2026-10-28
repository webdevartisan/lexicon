{% extends "front.lex.php" %}

{% block title %}<?= e($t('setup.start.title')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<?php
$fieldErrors = errors();
$description = (string) (old('description') ?: $blog['description'] ?? '');
?>
<section class="lx-wrap start-wrap">
  <div class="acct-page start-page">
    {% include "partials/dashboard/_start_steps.lex.php" %}

    <form method="post" action="<?= e(lurl('/start/'.(int) $blog['id'].'/about')) ?>" class="acct-form start-step" data-start-step>
      <?= csrf_field() ?>
      <div class="acct-field<?= isset($fieldErrors['description']) ? ' is-invalid' : '' ?>">
        <label for="description" class="start-question"><?= e($t('setup.start.about.question')) ?></label>
        <input id="description" name="description" type="text" maxlength="160" autocomplete="off" autofocus
          value="<?= e($description) ?>" aria-describedby="description-hint" class="acct-input start-input">
        <p id="description-hint" class="acct-hint"><?= e($t('setup.start.about.hint')) ?></p>
        <?php foreach ($fieldErrors['description'] ?? [] as $message) { ?>
        <p class="acct-error"><?= e($message) ?></p>
        <?php } ?>
      </div>
      <div class="acct-actions">
        <button type="submit" name="intent" value="skip" formnovalidate class="acct-btn"><?= e($t('setup.start.skip')) ?></button>
        <button type="submit" name="intent" value="save" class="acct-btn acct-btn-primary"><?= e($t('setup.start.continue')) ?></button>
      </div>
    </form>
  </div>
</section>
{% endblock %}
