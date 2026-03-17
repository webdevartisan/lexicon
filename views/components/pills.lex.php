<div class="card">
    <div class="card-body">
        <h6 class="mb-4 text-15">Pills Badge</h6>
        <div class="flex flex-wrap items-center gap-2">
            <span class="px-2.5 py-0.5 inline-block text-xs font-medium rounded-full border bg-custom-100 border-transparent text-custom-500 dark:bg-custom-500/20 dark:border-transparent">Custom</span>
            <span class="px-2.5 py-0.5 inline-block text-xs font-medium rounded-full border bg-green-100 border-transparent text-green-500 dark:bg-green-500/20 dark:border-transparent">Green</span>
            <span class="px-2.5 py-0.5 inline-block text-xs font-medium rounded-full border bg-orange-100 border-transparent text-orange-500 dark:bg-orange-500/20 dark:border-transparent">Orange</span>
            <span class="px-2.5 py-0.5 inline-block text-xs font-medium rounded-full border bg-sky-100 border-transparent text-sky-500 dark:bg-sky-500/20 dark:border-transparent">Sky</span>
            <span class="px-2.5 py-0.5 inline-block text-xs font-medium rounded-full border bg-yellow-100 border-transparent text-yellow-500 dark:bg-yellow-500/20 dark:border-transparent">Yellow</span>
            <span class="px-2.5 py-0.5 inline-block text-xs font-medium rounded-full border bg-red-100 border-transparent text-red-500 dark:bg-red-500/20 dark:border-transparent">Red</span>
            <span class="px-2.5 py-0.5 inline-block text-xs font-medium rounded-full border bg-purple-100 border-transparent text-purple-500 dark:bg-purple-500/20 dark:border-transparent">Purple</span>
            <span class="px-2.5 py-0.5 inline-block text-xs font-medium rounded-full border bg-slate-100 border-transparent text-slate-500 dark:bg-slate-500/20 dark:text-zink-200 dark:border-transparent">Slate</span>
        </div>
    </div>
</div><!--end card-->

<!-- Divider -->
            <div class="border-t border-slate-200 dark:border-zink-600"></div>

            <!-- Current Status Display -->
            <div class="space-y-2.5 text-xs">
              <div class="flex items-center justify-between gap-3">
                <span class="text-slate-500 dark:text-zink-300">Current status</span>
                <?php
                  $badgeBase = 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset';
                $statusBadge = match ($postStatus) {
                    'published' => $badgeBase.' bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-200 dark:ring-emerald-500/30',
                    'scheduled' => $badgeBase.' bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-500/20 dark:text-blue-200 dark:ring-blue-500/30',
                    'pending' => $badgeBase.' bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/20 dark:text-amber-200 dark:ring-amber-500/30',
                    'archived' => $badgeBase.' px-2.5 py-0.5 inline-block text-xs font-medium rounded-full border bg-custom-100 border-transparent text-custom-500 dark:bg-custom-500/20 dark:border-transparent',
                    default => $badgeBase.' bg-slate-100 text-slate-700 ring-slate-200 dark:bg-zink-600/30 dark:text-zink-100 dark:ring-zink-500/30',
                };
                ?>
                <span class="<?= $statusBadge ?>">
                  <?= e(ucfirst($postStatus)) ?>
                </span>

              </div>

              <div class="flex items-center justify-between gap-3">
                <span class="text-slate-500 dark:text-zink-300">Workflow</span>
                <?php
                  $wf = $workflowState ?? ($post['workflow_state'] ?? 'draft');
                $wfBadge = match ($wf) {
                    'in_review' => $badgeBase.' bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/20 dark:text-sky-200 dark:ring-sky-500/30',
                    'needs_changes' => $badgeBase.' bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-500/20 dark:text-amber-200 dark:ring-amber-500/30',
                    'approved' => $badgeBase.' bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-200 dark:ring-emerald-500/30',
                    'ready_to_publish' => $badgeBase.' bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/20 dark:text-indigo-200 dark:ring-indigo-500/30',
                    default => $badgeBase.' bg-slate-100 text-slate-700 ring-slate-200 dark:bg-zink-600/30 dark:text-zink-100 dark:ring-zink-500/30',
                };
                ?>
                <span class="<?= $wfBadge ?>">
                  <?= e(ucfirst(str_replace('_', ' ', $wf))) ?>
                </span>
              </div>

              <?php if (isset($role) && $role !== 'none') { ?>
              <div class="flex items-center justify-between gap-3 pt-2 mt-2 border-t border-dashed border-slate-200 dark:border-zink-600">
                <span class="text-slate-500 dark:text-zink-300">Your role</span>
                <span class="<?= $badgeBase.' bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/20 dark:text-sky-200 dark:ring-sky-500/30' ?>">
                  <?= e(ucfirst($role)) ?>
                </span>
              </div>
              <?php } ?>
            </div>