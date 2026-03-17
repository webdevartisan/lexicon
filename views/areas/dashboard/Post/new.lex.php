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
    {% include "partials/dashboard/post/_form.lex.php" %}
  </form>
</div>
{% endblock %}

{% block scripts %}
<script src="/vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script src="/assets/js/initeditor.js" referrerpolicy="origin"></script>
<script src="/cp-assets/libs/dropzone/dropzone-min.js"></script>
<script src="/cp-assets/libs/flatpickr/flatpickr.min.js"></script>
<script src="/cp-assets/js/dropzone.init.js"></script>
<script src="/cp-assets/js/flatpickr.init.js"></script>
<script src="/cp-assets/js/autosave.js"></script>
<script src="/cp-assets/js/pages/post.js"></script>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    const NameInput = document.getElementById('title');
    const SlugInput = document.getElementById('slug');

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
  });
</script>
{% endblock %}