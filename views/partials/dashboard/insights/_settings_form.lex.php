<?php
$pathErrors = $errors['excluded_paths'] ?? [];
$pathsValue = $pathErrors !== [] ? (string) old('excluded_paths', '') : (string) ($blogSettings['analytics_excluded_paths'] ?? '');
$pathsLabel = $t('analytics.settings.excludedPaths');
$pathsHint = e($t('analytics.settings.excludedPathsHint'));
$toggles = [
    ['exclude_members', !empty($blogSettings['analytics_exclude_members'] ?? 1), 'analytics.settings.excludeMembers', 'analytics.settings.excludeMembersHint'],
    ['public_notice', !empty($blogSettings['analytics_public_notice'] ?? 0), 'analytics.settings.publicNotice', 'analytics.settings.publicNoticeHint'],
    ['popular_posts', !empty($blogSettings['analytics_popular_posts'] ?? 0), 'analytics.settings.popularPosts', 'analytics.settings.popularPostsHint'],
    ['public_stats', !empty($blogSettings['analytics_public_stats'] ?? 0), 'analytics.settings.publicStats', 'analytics.settings.publicStatsHint'],
];
?>
<form method="post" action="<?= e(lurl($basePath.'/settings')) ?>" id="analyticsSettingsForm" class="flex flex-col gap-4">
  <?= csrf_field() ?>
  <?php foreach ($toggles as [$field, $checked, $labelKey, $hintKey]) { ?>
  <div class="flex items-start gap-2">
    <input id="analytics_<?= e($field) ?>" name="<?= e($field) ?>" type="checkbox" value="1"
      class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600"<?= $checked ? ' checked' : '' ?>>
    <div>
      <label for="analytics_<?= e($field) ?>" class="text-xs font-medium text-slate-800 dark:text-zink-100"><?= e($t($labelKey)) ?></label>
      <p class="mt-1 text-[11px] text-slate-500 dark:text-zink-300"><?= e($t($hintKey)) ?></p>
    </div>
  </div>
  <?php } ?>
  {% cmp="input" type="textarea" name="excluded_paths" label="{$pathsLabel}" value="{$pathsValue}" placeholder="/tag/drafts" rows="4" underlabel="{$pathsHint}" %}
</form>
