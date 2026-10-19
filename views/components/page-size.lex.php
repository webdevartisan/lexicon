<?php
/**
 * "Show N entries" selector for the admin list tables.
 *
 * @var int $perPage - the page size currently in effect
 */
$perPage = (int) ($perPage ?? 20);
?>
<div class="flex items-center gap-2 mb-3 text-sm text-slate-500 dark:text-zink-300">
    <label for="per-page">Show</label>
    <select id="per-page" data-page-size class="form-select w-auto py-1.5 ps-3 pe-8 text-sm rounded-lg border border-slate-200 bg-white text-slate-700 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-100">
        <?php foreach (\App\ValueObjects\PageSize::CHOICES as $choice) { ?>
        <option value="<?= $choice ?>" <?= $choice === $perPage ? 'selected' : '' ?>><?= $choice ?></option>
        <?php } ?>
    </select>
    <span>entries</span>
</div>
