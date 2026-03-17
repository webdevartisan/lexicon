{% extends "back.lex.php" %}

{% block title %}{{ t('blog.update.pageTitle') }} {% endblock %}
{% block subtitle %}{{ t('blog.update.pageSubtitle') }}{% endblock %}
{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/choices.css">
<link rel="stylesheet" href="/cp-assets/css/vendors/dropzone.css">
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
{% endblock %}
{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">
  <!-- Errors -->
  {% if errors|notempty %}
  <div class="mb-4">
    <div class="flex items-start gap-3 p-3 text-sm rounded-md bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-900/30 dark:text-amber-100 dark:border-amber-700">
      <div class="mt-0.5">
        <i class="fas fa-exclamation-triangle text-sm"></i>
      </div>
      <div>
        <p class="font-semibold">We found some issues:</p>
        <ul class="mt-2 space-y-1 list-disc list-inside">
          {% foreach ($errors as $field => $msg): %}
          <li>{{ msg }}</li>
          {% endforeach %}
        </ul>
      </div>
    </div>
  </div>
  {% endif %}

  <!-- Form + sidebar -->
<form
  id="blog-edit-form"
  method="post"
  action="/dashboard/blogs/{{ blog.id }}/update"
  enctype="multipart/form-data"
  data-dropzone-form
  class="">
  <input type="hidden" name="_method" value="PUT">
  
    {% include "partials/dashboard/blog/_form.lex.php" %}

    <!-- Danger Zone Section -->
  <div class="card mt-4 border-red-200 dark:border-red-800">
    <div class="card-body bg-red-50 dark:bg-red-900/10">
      <div class="flex items-start gap-3">
        <div class="flex items-center justify-center size-10 bg-red-100 dark:bg-red-500/20 rounded-md shrink-0">
          <i data-lucide="alert-triangle" class="size-5 text-red-500"></i>
        </div>
        <div class="grow">
          <h6 class="mb-2 text-15 text-red-600 dark:text-red-400">{{ t('blog.dangerZone.title') }}</h6>
          <p class="text-slate-500 dark:text-zink-300 mb-3">
            {{ t('blog.dangerZone.description') }}
          </p>
          <button 
            data-modal-target="deleteBlogModal" 
            data-blog-id="<?= $blog['id'] ?>"
            type="button"
            class="text-white btn bg-red-500 border-red-500 hover:text-white hover:bg-red-600 hover:border-red-600 focus:text-white focus:bg-red-600 focus:border-red-600 focus:ring focus:ring-red-100 active:text-white active:bg-red-600 active:border-red-600 active:ring active:ring-red-100 dark:ring-red-400/20">
            <i data-lucide="trash-2" class="inline-block size-4 mr-1"></i>
            {{ t('blog.dangerZone.deleteButton') }}
          </button>
        </div>
      </div>
    </div>
  </div>

</form>

<!-- Include Delete Blog Modal -->
{% cmp="deleteBlogModal" blog="{$blog}" stats="{$stats}" %}

</div>
{% endblock %}

{% block scripts %}
<script src='/cp-assets/libs/choices.js/public/assets/scripts/choices.min.js'></script>
<script src="/cp-assets/libs/dropzone/dropzone-min.js"></script>
<script src="/cp-assets/js/timezone.init.js"></script>
<script src="/cp-assets/js/dropzone.init.js"></script>
<script src="/cp-assets/js/modal.js"></script>
<script src="/cp-assets/js/delete-blog-modal.js"></script>
<!-- JavaScript for Modal and Final Confirmation -->
<script>
  // add JavaScript confirmation for extra safety
  document.getElementById('deleteAccountForm')?.addEventListener('submit', function (e) {
    const checkbox = document.getElementById('confirmDeleteCheck');
    const password = document.getElementById('confirmPassword').value;

    if (!checkbox.checked) {
      e.preventDefault();
      alert('Please confirm you understand this action is permanent.');
      return false;
    }

    if (!password) {
      e.preventDefault();
      alert('Please enter your password to confirm deletion.');
      return false;
    }

    // Final confirmation dialog
    const confirmed = confirm(
      'FINAL WARNING:\n\n' +
      'This will permanently delete your account and all personal data.\n\n' +
      'Are you absolutely sure you want to continue?'
    );

    if (!confirmed) {
      e.preventDefault();
      return false;
    }
  });


</script>
{% endblock %}