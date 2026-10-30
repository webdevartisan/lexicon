<link rel="stylesheet" href="<?= e(versioned_asset('/assets/css/blog-confirm.css')) ?>">
<script defer src="<?= e(versioned_asset('/assets/js/blog-confirm.js')) ?>" data-lang="<?= e($chromeLang) ?>" data-dir="<?= e($chromeDir) ?>"
        data-text="<?= e(json_encode(['label' => $t('blogConfirm.label'), 'title' => $t('blogConfirm.title'), 'cancel' => $t('blogConfirm.cancel'), 'confirm' => $t('blogConfirm.confirm')], JSON_UNESCAPED_UNICODE)) ?>"></script>
