<?php
$consentService = \Framework\Core\App::container()->get(\App\Services\ConsentService::class);
$currentConsent = $consentService->current();
?>

<script id="consent-state" type="application/json">
<?= $currentConsent ? json_encode($currentConsent->toPayload(), JSON_UNESCAPED_SLASHES) : 'null' ?>
</script>

<link rel="stylesheet" href="/assets/css/consent.css">
<script defer src="/assets/js/consent.js"></script>
