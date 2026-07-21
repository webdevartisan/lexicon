{% extends "back.lex.php" %}

{% block title %}Security{% endblock %}

{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">

  <?php $settingsTab = 'security'; ?>
  {% include "partials/dashboard/_settings_tabs.lex.php" %}

  <div class="grid gap-6 mt-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">

      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Change Password</h2>
          <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">
            Use a strong password you don't reuse anywhere else.
          </p>
        </div>
        <div class="p-4 md:p-5">
          <form method="post" action="/dashboard/account/security/password">
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

    </div>

    <aside class="space-y-6 max-w-xs">
      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h3 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Sign-in Activity</h3>
        </div>
        <div class="p-4 space-y-2 text-xs text-slate-600 dark:text-zink-200">
          <p class="flex items-center justify-between">Last Login
            <span class="font-semibold text-slate-900 dark:text-zink-100">
              {% if user.last_login|notempty %}{{ user.last_login }}{% else %}Never{% endif %}
            </span></p>
        </div>
      </section>
    </aside>
  </div>
</div>
{% endblock %}

{% block scripts %}
{% include "partials/dashboard/_form_error_validity.lex.php" %}
{% endblock %}
