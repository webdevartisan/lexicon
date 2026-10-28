  <?php
  // Always printed, empty or not: page scripts put a refusal in the same spot with the template below.
  $flashDismiss = chrome_translate('a11y.dismiss');
  ?>
  <link rel="stylesheet" href="<?= e(versioned_asset('/assets/css/theme-toast.css')) ?>">
  <div class="flash-wrap" data-lx-toast-host>
    <?php foreach ($flashes ?? [] as $type => $messages) { ?>
      <?php
        $class = match ($type) {
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info',
        };
        ?>
      <?php foreach ($messages as $message) { ?>
        <div class="alert <?= $class ?>" role="alert" data-lx-toast data-auto-close="6000">
          <span data-lx-toast-msg><?= e($message) ?></span>
          <button type="button" class="theme-toast-close" data-lx-toast-close aria-label="<?= e($flashDismiss) ?>">&times;</button>
        </div>
      <?php } ?>
    <?php } ?>
    <template data-lx-toast-template>
      <div class="alert alert-danger" role="alert" data-lx-toast data-auto-close="6000">
        <span data-lx-toast-msg></span>
        <button type="button" class="theme-toast-close" data-lx-toast-close aria-label="<?= e($flashDismiss) ?>">&times;</button>
      </div>
    </template>
  </div>
  <script src="<?= e(versioned_asset('/assets/js/flash-toast.js')) ?>" defer></script>
