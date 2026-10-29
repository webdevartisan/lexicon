<?php if (!empty($edit_in_place)) { ?>
<link rel="stylesheet" href="<?= e(versioned_asset('/assets/css/edit-in-place.css')) ?>">
<link rel="stylesheet" href="<?= e(versioned_asset('/assets/css/media-picker.css')) ?>">
<form class="edit-bar" data-edit-bar
      data-save-url="<?= e($edit_in_place['save_url']) ?>"
      data-blog-id="<?= (int) $edit_in_place['blog_id'] ?>"
      data-editor-script="/vendor/tinymce/tinymce.min.js">
  <?= csrf_field() ?>
  <input type="hidden" name="version" value="<?= e($edit_in_place['version']) ?>" data-edit-version>
  <template data-edit-source><?= $edit_in_place['content'] ?></template>
  <div class="edit-bar-toolbar" data-edit-toolbar hidden></div>
  <div class="edit-bar-actions">
    <span class="edit-bar-status" data-edit-status role="status" aria-live="polite"></span>
    <button type="button" class="edit-bar-btn is-primary" data-edit-start>Edit post</button>
    <a class="edit-bar-btn" href="<?= e($edit_in_place['editor_url']) ?>" data-edit-more hidden>All settings</a>
    <button type="button" class="edit-bar-btn" data-edit-cancel hidden>Cancel</button>
    <button type="submit" class="edit-bar-btn is-primary" data-edit-save hidden>Save</button>
  </div>
</form>
<script defer src="<?= e(versioned_asset('/cp-assets/js/media-picker.js')) ?>"></script>
<script defer src="<?= e(versioned_asset('/assets/js/editor-media.js')) ?>"></script>
<script defer src="<?= e(versioned_asset('/assets/js/editor-colours.js')) ?>"></script>
<script defer src="<?= e(versioned_asset('/assets/js/edit-in-place.js')) ?>"></script>
<?php } ?>
