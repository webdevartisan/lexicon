{% extends "back.lex.php" %}

{% block title %}Creating New Post{% endblock %}
{% block subtitle %}Fill in details to create your new post.{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/choices.css">
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
<script nonce="<?= csp_nonce() ?>">window.editorBlogId = <?= (int) ($selected_blog_id ?? 0) ?>;</script>
<script src="/assets/js/initeditor.js" referrerpolicy="origin"></script>
<script src="/cp-assets/libs/dropzone/dropzone-min.js"></script>
<script src="/cp-assets/libs/flatpickr/flatpickr.min.js"></script>
<script src="/cp-assets/js/dropzone.init.js"></script>
<script src="/cp-assets/js/flatpickr.init.js"></script>
<script src="/cp-assets/js/autosave.js"></script>
<script src="/cp-assets/js/pages/post.js"></script>
<script src="/cp-assets/js/media-picker.js"></script>

<script nonce="<?= csp_nonce() ?>">
  document.addEventListener("DOMContentLoaded", function () {
    const NameInput = document.getElementById('title');
    const SlugInput = document.getElementById('slug');
    const excerptField = document.getElementById('excerpt');

    if (NameInput && SlugInput) {
      NameInput.addEventListener('input', function () {
        let slug = NameInput.value
          .toLowerCase()               // convert to lowercase
          .trim()                     // remove leading/trailing spaces
          .replace(/[^\w\s-]/g, '')   // remove non-word characters except spaces/dashes
          .replace(/\s+/g, '-')       // replace spaces with dashes
          .replace(/-+/g, '-');       // collapse multiple dashes
        SlugInput.value = slug;
      });
    }

  if (!excerptField || typeof tinymce === 'undefined') {
    return;
  }

  let isExcerptEdited = false;

  excerptField.addEventListener('input', function () {
    isExcerptEdited = excerptField.value.trim().length > 0;
  });

  function getAutoExcerpt(html) {
    const wrapper = document.createElement('div');
    wrapper.innerHTML = html;

    let element = wrapper.firstElementChild;

    while (element && (element.tagName === 'H1' || element.tagName === 'H2')) {
      element = element.nextElementSibling;
    }

    while (element && element.tagName !== 'P') {
      element = element.nextElementSibling;
    }

    if (!element) {
      return '';
    }

    const text = element.textContent.trim();
    return text.length > 180 ? text.slice(0, 180).trimEnd() + '...' : text;
  }

  function syncExcerpt() {
    if (isExcerptEdited) {
      return;
    }

    const editor = tinymce.get('content');

    if (!editor) {
      return;
    }

    excerptField.value = getAutoExcerpt(editor.getContent());
  }

  const bindWhenReady = function () {
    const editor = tinymce.get('content');

    if (!editor) {
      setTimeout(bindWhenReady, 100);
      return;
    }

    editor.on('keyup change input SetContent', syncExcerpt);
    editor.on('init', syncExcerpt);
    syncExcerpt();
  };

  bindWhenReady();
});
</script>
{% endblock %}