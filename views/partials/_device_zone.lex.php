<?php
/**
 * Hands device-zone.js what it needs to tell the server this device's zone, so
 * an account on Automatic reads dates on the device's clock. Signed-in pages
 * only: those are never page cached, so the zone and token here stay this
 * session's own.
 *
 * No early return: includes are inlined into the layout at compile time.
 */
if (auth()->check()) {
?>
<meta name="device-zone" content="<?= e(app(\App\Services\AccountZone::class)->reportedDevice() ?? '') ?>" data-url="<?= e(lurl('/account/device-zone')) ?>" data-token="<?= e(csrf()->getToken()) ?>">
<script src="<?= e(versioned_asset('/assets/js/device-zone.js')) ?>" defer></script>
<?php } ?>
