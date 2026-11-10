{% extends "back.lex.php" %}

{% block title %}{{ t('dashboard.actions.newPost') }}{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/dropzone.css">
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">
  <form
    method="post"
    action="/dashboard/post/create"
    enctype="multipart/form-data"
    data-dropzone-form
    class="space-y-3" data-autosave-form>
    <input type="hidden" name="blog_id" value="<?= (int) ($blog['id'] ?? 0) ?>">
    {% include "partials/dashboard/post/_form.lex.php" %}
  </form>
</div>
{% endblock %}

{% block scripts %}
<script src="/vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script nonce="<?= csp_nonce() ?>">window.editorBlogId = <?= (int) ($blog['id'] ?? 0) ?>;</script>
<script src="<?= e(versioned_asset('/assets/js/editor-media.js')) ?>"></script>
<script src="<?= e(versioned_asset('/assets/js/editor-colours.js')) ?>"></script>
<script src="<?= e(versioned_asset('/assets/js/initeditor.js')) ?>" referrerpolicy="origin"></script>
<script src="/cp-assets/libs/dropzone/dropzone-min.js"></script>
<script src="/cp-assets/libs/flatpickr/flatpickr.min.js"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/dropzone.init.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/flatpickr.init.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/autosave.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/pages/post.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/pages/post-tags.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/pages/post-editor.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/title-field.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/tooltip.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/media-picker.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/slug-field.js')) ?>"></script>

<script nonce="<?= csp_nonce() ?>">
  document.addEventListener("DOMContentLoaded", function () {
    SlugField.bind({ source: 'title', target: 'slug', min: 2, max: 100, checkUrl: '<?= e(lurl('/dashboard/slug-check')) ?>?type=post&blog_id=<?= (int) ($blog['id'] ?? 0) ?>' });
  });
</script>
{% endblock %}