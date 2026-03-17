{% extends "back.lex.php" %}

{% block title %}Edit Post · {{ post.title }}{% endblock %}
{% block subtitle %}Update details, content, localization, SEO, and workflow state for this post.{% endblock %}

{% block head %}
<script src="/vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script src="/assets/js/initeditor.js" referrerpolicy="origin"></script>
<link rel="stylesheet" href="/cp-assets/css/vendors/choices.css">
<link rel="stylesheet" href="/cp-assets/css/vendors/dropzone.css">
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
{% endblock %}

{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">
  <form
    method="post"
    action="/dashboard/post/{{ post.id }}/update"
    enctype="multipart/form-data"
    data-dropzone-form
    class="space-y-3" data-autosave-form>
    {% include "partials/dashboard/post/_form.lex.php" %}
  </form>
</div>

{% cmp="modal" 
    id="confirmModal" 
    title="Are you sure?" 
    icon="alert-circle"
    variant="danger"
    message="This action cannot be undone."
    confirmText="Yes, Continue"
    cancelText="Cancel" 
    form="deletePost" %}
<form method="POST" action="/dashboard/post/{{ post.id }}/destroy" id="deletePost">
    {{ csrf_field() }}
</form>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/libs/dropzone/dropzone-min.js"></script>
<script src="/cp-assets/libs/flatpickr/flatpickr.min.js"></script>
<script src="/cp-assets/js/dropzone.init.js"></script>
<script src="/cp-assets/js/flatpickr.init.js"></script>
<script src="/cp-assets/js/autosave.js"></script>
<script src="/cp-assets/js/pages/post.js"></script>
<script src="/cp-assets/js/modal.js"></script>
{% endblock %}