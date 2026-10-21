<?php
$consentService = \Framework\Core\App::container()->get(\App\Services\ConsentService::class);
?>

<link rel="stylesheet" href="<?= e(platform_asset('assets/css/consent.css')) ?>">
<script defer src="/assets/js/consent.js" data-consent-version="<?= (int) $consentService->version() ?>"></script>
