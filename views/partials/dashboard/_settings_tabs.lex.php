<?php
// Settings navigation. Every tab is a real page rather than a JS panel, so a
// save can redirect back to the tab it was made on and each section stays
// linkable on its own.
$settingsActive = $settingsTab ?? 'profile';

$settingsSections = [
    'profile' => ['label' => 'Profile', 'href' => '/dashboard/profile', 'icon' => 'user-2'],
    'account' => ['label' => 'Account', 'href' => '/dashboard/account', 'icon' => 'settings'],
    'security' => ['label' => 'Security', 'href' => '/dashboard/account/security', 'icon' => 'lock'],
    'notifications' => ['label' => 'Email notifications', 'href' => '/dashboard/account/notifications', 'icon' => 'bell-ring'],
];

$settingsTabOn = 'bg-custom-500 text-white border-custom-500 dark:bg-custom-600 dark:border-custom-600';
$settingsTabOff = 'bg-white text-slate-600 border-slate-200 hover:border-custom-400 hover:text-custom-600 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:border-custom-500';
?>
<div class="mt-6">
  <nav class="flex flex-wrap items-center gap-1.5" aria-label="Settings sections">
    <?php foreach ($settingsSections as $settingsKey => $settingsSection) { ?>
      <a href="<?= lurl($settingsSection['href']) ?>"
         class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border transition-colors <?= $settingsActive === $settingsKey ? $settingsTabOn : $settingsTabOff ?>"
         <?= $settingsActive === $settingsKey ? 'aria-current="page"' : '' ?>>
        <i data-lucide="<?= e($settingsSection['icon']) ?>" class="size-3.5"></i>
        <?= e($settingsSection['label']) ?>
      </a>
    <?php } ?>
  </nav>
</div>
