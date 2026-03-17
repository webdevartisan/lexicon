{% extends "back.lex.php" %}

{% block title %}{{ t('blog.create.pageTitle') }}{% endblock %}
{% block subtitle %}{{ t('blog.create.pageSubtitle') }}{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/choices.css">
<link rel="stylesheet" href="/cp-assets/css/vendors/dropzone.css">
{% endblock %}

{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">
  <form
    id="blog-create-form"
    method="post"
    action="/dashboard/blog/create"
    enctype="multipart/form-data"
    data-dropzone-form
    class="">
        <input type="hidden" name="_method" value="PUT">
        {% include "partials/dashboard/blog/_form.lex.php" %}
        
  </form>
</div>
{% endblock %}

{% block scripts %}
<script src='/cp-assets/libs/choices.js/public/assets/scripts/choices.min.js'></script>
<script src="/cp-assets/libs/dropzone/dropzone-min.js"></script>
<script src="/cp-assets/js/dropzone.init.js"></script>
<script src="/cp-assets/js/choices.init.js"></script>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    const NameInput = document.getElementById('name');
    const SlugInput = document.getElementById('slug');

    if (NameInput && SlugInput) {
      NameInput.addEventListener('input', function () {
        // generate a URL-friendly slug from the blog name
        let slug = NameInput.value
          .toLowerCase()               // Convert to lowercase
          .trim()                      // Remove leading/trailing spaces
          .replace(/[^\w\s-]/g, '')    // Remove non-word characters except spaces/dashes
          .replace(/\s+/g, '-')        // Replace spaces with dashes
          .replace(/-+/g, '-');        // Collapse multiple dashes
        SlugInput.value = slug;
      });
    }
  });
</script>

{% endblock %}
