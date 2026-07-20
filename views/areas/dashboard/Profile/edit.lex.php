{% extends "back.lex.php" %}

{% block title %}Profile{% endblock %}

{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">

  <?php $settingsTab = 'profile'; ?>
  {% include "partials/dashboard/_settings_tabs.lex.php" %}

  <div class="grid gap-6 mt-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">

      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Public Profile</h2>
          <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">
            This is what readers see. Your email and posting defaults live under Account.
          </p>
        </div>

        <div class="p-4 md:p-5">
          <form method="post" action="/dashboard/profile/update">
            {{ csrf_field() }}

            {# Name #}
            <h3 class="mb-2 text-xs font-semibold tracking-wide uppercase text-slate-500 dark:text-zink-300">
              Name
            </h3>
            <div class="grid gap-4 md:grid-cols-2">

              <?php $firstName = $user['first_name']; ?>
              {% cmp="input" type="text" label="First Name" value="{$firstName}" %}

              <?php $lastName = $user['last_name']; ?>
              {% cmp="input" type="text" label="Last Name" value="{$lastName}" %}

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

            {# Public profile URL #}
            <div class="mt-6 space-y-3">
              <h3 class="text-xs font-semibold tracking-wide uppercase text-slate-500 dark:text-zink-300">
                Public Page
              </h3>
              <?php $slug = $user['slug']; ?>
              {% cmp="input" type="text" name="public profile url" label="Public profile URL" value="{$slug}" prefix="{$profileUrlPrefix}" placeholder="your-name" underlabel="Lowercase letters, numbers, and hyphens. Leave empty to disable your public page." %}

              <label class="flex items-start gap-2 text-xs text-slate-700 dark:text-zink-100">
                <input type="checkbox" name="is_public" value="1"
                  class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600" {{
                  user.is_public ? 'checked' : '' }}>
                <span>
                  <span class="font-medium">Make my profile public</span><br>
                  <span class="text-[11px] text-slate-500 dark:text-zink-300">
                    When off, your profile page returns a 404 for visitors.
                  </span>
                </span>
              </label>
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
                {% cmp="input" type="url" name="twitter" label="Twitter (X)" value="{$twitter}" %}

                <?php $instagram = $user['instagram']; ?>
                {% cmp="input" type="url" label="Instagram" value="{$instagram}" %}

                <?php $linkedin = $user['linkedin']; ?>
                {% cmp="input" type="url" label="LinkedIn" value="{$linkedin}" %}

                <?php $github = $user['github']; ?>
                {% cmp="input" type="url" label="GitHub" value="{$github}" %}

              </div>
            </div>

            <div class="flex justify-end mt-6">
              {% cmp="btn" type="submit" variant="blue" icon="save" label="Save Changes" %}
            </div>
          </form>
        </div>
      </section>

    </div>

    {# RIGHT: avatar, activity, public page link #}
    <aside class="space-y-6 max-w-xs">

      <div class="card">
        <div class="card-body">
          <h6 class="mb-4 text-15">Profile Avatar</h6>

          <div class="flex flex-col gap-5">

            <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start">

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

                <div
                  class="absolute bottom-0 right-0 flex items-center justify-center rounded-full size-8 bg-white dark:bg-zink-600 shadow-lg profile-photo-edit">
                  <label for="avatar-input" class="flex items-center justify-center cursor-pointer size-8"
                    title="Change avatar">
                    <i data-lucide="image-plus" class="w-4 h-4 text-slate-500 dark:text-zink-200"></i>
                    <span class="sr-only">Change profile avatar</span>
                  </label>
                </div>
              </div>

              <div class="flex-1">
                <form id="avatar-form" method="post" action="/dashboard/profile/avatar" enctype="multipart/form-data"
                  class="space-y-3">

                  {{ csrf_field() }}

                  <input id="avatar-input" name="avatar" type="file" class="hidden"
                    accept="image/jpeg,image/png,image/webp" data-max-size="2097152">

                  <div id="upload-instructions" class="text-sm text-slate-500 dark:text-zink-200">
                    <p class="mb-1">Click the icon to upload a new avatar</p>
                    <p class="text-xs">JPG, PNG or WebP. Max size 2MB.</p>
                  </div>

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

                  <div id="upload-actions" class="hidden">
                    <button type="submit" class="btn bg-custom-500 text-white hover:bg-custom-600">
                      <i data-lucide="upload" class="inline-block size-4 mr-1"></i>
                      <span>{% if user.avatar_url|notempty %}Replace{% else %}Upload{% endif %} Avatar</span>
                    </button>
                  </div>

                  <div id="upload-loading" class="hidden">
                    <div class="flex items-center gap-2 text-sm text-custom-500">
                      <div
                        class="inline-block size-4 border-2 border-current border-t-transparent rounded-full animate-spin">
                      </div>
                      <span>Uploading...</span>
                    </div>
                  </div>

                </form>

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
          <p class="flex items-center justify-between">Total Posts
            <span class="font-semibold text-slate-900 dark:text-zink-100">{{ user.post_count }}</span></p>
          <p class="flex items-center justify-between">Comments Received
            <span class="font-semibold text-slate-900 dark:text-zink-100">{{ user.comment_count }}</span></p>
        </div>
      </section>

      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h3 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Your Public Page</h3>
        </div>
        <div class="p-4 space-y-5 text-xs text-slate-600 dark:text-zink-200">
          <?php $slug = $user['slug']; ?>
          {% if user.slug|notempty %}
          <div class="flex justify-center gap-2">
            {% cmp="btn" href="/profile/{$slug}" variant="slate" icon="external-link" label="Profile Preview" %}
          </div>
          {% else %}
          <p class="text-center text-[11px] text-slate-500 dark:text-zink-300">
            Set a public profile URL above to enable your public page.
          </p>
          {% endif %}
        </div>
      </section>
    </aside>
  </div>
</div>
{% endblock %}

{% block scripts %}
{% include "partials/dashboard/_form_error_validity.lex.php" %}

<script>
  // handle avatar upload preview and validation
  (function () {
    const avatarInput = document.getElementById('avatar-input');
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

    avatarInput.addEventListener('change', function (e) {
      const file = e.target.files[0];

      if (!file) {
        resetUploadUI();
        return;
      }

      const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
      if (!validTypes.includes(file.type)) {
        alert('Please select a valid image file (JPG, PNG, or WebP).');
        resetUploadUI();
        return;
      }

      const maxSize = parseInt(avatarInput.dataset.maxSize) || 2097152;
      if (file.size > maxSize) {
        alert('File size must be less than 2MB.');
        resetUploadUI();
        return;
      }

      fileNameSpan.textContent = file.name;
      uploadInstructions.classList.add('hidden');
      selectedFileDiv.classList.remove('hidden');
      uploadActions.classList.remove('hidden');

      const reader = new FileReader();
      reader.onload = function (e) {
        const previewImg = document.getElementById('avatar-preview');
        const placeholder = document.getElementById('avatar-placeholder');

        if (previewImg) {
          previewImg.src = e.target.result;
        } else if (placeholder) {
          const img = document.createElement('img');
          img.id = 'avatar-preview';
          img.src = e.target.result;
          img.className = 'w-full h-full rounded-full object-cover';
          placeholder.replaceWith(img);
        }
      };
      reader.readAsDataURL(file);
    });

    if (clearFileBtn) {
      clearFileBtn.addEventListener('click', function () {
        resetUploadUI();
      });
    }

    if (avatarForm) {
      avatarForm.addEventListener('submit', function () {
        // loading state clears on the redirect back to this page
        uploadActions.classList.add('hidden');
        selectedFileDiv.classList.add('hidden');
        uploadLoading.classList.remove('hidden');
      });
    }

    if (removeAvatarBtn && removeAvatarForm) {
      removeAvatarBtn.addEventListener('click', function () {
        if (confirm('Are you sure you want to remove your profile avatar?')) {
          removeAvatarForm.submit();
        }
      });
    }

    function resetUploadUI() {
      avatarInput.value = '';

      selectedFileDiv.classList.add('hidden');
      uploadActions.classList.add('hidden');
      uploadInstructions.classList.remove('hidden');

      const previewImg = document.getElementById('avatar-preview');
      if (originalAvatarUrl && previewImg) {
        previewImg.src = originalAvatarUrl;
      } else if (!originalAvatarUrl && previewImg) {
        const placeholder = document.createElement('div');
        placeholder.id = 'avatar-placeholder';
        placeholder.className = 'flex items-center justify-center w-full h-full text-2xl font-semibold text-slate-600 dark:text-zink-100';
        placeholder.textContent = '{{ user.initials }}';
        previewImg.replaceWith(placeholder);
      }
    }
  })();
</script>
{% endblock %}
