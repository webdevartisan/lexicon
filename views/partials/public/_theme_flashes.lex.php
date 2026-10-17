  <?php if (!empty($flashes ?? [])) { ?>
  <div class="flash-wrap">
    <?php foreach ($flashes as $type => $messages) { ?>
      <?php
        $class = match ($type) {
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info',
        };
        ?>
      <?php foreach ($messages as $message) { ?>
        <div class="alert <?= $class ?>" role="alert"><?= e($message) ?></div>
      <?php } ?>
    <?php } ?>
  </div>
  <?php } ?>
