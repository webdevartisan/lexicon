<?php if (isset($current_path) && \App\Services\Traffic\PlatformPages::counts((string) $current_path) && app(\App\Services\Traffic\TrafficSettings::class)->enabled()) { ?>
<script src="/assets/js/traffic.js" defer></script>
<?php } ?>
