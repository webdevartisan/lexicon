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

            <div class="space-y-6 text-xs text-slate-700 dark:text-zink-100">

              <div>
                <h3 class="text-[11px] font-semibold tracking-wide uppercase text-slate-400 dark:text-zink-300">
                  Your posts
                </h3>
                <div class="mt-3 space-y-3">
                  <label class="flex items-start gap-2">
                    <input type="checkbox" name="notify_post_status" value="1"
                      class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600" {{
                      user.notify_post_status ? 'checked' : '' }}>
                    <span>
                      <span class="font-medium">Activity on posts you wrote</span><br>
                      <span class="text-[11px] text-slate-500 dark:text-zink-300">
                        Approvals, changes requested, posts going live, and a post being reset to draft
                        when review is switched off.
                      </span>
                    </span>
                  </label>
                </div>
              </div>

              <div>
                <h3 class="text-[11px] font-semibold tracking-wide uppercase text-slate-400 dark:text-zink-300">
                  Reviews assigned to you
                </h3>
                <div class="mt-3 space-y-3">
                  <label class="flex items-start gap-2">
                    <input type="checkbox" name="notify_review_requests" value="1"
                      class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600" {{
                      user.notify_review_requests ? 'checked' : '' }}>
                    <span>
                      <span class="font-medium">Review work for you</span><br>
                      <span class="text-[11px] text-slate-500 dark:text-zink-300">
                        A draft submitted for your review, being assigned as reviewer, and your assignment
                        moving on without you.
                      </span>
                    </span>
                  </label>
                </div>
              </div>

              <div>
                <h3 class="text-[11px] font-semibold tracking-wide uppercase text-slate-400 dark:text-zink-300">
                  Comments
                </h3>
                <p class="mt-1 text-[11px] text-slate-400 dark:text-zink-300">
                  A single comment only ever emails you once, using the most personal reason that fits.
                  Your own comments never notify you.
                </p>
                <div class="mt-3 space-y-3">
                  <label class="flex items-start gap-2">
                    <input type="checkbox" name="notify_comment_replies" value="1"
                      class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600" {{
                      user.notify_comment_replies ? 'checked' : '' }}>
                    <span>
                      <span class="font-medium">Replies to your comments</span><br>
                      <span class="text-[11px] text-slate-500 dark:text-zink-300">
                        When someone replies to a comment you left, anywhere.
                      </span>
                    </span>
                  </label>

                  <label class="flex items-start gap-2">
                    <input type="checkbox" name="notify_comments_authored" value="1"
                      class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600" {{
                      user.notify_comments_authored ? 'checked' : '' }}>
                    <span>
                      <span class="font-medium">Comments on posts you wrote</span><br>
                      <span class="text-[11px] text-slate-500 dark:text-zink-300">
                        New comments on any post you are the author of.
                      </span>
                    </span>
                  </label>

                  <label class="flex items-start gap-2">
                    <input type="checkbox" name="notify_comments_moderation" value="1"
                      class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600" {{
                      user.notify_comments_moderation ? 'checked' : '' }}>
                    <span>
                      <span class="font-medium">Comments awaiting your moderation</span><br>
                      <span class="text-[11px] text-slate-500 dark:text-zink-300">
                        When a comment is held for approval on a blog you own or edit.
                      </span>
                    </span>
                  </label>

                  <label class="flex items-start gap-2">
                    <input type="checkbox" name="notify_comments_blog" value="1"
                      class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600" {{
                      user.notify_comments_blog ? 'checked' : '' }}>
                    <span>
                      <span class="font-medium">Comments on blogs you own</span><br>
                      <span class="text-[11px] text-slate-500 dark:text-zink-300">
                        Every comment across a blog you own, even ones that don't need your action.
                      </span>
                    </span>
                  </label>
                </div>
              </div>

              <div>
                <h3 class="text-[11px] font-semibold tracking-wide uppercase text-slate-400 dark:text-zink-300">
                  Team and access
                </h3>
                <div class="mt-3 space-y-3">
                  <label class="flex items-start gap-2">
                    <input type="checkbox" name="notify_role_changes" value="1"
                      class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600" {{
                      user.notify_role_changes ? 'checked' : '' }}>
                    <span>
                      <span class="font-medium">Role and access changes</span><br>
                      <span class="text-[11px] text-slate-500 dark:text-zink-300">
                        When your role on a blog is updated, or your access is revoked.
                      </span>
                    </span>
                  </label>

                  <label class="flex items-start gap-2">
                    <input type="checkbox" name="notify_invites" value="1"
                      class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600" {{
                      user.notify_invites ? 'checked' : '' }}>
                    <span>
                      <span class="font-medium">Replies to invitations you sent</span><br>
                      <span class="text-[11px] text-slate-500 dark:text-zink-300">
                        When someone declines an invitation to a blog you own.
                      </span>
                    </span>
                  </label>
                </div>
              </div>

            </div>

            <div class="flex justify-end mt-6">
              {% cmp="btn" type="submit" variant="blue" icon="bell-ring" label="Save notification settings" %}
            </div>
          </form>
        </div>
      </section>

      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Always sent</h2>
          <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">
            These are not covered by the settings above, because turning them off would leave you stuck.
          </p>
        </div>
        <div class="p-4 md:p-5 space-y-4 text-xs text-slate-700 dark:text-zink-100">
          <div>
            <span class="font-medium">Invitations you receive</span><br>
            <span class="text-[11px] text-slate-500 dark:text-zink-300">
              The link that lets you join a blog only exists inside that email, so it is always delivered.
            </span>
          </div>
          <div>
            <span class="font-medium">Account emails</span><br>
            <span class="text-[11px] text-slate-500 dark:text-zink-300">
              Welcome and password reset messages.
            </span>
          </div>
          <div>
            <span class="font-medium">Blogs you follow</span><br>
            <span class="text-[11px] text-slate-500 dark:text-zink-300">
              New posts from blogs you subscribed to. Manage those per blog on your
              <a href="<?= lurl('/subscriptions') ?>"
                 class="font-medium underline text-custom-500 hover:text-custom-600">subscriptions page</a>.
            </span>
          </div>
        </div>
      </section>

    </div>
  </div>
</div>
{% endblock %}
