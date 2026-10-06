<?php
/**
 * The bottom of every Insights page: what the numbers mean, and the link
 * builder and settings dialogs when the top of the page offered them.
 * Control panel pages pass their own English $aboutParagraphs.
 */
$aboutParagraphs ??= [$t('analytics.about.pages.'.($isPost ? 'post' : $page)), $t('analytics.about.counted')];
?>
  <details class="card mb-0">
    <summary class="card-body cursor-pointer text-sm font-semibold text-slate-800 dark:text-zink-50"><?= e($t('analytics.about.title')) ?></summary>
    <div class="px-5 pb-5 -mt-2 flex flex-col gap-2 text-sm leading-relaxed text-slate-600 dark:text-zink-200">
      <?php foreach ($aboutParagraphs as $aboutParagraph) { ?>
      <p><?= e($aboutParagraph) ?></p>
      <?php } ?>
      <?php $glossary = \App\Services\Analytics\InsightsPages::GLOSSARY[$isPost ? 'post' : $page] ?? []; ?>
      <?php if ($glossary !== []) { ?>
      <h3 class="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-zink-300"><?= e($t('analytics.about.glossary')) ?></h3>
      <dl class="grid gap-x-6 gap-y-2 sm:grid-cols-[max-content_1fr]">
        <?php foreach ($glossary as $term) { ?>
        <dt class="font-medium text-slate-800 dark:text-zink-100"><?= e($t('analytics.metrics.'.$term)) ?></dt>
        <dd class="text-slate-600 dark:text-zink-200"><?= e($t('analytics.metrics.'.$term.'Hint')) ?></dd>
        <?php } ?>
      </dl>
      <?php } ?>
    </div>
  </details>
</div>

<?php if ($showLinks) {
    ob_start(); ?>
{% include "partials/dashboard/insights/_link_builder.lex.php" %}
<?php
    $linkBody = ob_get_clean();
    $linkTitle = $t('analytics.links.title');
    $linkClose = $t('analytics.links.close');
    ?>
{% cmp="modal" id="analyticsLinkModal" title="{$linkTitle}" icon="link" size="lg" body="{$linkBody}" cancelText="{$linkClose}" noConfirm="true" %}
<?php } ?>

<?php if ($showSettings && !$isAdmin) {
    ob_start(); ?>
{% include "partials/dashboard/insights/_settings_form.lex.php" %}
<?php
    $settingsBody = ob_get_clean();
    $reopenSettings = !empty($errors['excluded_paths']);
    $settingsTitle = $t('analytics.settings.title');
    $settingsSave = $t('analytics.settings.save');
    $settingsCancel = $t('analytics.settings.cancel');
    ?>
{% cmp="modal" id="analyticsSettingsModal" title="{$settingsTitle}" icon="sliders-horizontal" size="lg" body="{$settingsBody}" form="analyticsSettingsForm" confirmText="{$settingsSave}" cancelText="{$settingsCancel}" openOnLoad="{$reopenSettings}" %}
<?php } ?>
