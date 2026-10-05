<?php
$pathErrors = $errors['excluded_paths'] ?? [];
$pathsValue = $pathErrors !== [] ? (string) old('excluded_paths', '') : (string) ($blogSettings['traffic_excluded_paths'] ?? '');
$pathsLabel = $t('traffic.settings.excludedPaths');
$pathsHint = e($t('traffic.settings.excludedPathsHint'));
$toggles = [
    ['exclude_members', !empty($blogSettings['traffic_exclude_members'] ?? 1), 'traffic.settings.excludeMembers', 'traffic.settings.excludeMembersHint'],
    ['public_notice', !empty($blogSettings['traffic_public_notice'] ?? 0), 'traffic.settings.publicNotice', 'traffic.settings.publicNoticeHint'],
    ['popular_posts', !empty($blogSettings['traffic_popular_posts'] ?? 0), 'traffic.settings.popularPosts', 'traffic.settings.popularPostsHint'],
    ['public_stats', !empty($blogSettings['traffic_public_stats'] ?? 0), 'traffic.settings.publicStats', 'traffic.settings.publicStatsHint'],
];
?>
<form method="post" action="<?= e(lurl($basePath.'/settings')) ?>" id="trafficSettingsForm" class="flex flex-col gap-4">
  <?= csrf_field() ?>
  <?php foreach ($toggles as [$field, $checked, $labelKey, $hintKey]) { ?>
  <div class="flex items-start gap-2">
    <input id="traffic_<?= e($field) ?>" name="<?= e($field) ?>" type="checkbox" value="1"
      class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600"<?= $checked ? ' checked' : '' ?>>
    <div>
      <label for="traffic_<?= e($field) ?>" class="text-xs font-medium text-slate-800 dark:text-zink-100"><?= e($t($labelKey)) ?></label>
      <p class="mt-1 text-[11px] text-slate-500 dark:text-zink-300"><?= e($t($hintKey)) ?></p>
    </div>
  </div>
  <?php } ?>
  {% cmp="input" type="textarea" name="excluded_paths" label="{$pathsLabel}" value="{$pathsValue}" placeholder="/tag/drafts" rows="4" underlabel="{$pathsHint}" %}
</form>
