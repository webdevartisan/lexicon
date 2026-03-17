<?php
[$classContainer, $classBtn] = match ($type) {
    'success' => ['relative p-3 pr-12 text-sm bg-green-500 border border-transparent rounded-md text-green-50',
        'absolute top-0 bottom-0 right-0 p-3 text-green-200 transition hover:text-green-100'],
    'error' => ['relative p-3 pr-12 text-sm bg-red-500 border border-transparent rounded-md text-red-50',
        'absolute top-0 bottom-0 right-0 p-3 text-red-200 transition hover:text-red-100'],
    'warning' => ['relative p-3 pr-12 text-sm bg-orange-500 border border-transparent rounded-md text-orange-50',
        'absolute top-0 bottom-0 right-0 p-3 text-orange-200 transition hover:text-orange-100'],
    'info' => ['relative p-3 pr-12 text-sm border border-transparent rounded-md text-custom-50 bg-custom-500',
        'absolute top-0 bottom-0 right-0 p-3 transition text-custom-200 hover:text-custom-100'],
    default => ['relative p-3 pr-12 text-sm border border-transparent rounded-md text-slate-50 bg-slate-500 dark:bg-zink-500 dark:border-zink-500 dark:text-zink-100',
        'absolute top-0 bottom-0 right-0 p-3 transition text-slate-200 hover:text-slate-100'],
};

$type = ucwords($type);
?>

<div class="mb-4 mt-4 {{ classContainer }}" data-closable>
    <button class="{{ classBtn }}"><i data-lucide="x" class="h-5" data-close></i></button>
    <span class="font-bold"><b>{{ type }}:</b></span> {{ msg }}
</div>

<script>
(function () {
  function onCloseClick(e) {
    const btn = e.target.closest('[data-close]');
    if (!btn) return;

    const box = btn.closest('[data-closable]');
    if (!box) return;

    box.remove();

    if (!document.querySelector('[data-closable]')) {
      document.removeEventListener('click', onCloseClick);
    }
  }

  document.addEventListener('click', onCloseClick);
})();
</script>
