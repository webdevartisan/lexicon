<?php if (!empty($settings['traffic_enabled']) && app(\App\Services\Traffic\TrafficSettings::class)->enabled()) { ?>
<?php if (!empty($settings['traffic_public_notice'])) { ?>
<p class="lx-traffic-notice">
  <?= e($t('traffic.publicNotice')) ?>
  <a href="<?= e(lurl('/privacy')) ?>"><?= e($t('traffic.publicNoticeLink')) ?></a>
</p>
<?php } ?>
<script src="/assets/js/traffic.js" defer></script>
<?php } ?>
