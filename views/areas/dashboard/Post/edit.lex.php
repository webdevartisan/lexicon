{% extends "back.lex.php" %}

{% block title %}Edit Post · {{ post.title }}{% endblock %}
{% block subtitle %}Update details, content, localization, SEO, and workflow state for this post.{% endblock %}

{% block head %}
<script src="/vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script nonce="<?= csp_nonce() ?>">window.editorBlogId = <?= (int) ($post['blog_id'] ?? 0) ?>;</script>
<script src="<?= e(versioned_asset('/assets/js/initeditor.js')) ?>" referrerpolicy="origin"></script>
<link rel="stylesheet" href="/cp-assets/css/vendors/dropzone.css">
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
{% endblock %}

{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">
  <?php $activeLocale = $defaultLocale ?? 'en'; ?>
  {% include "partials/dashboard/post/_locale_tabs.lex.php" %}
  <form
    method="post"
    action="/dashboard/post/{{ post.id }}/update"
    enctype="multipart/form-data"
    data-dropzone-form
    class="space-y-3"<?= $autosave ? ' data-autosave-form' : ' data-unsaved-warning' ?>>
    <input type="hidden" name="r" value="<?= e(old('r', $returnToken ?? '')) ?>" >
    {% include "partials/dashboard/post/_form.lex.php" %}
  </form>
  <?php $goLiveReturn = '/dashboard/post/'.(int) $post['id'].'/edit'; ?>
  {% cmp="go-live-form" line="{$visibility}" id="go-live-form" returnTo="{$goLiveReturn}" %}
</div>

<form method="POST" action="/dashboard/post/<?= (int) $post['id'] ?>/destroy?r=<?= e(urlencode((string) ($returnToken ?? ''))) ?>" id="deletePost" data-discards-changes>
    {{ csrf_field() }}
</form>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/libs/dropzone/dropzone-min.js"></script>
<script src="/cp-assets/libs/flatpickr/flatpickr.min.js"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/dropzone.init.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/flatpickr.init.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/autosave.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/pages/post.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/media-picker.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/modal.js')) ?>"></script>
<?php if (!empty($linkEditable)) { ?>
<?php $slugCheckUrl = lurl('/dashboard/slug-check').'?type=post&blog_id='.(int) $post['blog_id'].'&except='.(int) $post['id']; ?>
<script src="<?= e(versioned_asset('/cp-assets/js/slug-field.js')) ?>"></script>
<script nonce="<?= csp_nonce() ?>">
  document.addEventListener('DOMContentLoaded', function () {
    SlugField.bind({ source: 'title', target: 'slug', min: 2, max: 100, checkUrl: <?= json_encode($slugCheckUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?> });
  });
</script>
<?php } ?>
<?php /* Last, so it reads the fields after the other scripts have filled them in. */ ?>
<script src="<?= e(versioned_asset('/cp-assets/js/unsaved-changes.js')) ?>"></script>
{% endblock %}