{% extends "back.lex.php" %}

{% block title %}{{ blog.blog_name }} · Subscribers{% endblock %}
{% block subtitle %}Readers who left their email to hear about new posts on this blog.{% endblock %}
{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">
  <div class="card">
    <div class="card-body">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
          <h6 class="text-15">Subscribers</h6>
          <p class="text-xs text-slate-500 dark:text-zink-300 mt-0.5">
            <?= (int) $total ?> subscriber<?= (int) $total === 1 ? '' : 's' ?> &middot; notified by email when a post is published
          </p>
        </div>

        <form method="get" action="/dashboard/blog/{{ blog.id }}/subscribers" class="flex items-center gap-2">
          <input
            type="search"
            name="q"
            value="<?= e($q ?? '') ?>"
            placeholder="Search by email"
            class="form-input w-56 border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:bg-zink-700 dark:text-zink-100 placeholder:text-slate-400 dark:placeholder:text-zink-200">
          {% cmp="btn" type="submit" variant="slate" icon="search" label="Search" %}
        </form>
      </div>

      <?php if (empty($subscribers)) { ?>
        <div class="py-10 text-center text-slate-500 dark:text-zink-300">
          <i data-lucide="mail-open" class="size-8 mx-auto mb-2 opacity-60"></i>
          <?php if (($q ?? '') !== '') { ?>
            <p class="text-sm">No subscribers match "<?= e($q) ?>".</p>
          <?php } else { ?>
            <p class="text-sm">No subscribers yet. The subscribe form on your public blog page feeds this list.</p>
          <?php } ?>
        </div>
      <?php } else { ?>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="ltr:text-left rtl:text-right">
              <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-300 border-b border-slate-200 dark:border-zink-500">
                <th class="px-3 py-2.5 font-semibold">Email</th>
                <th class="px-3 py-2.5 font-semibold">Account</th>
                <th class="px-3 py-2.5 font-semibold">Subscribed</th>
                <th class="px-3 py-2.5 font-semibold ltr:text-right rtl:text-left">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($subscribers as $sub) { ?>
              <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0">
                <td class="px-3 py-2.5 text-sm text-slate-800 dark:text-zink-100"><?= e($sub['email']) ?></td>
                <td class="px-3 py-2.5 text-sm text-slate-500 dark:text-zink-300">
                  <?php if (!empty($sub['username'])) { ?>
                    <span class="inline-flex items-center gap-1">
                      <i data-lucide="user" class="size-3.5"></i><?= e($sub['username']) ?>
                    </span>
                  <?php } else { ?>
                    <span class="text-slate-400 dark:text-zink-400">Guest</span>
                  <?php } ?>
                </td>
                <td class="px-3 py-2.5 text-sm text-slate-500 dark:text-zink-300">
                  <?= e(local_datetime($sub['created_at'] ?? null, 'M j, Y')) ?>
                </td>
                <td class="px-3 py-2.5 ltr:text-right rtl:text-left">
                  <form method="post" action="/dashboard/blog/{{ blog.id }}/subscribers/<?= (int) $sub['id'] ?>/delete" class="inline"
                        onsubmit="return confirm('Remove <?= e($sub['email']) ?> from the subscriber list?');">
                    {{ csrf_field() }}
                    <button type="submit"
                      class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded border border-red-200 text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">
                      <i data-lucide="trash-2" class="size-3.5"></i> Remove
                    </button>
                  </form>
                </td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>

        <?php
          $totalPages = max(1, (int) ceil(($total ?? 0) / max(1, (int) ($perPage ?? 25))));
          $currentPage = (int) ($page ?? 1);
          $qs = ($q ?? '') !== '' ? '&q='.rawurlencode($q) : '';
          ?>
        <?php if ($totalPages > 1) { ?>
        <div class="flex items-center justify-between mt-4 text-xs text-slate-500 dark:text-zink-300">
          <span>Page <?= $currentPage ?> of <?= $totalPages ?></span>
          <div class="flex gap-2">
            <?php if ($currentPage > 1) { ?>
              <a href="/dashboard/blog/{{ blog.id }}/subscribers?page=<?= $currentPage - 1 ?><?= $qs ?>"
                 class="px-3 py-1.5 border rounded border-slate-200 dark:border-zink-500 hover:bg-slate-50 dark:hover:bg-zink-600">Previous</a>
            <?php } ?>
            <?php if ($currentPage < $totalPages) { ?>
              <a href="/dashboard/blog/{{ blog.id }}/subscribers?page=<?= $currentPage + 1 ?><?= $qs ?>"
                 class="px-3 py-1.5 border rounded border-slate-200 dark:border-zink-500 hover:bg-slate-50 dark:hover:bg-zink-600">Next</a>
            <?php } ?>
          </div>
        </div>
        <?php } ?>
      <?php } ?>
    </div>
  </div>
</div>
{% endblock %}
