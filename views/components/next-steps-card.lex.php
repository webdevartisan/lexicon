<?php
/**
 * "Your next steps" card for the Overview.
 *
 * Rendered fully on the server, so its forms work without JavaScript. With
 * JavaScript, next-steps.js takes over the buttons: hiding, showing and
 * copying happen in place and roll back if the server refuses.
 *
 * Attributes:
 * - setup: GuidedSetupService::forUser() output; nothing renders when it is
 *   empty or dismissed
 * - blogId: the blog the card belongs to
 * - blogUrl: the blog's public address, for "Copy link"
 */
$setup = is_array($setup ?? null) ? $setup : null;
$blogId = (int) ($blogId ?? 0);
$blogUrl = (string) ($blogUrl ?? '');
?>
<?php if ($setup !== null && $setup['state'] !== 'dismissed') {
    $state = $setup['state'];
    $isOwner = $setup['audience'] === 'owner';
    $base = '/dashboard/setup/'.$blogId.'/';
    $sharedAtStart = false;
    $firstOpenKey = null;
    foreach ($setup['steps'] as $step) {
        if ($step->key === 'share') {
            $sharedAtStart = $step->done;
        }
        if ($firstOpenKey === null && !$step->done) {
            $firstOpenKey = $step->key;
        }
    }
    $percent = $setup['total'] > 0 ? (int) round($setup['done'] * 100 / $setup['total']) : 0;
    $config = [
        'state' => $state,
        'done' => $setup['done'],
        'total' => $setup['total'],
        'shared' => $sharedAtStart,
        'blogUrl' => $blogUrl,
        'urls' => [
            'hide' => lurl($base.'hide'),
            'show' => lurl($base.'show'),
            'dismiss' => lurl($base.'dismiss'),
            'copied' => lurl($base.'copied'),
        ],
        'text' => [
            'progress' => $t('setup.card.progress'),
            'collapsed' => $t('setup.card.collapsed'),
            'copy' => $t('setup.steps.share.action'),
            'copied' => $t('setup.card.copied'),
        ],
    ];
    $hiddenUnless = static fn (bool $visible): string => $visible ? '' : ' style="display: none"';
    $ghostButton = 'inline-flex shrink-0 items-center justify-center rounded-md border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 transition-colors hover:border-custom-500 hover:text-custom-600 focus:border-custom-500 focus:outline-none focus:ring focus:ring-custom-100 dark:border-zink-500 dark:text-zink-100 dark:hover:text-custom-300 dark:focus:ring-custom-500/20';
    $csrf = csrf_field();
    ?>
<div class="mb-6" data-next-steps x-data="nextSteps" data-config="<?= e(json_encode($config, JSON_UNESCAPED_SLASHES)) ?>">

  <div x-show="isHidden"<?= $hiddenUnless($state === 'hidden') ?>>
    <form method="post" action="<?= e($config['urls']['show']) ?>" @submit.prevent="show">
      <?= $csrf ?>
      <button type="submit" x-ref="collapsed" class="inline-flex items-center gap-2 text-sm font-medium text-custom-500 underline-offset-2 hover:underline focus:underline focus:decoration-2 focus:outline-none">
        <i data-lucide="list-checks" class="size-4" aria-hidden="true"></i>
        <span x-text="collapsedText"><?= e($t('setup.card.collapsed', ['done' => $setup['done'], 'total' => $setup['total']])) ?></span>
      </button>
    </form>
  </div>

  <section class="card mb-0" aria-labelledby="next-steps-title" x-show="isOpen"<?= $hiddenUnless($state === 'open') ?>>
    <div class="card-body">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 id="next-steps-title" x-ref="heading" tabindex="-1" class="text-base font-semibold text-slate-900 focus:outline-none dark:text-zink-50"><?= e($t('setup.card.title')) ?></h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-zink-300" x-text="progressText"><?= e($t('setup.card.progress', ['done' => $setup['done'], 'total' => $setup['total']])) ?></p>
        </div>
        <form method="post" action="<?= e($config['urls']['hide']) ?>" @submit.prevent="hide">
          <?= $csrf ?>
          <button type="submit" class="text-sm font-medium text-slate-500 underline-offset-2 hover:text-slate-700 hover:underline focus:underline focus:decoration-2 focus:outline-none dark:text-zink-300 dark:hover:text-zink-100"><?= e($t('setup.card.hide')) ?></button>
        </form>
      </div>

      <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-zink-600" role="progressbar" aria-labelledby="next-steps-title" aria-valuemin="0" :aria-valuemax="total" :aria-valuenow="done" aria-valuemax="<?= (int) $setup['total'] ?>" aria-valuenow="<?= (int) $setup['done'] ?>">
        <div class="h-full rounded-full bg-custom-500 transition-[width] duration-500 motion-reduce:transition-none" :style="barStyle" style="width: <?= $percent ?>%"></div>
      </div>

      <ol class="mt-4 divide-y divide-slate-100 dark:divide-zink-600">
        <?php foreach ($setup['steps'] as $step) {
            $isShare = $step->key === 'share';
            $isNext = $step->key === $firstOpenKey;
            ?>
        <li class="py-3 first:pt-0 last:pb-0" data-step="<?= e($step->key) ?>">
          <?php if ($step->done || $isShare) { ?>
          <div class="flex items-start gap-3"<?= $isShare ? ' x-show="shared"'.$hiddenUnless($step->done) : '' ?>>
            <span class="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white" aria-hidden="true">
              <svg viewBox="0 0 20 20" fill="currentColor" class="size-3.5"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
            </span>
            <div class="min-w-0">
              <p class="text-sm text-slate-500 line-through decoration-slate-300 dark:text-zink-300 dark:decoration-zink-500">
                <span class="sr-only"><?= e($t('setup.card.doneLabel')) ?></span><?= e($t($step->titleKey)) ?>
              </p>
              <?php if ($step->doneNoteKey !== null) { ?>
              <p class="mt-0.5 text-xs text-slate-500 dark:text-zink-400"><?= e($t($step->doneNoteKey)) ?></p>
              <?php } ?>
            </div>
          </div>
          <?php } ?>
          <?php if (!$step->done) { ?>
          <div class="flex flex-wrap items-start gap-3 sm:flex-nowrap"<?= $isShare ? ' x-show="notShared"' : '' ?>>
            <span class="mt-0.5 inline-block size-5 shrink-0 rounded-full border-2 <?= $isNext ? 'border-custom-500' : 'border-slate-300 dark:border-zink-500' ?>" aria-hidden="true"></span>
            <div class="min-w-0 grow">
              <p class="text-sm font-medium text-slate-900 dark:text-zink-50">
                <?= e($t($step->titleKey)) ?>
                <?php if ($isNext) { ?>
                <span class="ml-1 rounded-full bg-custom-100 px-2 py-0.5 text-[11px] font-medium text-custom-600 rtl:ml-0 rtl:mr-1 dark:bg-custom-500/20 dark:text-custom-300"><?= e($t('setup.card.next')) ?></span>
                <?php } ?>
              </p>
              <p class="mt-0.5 text-sm text-slate-500 dark:text-zink-300"><?= e($t($step->descriptionKey)) ?></p>
              <?php if ($step->key === 'member_review') { ?>
              <details class="mt-2 text-sm text-slate-600 dark:text-zink-200">
                <summary class="cursor-pointer font-medium text-custom-500 underline-offset-2 hover:underline focus:underline focus:decoration-2 focus:outline-none"><?= e($t((string) $step->actionLabelKey)) ?></summary>
                <ol class="mt-2 list-decimal space-y-1 ps-5">
                  <li><?= e($t('setup.card.review.write')) ?></li>
                  <li><?= e($t('setup.card.review.editor')) ?></li>
                  <li><?= e($t('setup.card.review.notify')) ?></li>
                </ol>
              </details>
              <?php } ?>
              <?php if ($isShare) { ?>
              <div x-show="fallback" style="display: none" class="mt-2">
                <label class="sr-only" for="next-steps-url"><?= e($t('setup.card.copyFallback')) ?></label>
                <input id="next-steps-url" type="text" readonly value="<?= e($blogUrl) ?>" x-ref="urlInput" @focus="selectAddress"
                  class="form-input w-full border-slate-200 text-sm dark:border-zink-500 dark:bg-zink-700 dark:text-zink-100">
              </div>
              <noscript>
                <p class="mt-2 break-all text-sm text-slate-600 dark:text-zink-200"><?= e($blogUrl) ?></p>
              </noscript>
              <?php } ?>
            </div>
            <?php if ($step->actionUrl !== null) { ?>
            <a href="<?= e(lurl($step->actionUrl)) ?>" class="<?= $ghostButton ?>"><?= e($t((string) $step->actionLabelKey)) ?></a>
            <?php } elseif ($isShare) { ?>
            <button type="button" @click="copy" class="<?= $ghostButton ?>" style="display: none" x-show="ready">
              <span x-text="copyLabel"><?= e($t((string) $step->actionLabelKey)) ?></span>
            </button>
            <?php } ?>
          </div>
          <?php } ?>
        </li>
        <?php } ?>
      </ol>
    </div>
  </section>

  <section class="card mb-0" aria-labelledby="next-steps-finished" x-show="isFinished"<?= $hiddenUnless($state === 'finished') ?>>
    <div class="card-body flex flex-wrap items-start justify-between gap-4">
      <div>
        <h2 id="next-steps-finished" x-ref="finished" tabindex="-1" class="text-base font-semibold text-slate-900 focus:outline-none dark:text-zink-50"><?= e($t('setup.card.finishedTitle')) ?></h2>
        <?php if ($isOwner) { ?>
        <p class="mt-1 text-sm text-slate-500 dark:text-zink-300"><?= e($t('setup.card.finishedIntro')) ?></p>
        <?php } ?>
        <?php if ($isOwner) { ?>
        <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
          <li><a class="font-medium text-custom-500 underline-offset-2 hover:underline focus:underline focus:decoration-2 focus:outline-none" href="<?= e(lurl('/dashboard/blog/'.$blogId.'/insights')) ?>"><?= e($t('setup.card.insights')) ?></a></li>
          <li><a class="font-medium text-custom-500 underline-offset-2 hover:underline focus:underline focus:decoration-2 focus:outline-none" href="<?= e(lurl('/dashboard/blog/'.$blogId.'/backup')) ?>"><?= e($t('setup.card.backup')) ?></a></li>
          <li><a class="font-medium text-custom-500 underline-offset-2 hover:underline focus:underline focus:decoration-2 focus:outline-none" href="<?= e(lurl('/dashboard/blog/'.$blogId.'/settings').'#general') ?>"><?= e($t('setup.card.translations')) ?></a></li>
        </ul>
        <?php } ?>
      </div>
      <form method="post" action="<?= e($config['urls']['dismiss']) ?>" @submit.prevent="dismiss">
        <?= $csrf ?>
        <button type="submit" class="<?= $ghostButton ?>"><?= e($t('setup.card.done')) ?></button>
      </form>
    </div>
  </section>
</div>
<?php } ?>
