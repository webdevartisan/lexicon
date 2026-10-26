<?php
/**
 * Offers to show dates on this device's clock when it reads a different time
 * from the zone the account picked. Accounts on Automatic already follow the
 * device, so they never see it. Stays hidden until zone-prompt.js compares the
 * two, since only the browser knows which zone it is in. Left out while an admin
 * is signed in as someone else, because the device is the admin's, not theirs.
 *
 * No early return: includes are inlined into the layout at compile time.
 */
$zonePromptAccount = auth()->check() && empty($impersonation)
    ? app(\App\Services\AccountZone::class)->chosen((int) auth()->user()['id'])
    : null;
if ($zonePromptAccount !== null) {
    $zonePromptLabel = \App\Helpers\TimezoneHelper::label($zonePromptAccount);
    // The device's zone is filled in by the browser, so each {device} becomes an empty slot for it.
    $zonePromptFill = static fn (string $text): string => implode('<span data-zone-device></span>', array_map('e', explode('{device}', $text)));
?>
<div data-zone-prompt data-account-zone="<?= e($zonePromptAccount) ?>" role="region" aria-label="<?= e($t('dashboard.zonePrompt.label')) ?>" hidden>
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 mt-4 text-sm rounded-md border border-custom-200 bg-custom-50 text-slate-700 dark:bg-custom-500/10 dark:border-custom-500/30 dark:text-zink-100 print:hidden">
        {% cache 'lucide:zone-prompt:clock' ttl=31536000 %}<i data-lucide="clock" class="size-4 shrink-0 text-custom-500" aria-hidden="true"></i>{% endcache %}
        <p class="grow"><?= $zonePromptFill($t('dashboard.zonePrompt.text', ['account' => $zonePromptLabel])) ?></p>
        <form method="post" action="<?= e(lurl('/account/preferences/timezone')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="timezone" data-zone-field>
            <input type="hidden" name="return_to" value="<?= e(lurl($current_path ?? '/dashboard')) ?>">
            <button type="submit" class="font-medium text-custom-600 underline hover:no-underline dark:text-custom-200"><?= $zonePromptFill($t('dashboard.zonePrompt.use')) ?></button>
        </form>
        <button type="button" data-zone-keep class="underline hover:no-underline"><?= e($t('dashboard.zonePrompt.keep', ['account' => $zonePromptLabel])) ?></button>
    </div>
</div>
<?php } ?>
