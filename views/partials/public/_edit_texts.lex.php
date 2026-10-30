<?php if (!empty($edit_texts) && empty($edit_in_place)) { ?>
<link rel="stylesheet" href="<?= e(versioned_asset('/assets/css/edit-in-place.css')) ?>">
<form class="edit-bar" data-edit-bar data-edit-texts-only>
  <?= csrf_field() ?>
  {% include "partials/public/_edit_texts_data.lex.php" %}
  <div class="edit-bar-actions">
    <span class="edit-bar-status" data-edit-status role="status" aria-live="polite"></span>
    <button type="button" class="edit-bar-btn is-primary" data-edit-start>Edit page</button>
    <button type="button" class="edit-bar-btn" data-edit-reset-all hidden>Reset page</button>
    <a class="edit-bar-btn" href="<?= e($edit_texts['settings_url']) ?>" data-edit-more hidden>All settings</a>
    <button type="button" class="edit-bar-btn" data-edit-cancel hidden>Cancel</button>
    <button type="submit" class="edit-bar-btn is-primary" data-edit-save hidden>Save</button>
  </div>
</form>
{% include "partials/public/_blog_confirm.lex.php" %}
<script defer src="<?= e(versioned_asset('/assets/js/edit-bar.js')) ?>"></script>
<script defer src="<?= e(versioned_asset('/assets/js/edit-texts.js')) ?>"></script>
<?php } ?>
