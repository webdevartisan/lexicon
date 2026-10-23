<?php
$consentService = \Framework\Core\App::container()->get(\App\Services\ConsentService::class);
?>

<link rel="stylesheet" href="<?= e(versioned_asset('/assets/css/consent.css')) ?>">
<script defer src="<?= e(versioned_asset('/assets/js/consent.js')) ?>" data-consent-version="<?= (int) $consentService->version() ?>"></script>
