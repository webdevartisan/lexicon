{% extends "back.lex.php" %}

{% block title %}Account{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/choices.css">
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
{% endblock %}

{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">

  <?php $settingsTab = 'account'; ?>
  {% include "partials/dashboard/_settings_tabs.lex.php" %}

  <div class="grid gap-6 mt-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">

      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Account Settings</h2>
          <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">
            Your sign-in address and the defaults applied to new posts.
          </p>
        </div>

        <div class="p-4 md:p-5">
          <form method="post" action="/dashboard/account/update">
            {{ csrf_field() }}

            {# Sign-in details #}
            <h3 class="mb-2 text-xs font-semibold tracking-wide uppercase text-slate-500 dark:text-zink-300">
              Sign-in Details
            </h3>
            <div class="grid gap-4 md:grid-cols-2">

              <?php $username = $user['username']; ?>
              {% cmp="input" type="text" label="Username" value="{$username}" underlabel="Disabled" disabled="true" %}

              <?php $email = $user['email']; ?>
              {% cmp="input" type="email" label="Email" value="{$email}" %}

            </div>

            {# Blog Preferences #}
            <div class="mt-6 space-y-3">
              <h3 class="text-xs font-semibold tracking-wide uppercase text-slate-500 dark:text-zink-300">
                Blog Preferences
              </h3>
              <div class="grid gap-4 md:grid-cols-2">

                <?php $options = ['name' => 'Full Name', 'username' => 'Username']; ?>
                <?php $selectedKey = $user['display_name']; ?>
                {% cmp="select" options="{$options}" selectedKey="{$selectedKey}" label="Display Name" %}

                <?php $options = ['public' => 'Public', 'unlisted' => 'Unlisted', 'private' => 'Private']; ?>
                <?php $selectedKey = $user['default_visibility']; ?>
                {% cmp="select" options="{$options}" selectedKey="{$selectedKey}" name="default visibility" label="Default Visibility" %}

              </div>

              <div class="grid gap-4 mt-2 md:grid-cols-2">

                <?php $selectedKey = $user['timezone']; ?>
                {% cmp="select" groups="{$timezones}" selectedKey="{$selectedKey}" name="timezone" label="Timezone" %}

                <?php $options = $locales; ?>
                <?php $selectedKey = $user['locale']; ?>
                {% cmp="select" options="{$options}" selectedKey="{$selectedKey}" name="locale" label="Interface Language" %}

              </div>
            </div>

            <div class="flex justify-end mt-6">
              {% cmp="btn" type="submit" variant="blue" icon="save" label="Save Changes" %}
            </div>
          </form>
        </div>
      </section>

      <!-- Danger Zone Section -->
      <div class="card mt-4 border-red-200 dark:border-red-800">
        <div class="card-body bg-red-50 dark:bg-red-900/10">
          <div class="flex items-start gap-3">
            <div class="flex items-center justify-center size-10 bg-red-100 dark:bg-red-500/20 rounded-md shrink-0">
              <i data-lucide="alert-triangle" class="size-5 text-red-500"></i>
            </div>
            <div class="grow">
              <h6 class="mb-2 text-15 text-red-600 dark:text-red-400">Danger Zone</h6>
              <p class="text-slate-500 dark:text-zink-300 mb-3">
                Once you delete your account, there is no going back.
                Your personal information will be permanently removed.
              </p>
              <button data-modal-target="deleteAccountModal" type="button"
                class="text-white btn bg-red-500 border-red-500 hover:text-white hover:bg-red-600 hover:border-red-600 focus:text-white focus:bg-red-600 focus:border-red-600 focus:ring focus:ring-red-100 active:text-white active:bg-red-600 active:border-red-600 active:ring active:ring-red-100 dark:ring-red-400/20">
                <i data-lucide="trash-2" class="inline-block size-4 mr-1"></i>
                Delete Account
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <aside class="space-y-6 max-w-xs">
      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h3 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Your Data</h3>
        </div>
        <div class="p-4 space-y-3 text-xs text-slate-600 dark:text-zink-200">
          <p>Download everything associated with your account.</p>
          <div class="flex justify-center">
            {% cmp="btn" href="/dashboard/export" variant="slate" icon="download" label="Export my data" %}
          </div>
        </div>
      </section>
    </aside>
  </div>
</div>

<!-- Delete Account Modal -->
<?php $userPostCount = $user['post_count']; ?>
<?php $userCommentCount = $user['comment_count']; ?>
{% cmp="deleteAccountModal" postCount="{$userPostCount}" commentCount="{$userCommentCount}" %}

{% endblock %}

{% block scripts %}
<script src='/cp-assets/libs/choices.js/public/assets/scripts/choices.min.js'></script>
<script src="/cp-assets/js/modal.js"></script>
<script nonce="<?= csp_nonce() ?>">
  document.addEventListener('DOMContentLoaded', () => {
    const selectEl = document.querySelector('select[name="timezone"]');
    if (!selectEl) {
      return;
    }

    new Choices(selectEl, {
      shouldSort: true,
      allowHTML: true,
      searchEnabled: true,
      placeholder: true,
      placeholderValue: 'Select timezone',
    });
  });
</script>

{% include "partials/dashboard/_form_error_validity.lex.php" %}

<script nonce="<?= csp_nonce() ?>">
  // extra confirmation beyond the modal, since deletion is unrecoverable
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
