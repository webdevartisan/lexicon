{% extends "back.lex.php" %}

{% block title %}Profile Settings{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/choices.css">
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
{% endblock %}

{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">

  {# === MAIN EDIT FORMS (LEFT) + SUMMARY/CONTROLS (RIGHT) === #}
  <div class="grid gap-6 mt-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">

      {# BASIC INFO + ABOUT + SOCIAL LINKS + BLOG PREFS #}
      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Profile Settings</h2>
          <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">
            Update your basic information, profile brief, and social links.
          </p>
        </div>

        <div class="p-4 md:p-5">
          <form method="post" action="/dashboard/profile/update">
            {{ csrf_field() }}

            {# Basic Info #}
            <h3 class="mb-2 text-xs font-semibold tracking-wide uppercase text-slate-500 dark:text-zink-300">
              Basic Info
            </h3>
            <div class="grid gap-4 md:grid-cols-2">

              <?php $firstName = $user['first_name']; ?>
              {% cmp="input" type="text" label="First Name" value="{$firstName}" %}

              <?php $lastName = $user['last_name']; ?>
              {% cmp="input" type="text" label="Last Name" value="{$lastName}" %}


              <?php $username = $user['username']; ?>
              {% cmp="input" type="text" label="Username" value="{$username}" underlabel="Disabled" disabled="true" %}

              <?php $email = $user['email']; ?>
              {% cmp="input" type="email" label="Email" value="{$email}" %}

            </div>

            {# About Me #}
            <div class="mt-6 space-y-3">
              <h3 class="text-xs font-semibold tracking-wide uppercase text-slate-500 dark:text-zink-300">
                About Me
              </h3>
              <div class="grid gap-4 md:grid-cols-2">

                <?php $occupation = $user['occupation']; ?>
                {% cmp="input" type="text" label="Occupation" value="{$occupation}" placeholder="e.g. Blogger, Software Engineer" %}

                <?php $location = $user['location']; ?>
                {% cmp="input" type="text" label="Location" value="{$location}" %}

              </div>

              <?php $bio = $user['bio']; ?>
              {% cmp="input" type="textarea" label="Bio" value="{$bio}" %}
            </div>

            {# Profile #}
            <div class="mt-6 space-y-3">
              <h3 class="text-xs font-semibold tracking-wide uppercase text-slate-500 dark:text-zink-300">
                Profile
              </h3>
              <?php $slug = $user['slug']; ?>
              {% cmp="input" type="text" label="Public profile URL" value="{$slug}" prefix="https://lexicon.com/en/profile/" placeholder="your-slug" underlabel="This will be your public profile link." %}
            </div>

            {# Social Links #}
            <div class="mt-6 space-y-3">
              <h3 class="text-xs font-semibold tracking-wide uppercase text-slate-500 dark:text-zink-300">
                Social Links
              </h3>
              <div class="grid gap-4 md:grid-cols-2">

                <?php $website = $user['website']; ?>
                {% cmp="input" type="url" label="Website" value="{$website}" %}

                <?php $twitter = $user['twitter']; ?>
                {% cmp="input" type="url" label="Twitter (X)" value="{$twitter}" %}

                <?php $instagram = $user['instagram']; ?>
                {% cmp="input" type="url" label="Instagram" value="{$instagram}" %}

                <?php $linkedin = $user['linkedin']; ?>
                {% cmp="input" type="url" label="LinkedIn" value="{$linkedin}" %}

                <?php $github = $user['github']; ?>
                {% cmp="input" type="url" label="GitHub" value="{$github}" %}

              </div>
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

                <?php $options = ['public' => 'Public', 'private' => 'Private']; ?>
                <?php $selectedKey = $user['default_visibility']; ?>
                {% cmp="select" options="{$options}" selectedKey="{$selectedKey}" label="Default Visibility" %}

              </div>

              <div class="grid gap-4 mt-2 md:grid-cols-2">

                <?php $selectedKey = $user['timezone']; ?>
                {% cmp="select" groups="{$timezones}" selectedKey="{$selectedKey}" label="timezone" %}

              </div>
            </div>

            <div class="flex justify-end mt-6">
              {% cmp="btn" type="submit" variant="blue" icon="save" label="Save Changes" %}
            </div>
          </form>
        </div>
      </section>

      {# Change Password #}
      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Change Password</h2>
        </div>
        <div class="p-4 md:p-5">
          <form method="post" action="/dashboard/profile/update/password">
            {{ csrf_field() }}
            <input type="text" name="username" autocomplete="username" value="{{ user.username }}" class="hidden"
              aria-hidden="true" tabindex="-1">
            <div class="space-y-4">

              {% cmp="input" type="password" label="Current Password" required="true" %}


              <div class="grid gap-4 md:grid-cols-2">
                {% cmp="input" type="password" label="New Password" required="true" %}
                {% cmp="input" type="password" label="New Password Confirm" required="true" %}
              </div>
            </div>
            <div class="flex justify-end mt-4">
              {% cmp="btn" type="submit" variant="blue" icon="lock" label="Update Password" %}
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
    {# RIGHT: Activity summary + account controls #}
    <aside class="space-y-6 max-w-xs">

      <div class="card">
        <div class="card-body">
          <h6 class="mb-4 text-15">Profile Avatar</h6>

          <div class="flex flex-col gap-5">

            <!-- Avatar Display & Upload -->
            <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start">

              <!-- Current Avatar -->
              <div
                class="relative inline-block size-24 rounded-full shadow-md bg-slate-100 dark:bg-zink-600 profile-user">
                {% if user.avatar_url|notempty %}
                <img id="avatar-preview" src="{{ user.avatar_url }}" alt="{{ user.display_name_cached }} avatar"
                  class="w-full h-full rounded-full object-cover">
                {% else %}
                <div id="avatar-placeholder"
                  class="flex items-center justify-center w-full h-full text-2xl font-semibold text-slate-600 dark:text-zink-100">
                  {{ user.initials }}
                </div>
                {% endif %}

                <!-- Edit Button Overlay -->
                <div
                  class="absolute bottom-0 right-0 flex items-center justify-center rounded-full size-8 bg-white dark:bg-zink-600 shadow-lg profile-photo-edit">
                  <label for="avatar-input" class="flex items-center justify-center cursor-pointer size-8"
                    title="Change avatar">
                    <i data-lucide="image-plus" class="w-4 h-4 text-slate-500 dark:text-zink-200"></i>
                    <span class="sr-only">Change profile avatar</span>
                  </label>
                </div>
              </div>

              <!-- Avatar Upload Form & Actions -->
              <div class="flex-1">
                <form id="avatar-form" method="post" action="/dashboard/profile/avatar" enctype="multipart/form-data"
                  class="space-y-3">

                  {{ csrf_field() }}

                  <!-- Hidden File Input -->
                  <input id="avatar-input" name="avatar" type="file" class="hidden"
                    accept="image/jpeg,image/png,image/webp" data-max-size="2097152">

                  <!-- Upload Instructions -->
                  <div id="upload-instructions" class="text-sm text-slate-500 dark:text-zink-200">
                    <p class="mb-1">Click the icon to upload a new avatar</p>
                    <p class="text-xs">JPG, PNG or WebP. Max size 2MB.</p>
                  </div>

                  <!-- File Name Display (Hidden until file selected) -->
                  <div id="selected-file" class="hidden">
                    <div class="flex items-center gap-2 p-2 text-sm rounded-md bg-slate-100 dark:bg-zink-600">
                      <i data-lucide="file-image" class="size-4 text-custom-500"></i>
                      <span id="file-name" class="flex-1 truncate text-slate-700 dark:text-zink-100"></span>
                      <button type="button" id="clear-file" class="text-slate-400 hover:text-red-500"
                        title="Clear selection">
                        <i data-lucide="x" class="size-4"></i>
                      </button>
                    </div>
                  </div>

                  <!-- Upload Button (Hidden until file selected) -->
                  <div id="upload-actions" class="hidden">
                    <button type="submit" class="btn bg-custom-500 text-white hover:bg-custom-600">
                      <i data-lucide="upload" class="inline-block size-4 mr-1"></i>
                      <span>{% if user.avatar_url|notempty %}Replace{% else %}Upload{% endif %} Avatar</span>
                    </button>
                  </div>

                  <!-- Loading State (Hidden by default) -->
                  <div id="upload-loading" class="hidden">
                    <div class="flex items-center gap-2 text-sm text-custom-500">
                      <div
                        class="inline-block size-4 border-2 border-current border-t-transparent rounded-full animate-spin">
                      </div>
                      <span>Uploading...</span>
                    </div>
                  </div>

                </form>

                <!-- Remove Avatar Button (Always visible if avatar exists) -->
                {% if user.avatar_url|notempty %}
                <div class="pt-3 mt-3 border-t border-slate-200 dark:border-zink-500">
                  <form id="remove-avatar-form" method="post" action="/dashboard/profile/avatar/remove">
                    {{ csrf_field() }}
                    <button type="button" id="remove-avatar-btn"
                      class="btn bg-white border-red-500 text-red-500 hover:bg-red-50 dark:bg-zink-700 dark:border-red-500 dark:hover:bg-red-500/10">
                      <i data-lucide="trash-2" class="inline-block size-4 mr-1"></i>
                      Remove Current Avatar
                    </button>
                  </form>
                </div>
                {% endif %}

                <!-- Error Display -->
                {% if errors.avatar|notempty %}
                <div class="mt-3 p-3 text-sm text-red-600 bg-red-50 dark:bg-red-900/20 rounded-md">
                  {% foreach ($errors['avatar'] as $error) %}
                  <p>{{ error }}</p>
                  {% endforeach %}
                </div>
                {% endif %}

              </div>

            </div>

          </div>
        </div>
      </div><!--end card-->



      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h3 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Activity Summary</h3>
        </div>
        <div class="p-4 space-y-2 text-xs text-slate-600 dark:text-zink-200">
          <p>Total Posts <span class="font-semibold text-slate-900 dark:text-zink-100">{{ user.post_count }}</span></p>
          <p>Comments Received <span class="font-semibold text-slate-900 dark:text-zink-100">{{ user.comment_count
              }}</span></p>
          <p>Last Login <span class="font-semibold text-slate-900 dark:text-zink-100">{{ user.last_login }}</span></p>
        </div>
      </section>

      {# Notifications #}
      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Notifications</h2>
        </div>
        <div class="p-4 md:p-5">
          <form method="post" action="/dashboard/account/notifications">
            {{ csrf_field() }}
            <div class="space-y-3 text-xs text-slate-700 dark:text-zink-100">
              <label class="flex items-start gap-2">
                <input type="checkbox" name="notify_comments" value="1"
                  class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600" {{
                  user.notify_comments ? 'checked' : '' }}>
                <span>
                  <span class="font-medium">Email me when someone comments</span><br>
                  <span class="text-[11px] text-slate-500 dark:text-zink-300">
                    Applies to comments on posts you authored.
                  </span>
                </span>
              </label>

              <label class="flex items-start gap-2">
                <input type="checkbox" name="notify_likes" value="1"
                  class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600" {{
                  user.notify_likes ? 'checked' : '' }}>
                <span>
                  <span class="font-medium">Email me when someone likes a post</span>
                </span>
              </label>
            </div>

            <div class="flex justify-center mt-4">
              {% cmp="btn" type="submit" variant="blue" icon="bell-ring" label="Save notification settings" %}
            </div>
          </form>
        </div>
      </section>

      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h3 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Account Controls</h3>
        </div>
        <div class="p-4 space-y-5 text-xs text-slate-600 dark:text-zink-200">
          <?php $slug = $user['slug']; ?>
          <div class="flex justify-center gap-2">
            {% cmp="btn" href="/profile/{$slug}" variant="slate" icon="external-link" label="Profile Preview" %}
            {% cmp="btn" href="/dashboard/export" variant="slate" icon="download" label="Export my data" %}
          </div>
          <form method="post" action="/dashboard/delete-account"
            onsubmit="return confirm('Are you sure you want to delete your account? This cannot be undone.');">
            <input type="hidden" name="_method" value="DELETE">
            {{ csrf_field() }}
            <div class="flex justify-center mt-4">
              {% cmp="btn" type="submit" variant="red" icon="trash-2" label="Delete my account" %}
            </div>
          </form>
        </div>

      </section>
    </aside>
  </div>
</div>

<!-- Delete Account Modal (Using Your Template's Structure) -->
 {% cmp="deleteAccountModal" postCount="{$userPostCount}" commentCount="{$userCommentCount}" %}

{% endblock %}
{% block scripts %}
<script src='/cp-assets/libs/choices.js/public/assets/scripts/choices.min.js'></script>
<script src="/cp-assets/js/modal.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const selectEl = document.querySelector('select[name="timezone"]');
    if (!selectEl) {
      console.error('No timezone select');
      return;
    }

    // initialize Choices once and keep the instance reference.
    const tzChoices = new Choices(selectEl, {
      shouldSort: true,
      allowHTML: true,
      searchEnabled: true,
      placeholder: true,
      placeholderValue: 'Select timezone',
    });

  });
</script>

<script>
  // set custom browser validation messages for server-side errors
  document.addEventListener('DOMContentLoaded', function () {
    // find all inputs/textareas with server-side errors
    const errorFields = document.querySelectorAll('[aria-invalid="true"]');

    errorFields.forEach(function (field) {
      // get the error message from the associated error div
      const errorId = field.getAttribute('aria-describedby');
      const errorDiv = document.getElementById(errorId);


      if (errorDiv) {
        // get the first error message
        const errorText = errorDiv.querySelector('p')?.textContent.trim() || 'Invalid input';

        // set browser's custom validity message
        // This triggers :invalid pseudo-class
        field.setCustomValidity(errorText);

        // clear the custom validity on input change
        field.addEventListener('input', function () {
          field.setCustomValidity('');
        });
        field.classList.remove('dark:valid:border-green-800', 'valid:border-green-500');

      }
    });

    // Public profile URL Prefix
    const base = window.location.origin;
    const prefix = document.getElementById('public_profile_url');
    prefix.innerHTML = base + '/en/profile/';
  });
</script>


<script>
  // handle avatar upload preview and validation
  (function () {
    const avatarInput = document.getElementById('avatar-input');
    const avatarPreview = document.getElementById('avatar-preview');
    const avatarPlaceholder = document.getElementById('avatar-placeholder');
    const uploadInstructions = document.getElementById('upload-instructions');
    const selectedFileDiv = document.getElementById('selected-file');
    const fileNameSpan = document.getElementById('file-name');
    const uploadActions = document.getElementById('upload-actions');
    const uploadLoading = document.getElementById('upload-loading');
    const avatarForm = document.getElementById('avatar-form');
    const clearFileBtn = document.getElementById('clear-file');
    const removeAvatarBtn = document.getElementById('remove-avatar-btn');
    const removeAvatarForm = document.getElementById('remove-avatar-form');

    if (!avatarInput) return;

    // store the original avatar URL for restoration
    const originalAvatarUrl = '{{ user.avatar_url }}';

    // handle file selection
    avatarInput.addEventListener('change', function (e) {
      const file = e.target.files[0];

      if (!file) {
        resetUploadUI();
        return;
      }

      // validate file type
      const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
      if (!validTypes.includes(file.type)) {
        alert('Please select a valid image file (JPG, PNG, or WebP).');
        resetUploadUI();
        return;
      }

      // validate file size (2MB max)
      const maxSize = parseInt(avatarInput.dataset.maxSize) || 2097152;
      if (file.size > maxSize) {
        alert('File size must be less than 2MB.');
        resetUploadUI();
        return;
      }

      // show file name and upload button
      fileNameSpan.textContent = file.name;
      uploadInstructions.classList.add('hidden');
      selectedFileDiv.classList.remove('hidden');
      uploadActions.classList.remove('hidden');

      // preview the image
      const reader = new FileReader();
      reader.onload = function (e) {
        const previewImg = document.getElementById('avatar-preview');
        const placeholder = document.getElementById('avatar-placeholder');

        if (previewImg) {
          // update existing image preview
          previewImg.src = e.target.result;
        } else if (placeholder) {
          // replace placeholder with preview image
          const img = document.createElement('img');
          img.id = 'avatar-preview';
          img.src = e.target.result;
          img.className = 'w-full h-full rounded-full object-cover';
          placeholder.replaceWith(img);
        }
      };
      reader.readAsDataURL(file);
    });

    // handle clear file button
    if (clearFileBtn) {
      clearFileBtn.addEventListener('click', function () {
        resetUploadUI();
      });
    }

    // handle form submission
    if (avatarForm) {
      avatarForm.addEventListener('submit', function (e) {
        // show loading state
        uploadActions.classList.add('hidden');
        selectedFileDiv.classList.add('hidden');
        uploadLoading.classList.remove('hidden');

        // let the form submit normally
        // Loading state will clear on page reload
      });
    }

    // handle remove avatar button
    if (removeAvatarBtn && removeAvatarForm) {
      removeAvatarBtn.addEventListener('click', function () {
        if (confirm('Are you sure you want to remove your profile avatar?')) {
          removeAvatarForm.submit();
        }
      });
    }

    // reset the upload UI to initial state
    function resetUploadUI() {
      // clear the file input
      avatarInput.value = '';

      // hide upload UI elements
      selectedFileDiv.classList.add('hidden');
      uploadActions.classList.add('hidden');
      uploadInstructions.classList.remove('hidden');

      // restore original avatar preview
      const previewImg = document.getElementById('avatar-preview');
      if (originalAvatarUrl && previewImg) {
        previewImg.src = originalAvatarUrl;
      } else if (!originalAvatarUrl && previewImg) {
        // restore placeholder if original had no avatar
        const placeholder = document.createElement('div');
        placeholder.id = 'avatar-placeholder';
        placeholder.className = 'flex items-center justify-center w-full h-full text-2xl font-semibold text-slate-600 dark:text-zink-100';
        placeholder.textContent = '{{ user.initials }}';
        previewImg.replaceWith(placeholder);
      }
    }
  })();
</script>

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