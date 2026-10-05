<?php
$linkUrlLabel = $t('traffic.links.url');
$linkSourceLabel = $t('traffic.links.source');
$linkSourceHint = e($t('traffic.links.sourceHint'));
$linkMediumLabel = $t('traffic.links.medium');
$linkMediumHint = e($t('traffic.links.mediumHint'));
$linkCampaignLabel = $t('traffic.links.campaign');
$linkCampaignHint = e($t('traffic.links.campaignHint'));
$linkResultLabel = $t('traffic.links.result');
?>
<div class="flex flex-col gap-4" data-link-builder data-copied="<?= e($t('traffic.links.copied')) ?>">
  <p class="text-sm text-slate-600 dark:text-zink-200"><?= e($t('traffic.links.intro')) ?></p>
  {% cmp="input" type="url" name="link_url" label="{$linkUrlLabel}" value="{$blogUrl}" %}
  <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
    {% cmp="input" name="link_source" label="{$linkSourceLabel}" underlabel="{$linkSourceHint}" %}
    {% cmp="input" name="link_medium" label="{$linkMediumLabel}" underlabel="{$linkMediumHint}" %}
    {% cmp="input" name="link_campaign" label="{$linkCampaignLabel}" underlabel="{$linkCampaignHint}" %}
  </div>
  <div>
    <label for="link_result" class="inline-block mb-2 text-base font-medium"><?= e($linkResultLabel) ?></label>
    <div class="flex gap-2">
      <input id="link_result" type="text" readonly dir="ltr"
             class="form-input flex-1 border-slate-200 bg-slate-50 dark:border-zink-500 dark:bg-zink-600 dark:text-zink-100" data-link-result>
      <button type="button" class="px-3 text-sm text-white rounded-md bg-custom-500 hover:bg-custom-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-custom-500"
              data-link-copy><?= e($t('traffic.links.copy')) ?></button>
    </div>
    <p class="mt-1 text-xs text-slate-500 dark:text-zink-300" aria-live="polite" data-link-status></p>
  </div>
</div>
