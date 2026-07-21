{% extends "back.lex.php" %}

{% block title %}Email notifications{% endblock %}

{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">

  <?php $settingsTab = 'notifications'; ?>
  {% include "partials/dashboard/_settings_tabs.lex.php" %}

  <div class="grid gap-6 mt-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">

      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Email Notifications</h2>
          <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">
            Choose what gets emailed to you. In-app alerts stay in your notifications feed either way.
          </p>
        </div>
        <div class="p-4 md:p-5">
          <form method="post" action="/dashboard/account/notifications/update">
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

              <label class="flex items-start gap-2">
                <input type="checkbox" name="notify_post_status" value="1"
                  class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600" {{
                  user.notify_post_status ? 'checked' : '' }}>
                <span>
                  <span class="font-medium">Email me on post review activity</span><br>
                  <span class="text-[11px] text-slate-500 dark:text-zink-300">Submissions, approvals, changes requested, and publish events.</span>
                </span>
              </label>

              <label class="flex items-start gap-2">
                <input type="checkbox" name="notify_role_changes" value="1"
                  class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600" {{
                  user.notify_role_changes ? 'checked' : '' }}>
                <span>
                  <span class="font-medium">Email me on collaborator role changes</span><br>
                  <span class="text-[11px] text-slate-500 dark:text-zink-300">When your role is updated or your access is revoked.</span>
                </span>
              </label>

              <label class="flex items-start gap-2">
                <input type="checkbox" name="notify_invites" value="1"
                  class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600" {{
                  user.notify_invites ? 'checked' : '' }}>
                <span>
                  <span class="font-medium">Email me on blog invite activity</span><br>
                  <span class="text-[11px] text-slate-500 dark:text-zink-300">New invites you receive and invites you sent being declined.</span>
                </span>
              </label>
            </div>

            <div class="flex justify-end mt-6">
              {% cmp="btn" type="submit" variant="blue" icon="bell-ring" label="Save notification settings" %}
            </div>
          </form>
        </div>
      </section>

    </div>
  </div>
</div>
{% endblock %}
