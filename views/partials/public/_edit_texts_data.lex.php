<?php
$textFields = [];
foreach ($edit_texts['fields'] as $key => $text) {
    $label = App\Services\FrontPageTexts::LABELS[$key] ?? '';
    $textFields[$key] = $text + ['label' => ucfirst($label)];
}
?>
<script type="application/json" data-edit-fields
        data-save-url="<?= e($edit_texts['save_url']) ?>"
        data-locale="<?= e($edit_texts['locale']) ?>"
        data-wording-locale="<?= e($edit_texts['wording_locale']) ?>"><?= json_encode($textFields, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
