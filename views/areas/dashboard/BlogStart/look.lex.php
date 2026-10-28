{% extends "front.lex.php" %}

{% block title %}<?= e($t('setup.start.title')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<section class="lx-wrap start-wrap">
  <div class="acct-page start-page">
    {% include "partials/dashboard/_start_steps.lex.php" %}

    <form method="post" action="<?= e(lurl('/start/'.(int) $blog['id'].'/look')) ?>" class="acct-form start-step" data-start-step>
      <?= csrf_field() ?>
      <fieldset class="start-fieldset">
        <legend class="start-question"><?= e($t('setup.start.look.question')) ?></legend>
        <p class="acct-hint"><?= e($t('setup.start.look.hint')) ?></p>

        <div class="start-themes">
          <?php foreach ($themes as $theme) {
              $themeKey = (string) $theme['key'];
              $inputId = 'theme-'.$themeKey;
              ?>
          <label for="<?= e($inputId) ?>" class="start-theme" data-theme-card="<?= e($themeKey) ?>">
            <input type="radio" name="theme" id="<?= e($inputId) ?>" value="<?= e($themeKey) ?>" class="acct-sr-only"<?= $themeKey === $currentTheme ? ' checked' : '' ?>>
            <span class="start-theme-card">
              <?php if (!empty($theme['screenshot'])) { ?>
              <img src="<?= e($theme['screenshot']) ?>" alt="" loading="lazy" width="640" height="400" class="start-theme-shot">
              <?php } else { ?>
              <span class="start-theme-shot"></span>
              <?php } ?>
              <span class="start-theme-text">
                <span class="start-theme-name" dir="auto"><?= e($theme['name']) ?></span>
                <span class="acct-hint" dir="auto"><?= e($theme['description']) ?></span>
              </span>
            </span>
            <span class="start-theme-check" aria-hidden="true">
              <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
            </span>
          </label>
          <?php } ?>
        </div>
      </fieldset>
      <div class="acct-actions">
        <button type="submit" name="intent" value="skip" formnovalidate class="acct-btn"><?= e($t('setup.start.skip')) ?></button>
        <button type="submit" name="intent" value="save" class="acct-btn acct-btn-primary"><?= e($t('setup.start.continue')) ?></button>
      </div>
    </form>
  </div>
</section>
{% endblock %}
