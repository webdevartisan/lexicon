<?php
// Reusable front confirmation dialog. Set these before the include:
//   $cmId       unique DOM id for the modal
//   $cmTitle    heading text
//   $cmMessage  body text
//   $cmConfirm  confirm-button label
//   $cmCancel   cancel-button label
//   $cmFormId   id of the form submitted when the user confirms
//   $cmTone     'danger' (default) or 'primary' for the confirm button
//
// Behaviour is wired by _confirm_modal_js.lex.php: a control carrying
// data-confirm-open="<id>" opens it, and confirming submits $cmFormId.
$cmTone = ($cmTone ?? 'danger') === 'primary' ? 'lx-btn--primary' : 'lx-btn--danger';
?>
<div id="<?= e($cmId) ?>" class="lx-modal" hidden role="dialog" aria-modal="true"
     aria-labelledby="<?= e($cmId) ?>-title" data-confirm-form="<?= e($cmFormId) ?>">
    <div class="lx-modal-backdrop" data-close></div>
    <div class="lx-modal-panel">
        <h3 class="lx-modal-title" id="<?= e($cmId) ?>-title"><?= e($cmTitle) ?></h3>
        <p class="lx-modal-text"><?= e($cmMessage) ?></p>
        <div class="lx-modal-actions">
            <button type="button" class="lx-btn lx-btn--subtle" data-close><?= e($cmCancel) ?></button>
            <button type="button" class="lx-btn <?= $cmTone ?>" data-confirm-ok><?= e($cmConfirm) ?></button>
        </div>
    </div>
</div>
