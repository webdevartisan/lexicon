<?php if (app(\App\Services\Analytics\AnalyticsSettings::class)->enabled()) { ?>
<?php if (!empty($settings['analytics_public_notice'])) { ?>
<p class="lx-insights-notice">
  <?= e($t('analytics.publicNotice')) ?>
  <a href="<?= e(lurl('/privacy')) ?>"><?= e($t('analytics.publicNoticeLink')) ?></a>
</p>
<?php } ?>
<script src="/assets/js/insights.js" defer></script>
<?php } ?>
