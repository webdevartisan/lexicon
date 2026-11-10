<?php
/**
 * Shared post editor body, used by both the create and edit screens.
 *
 * The page itself holds only what a writer types: cover, title and text.
 * Everything else is a row that shows its current value and opens a dialog,
 * so the settings stay one tap away without pushing the text down the page.
 * The dialogs sit inside the form, which is how their fields get saved.
 */
$postStatus = ($post['status'] ?? 'draft');
$hasPost = !empty($post['id']);
$errors = $errors ?? [];

// The publish date only makes sense while a post is still on its way out.
$showScheduling = !in_array($postStatus, ['published', 'archived'], true);

// A main button that can publish or schedule asks when and for whom first.
$hasPublishCheck = isset($actions['primary']['later'], $actions['primary']['now']);
$primaryAttrs = $hasPublishCheck ? ['data-publish-check' => 'postPublishDialog'] : [];

$dialogWithError = \App\Presenters\PostEditorPresenter::dialogWithError($errors, $hasPublishCheck);
$unplacedErrors = \App\Presenters\PostEditorPresenter::unplacedErrors($errors, $hasPublishCheck);
$quickTags = \App\Presenters\PostEditorPresenter::quickTags($allTags ?? []);

// Ctrl+S presses the button that saves without changing where the post stands.
$saveIntent = match (true) {
    $postStatus === 'draft' => 'save_draft',
    $actions['primary']['intent'] === 'update' => 'update',
    default => '',
};

$fieldLabel = 'mb-1.5 flex items-center gap-1 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zink-300';
$fieldInput = 'block w-full rounded-md border border-slate-300/80 bg-white px-3 py-2 text-sm text-slate-900 outline-none placeholder:text-slate-400 focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:border-zink-600 dark:bg-zink-800 dark:text-zink-100 dark:placeholder:text-zink-400';
$fieldError = 'mt-1 text-sm text-red-600 dark:text-red-400';
$barIcon = 'inline-flex size-9 shrink-0 items-center justify-center rounded-md border border-slate-200 text-slate-600 transition-colors hover:bg-slate-50 dark:border-zink-500 dark:text-zink-200 dark:hover:bg-zink-600';
$postPath = '/blog/'.($blog['blog_slug'] ?? '').'/';
$postAddress = base_url().$postPath;
?>
    <input type="hidden" name="_method" value="PUT">
    {{ csrf_field() }}

    <?php ob_start(); ?>
    <span data-word-count class="hidden shrink-0 text-xs tabular-nums text-slate-500 sm:inline dark:text-zink-300"></span>
    {% include "partials/dashboard/post/_locale_menu.lex.php" %}
    <?php if ($hasPost) { ?>
    <a href="<?= e($postAddress.($post['slug'] ?? '')) ?>" target="_blank" rel="noopener" class="<?= $barIcon ?>" title="Preview the saved post">
      <i data-lucide="eye" class="size-4" aria-hidden="true"></i>
      <span class="sr-only">Preview the saved post</span>
    </a>
    <?php } ?>
    <button type="button" data-focus-toggle aria-pressed="false" class="<?= $barIcon ?> aria-pressed:border-custom-500 aria-pressed:text-custom-600" title="Show only the title and text">
      <i data-lucide="maximize-2" class="size-4" aria-hidden="true"></i>
      <span class="sr-only">Show only the title and text</span>
    </button>
    <?php $barTools = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <?php if ($hasPost) { ?>
    <button type="submit" form="deletePost" role="menuitem"
      data-confirm="<?= e($t('components.postCard.deleteConfirm', ['title' => (string) $post['title']])) ?>"
      class="flex w-full items-center gap-2 border-t border-slate-200 px-3 py-2 text-left text-sm text-red-600 transition-colors hover:bg-red-50 dark:border-zink-500 dark:text-red-400 dark:hover:bg-red-500/10">
      <i data-lucide="trash-2" class="size-4 shrink-0" aria-hidden="true"></i>
      Delete post
    </button>
    <?php } ?>
    <?php $barMenuExtras = ob_get_clean(); ?>

    <?php $showAutosave = true; ?>
    {% include "partials/dashboard/_action_bar.lex.php" %}

    <div data-editor data-save-intent="<?= e($saveIntent) ?>" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] max-lg:pb-24">

      <main data-editor-main class="mx-auto w-full min-w-0 max-w-4xl space-y-4">

        <?php if ($unplacedErrors !== []) { ?>
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-500/30 dark:bg-red-500/10" role="alert">
          <div class="flex items-center gap-2">
            <i data-lucide="alert-circle" class="size-4 shrink-0 text-red-600 dark:text-red-400" aria-hidden="true"></i>
            <h3 class="text-sm font-semibold text-red-800 dark:text-red-300">This post wasn't saved</h3>
          </div>
          <ul class="mt-2 space-y-1 ltr:pl-6 rtl:pr-6">
            <?php foreach ($unplacedErrors as $message) { ?>
            <li class="list-disc text-xs text-red-700 dark:text-red-300"><?= e($message) ?></li>
            <?php } ?>
          </ul>
        </div>
        <?php } ?>

        <?php if (!empty($latestReview) && !empty($latestReview['feedback']) && ($workflowState ?? '') === 'needs_changes') { ?>
        <section class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
          <div class="flex items-center gap-2">
            <i data-lucide="message-square" class="size-4 shrink-0 text-amber-600 dark:text-amber-400" aria-hidden="true"></i>
            <h3 class="text-sm font-semibold text-amber-800 dark:text-amber-300">Reviewer feedback</h3>
          </div>
          <p class="mt-2 text-sm leading-relaxed text-amber-900 dark:text-amber-200"><?= e($latestReview['feedback']) ?></p>
          <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
            @<?= e($latestReview['reviewer_handle']) ?>,
            <?= e(local_datetime($latestReview['reviewed_at'] ?? null, 'M j, Y')) ?>
          </p>
        </section>
        <?php } ?>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-zink-600 dark:bg-zink-700">

          <div data-cover-strip>
            <button type="button" data-cover-shown data-modal-target="postCoverDialog"
              class="relative hidden w-full overflow-hidden rounded-t-lg focus:outline-none">
              <img data-cover-image alt="" class="h-40 w-full object-cover md:h-56">
              <span class="absolute bottom-2 inline-flex items-center gap-1.5 rounded-md bg-slate-900/70 px-2.5 py-1.5 text-xs font-medium text-white ltr:right-2 rtl:left-2">
                <i data-lucide="image" class="size-3.5" aria-hidden="true"></i>
                Change cover
              </span>
            </button>
            <div data-cover-empty class="px-4 pt-4 md:px-6">
              <button type="button" data-modal-target="postCoverDialog"
                class="inline-flex h-9 items-center gap-1.5 rounded-md px-2 text-sm font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:text-zink-300 dark:hover:bg-zink-600 dark:hover:text-zink-100 ltr:-ml-2 rtl:-mr-2">
                <i data-lucide="image-plus" class="size-4" aria-hidden="true"></i>
                Add cover
              </button>
            </div>
          </div>

          <div class="space-y-3 p-4 md:p-6">
            <div>
              <?php $title = old('title') ?? $post['title'] ?? $prefillTitle ?? ''; ?>
              <textarea id="title" name="title" rows="1" maxlength="100" data-title-field
                data-chars-left="20" aria-label="Title" placeholder="Title" autocomplete="off"
                class="block w-full resize-none overflow-hidden border-0 bg-transparent p-0 text-2xl font-semibold leading-tight text-slate-900 placeholder:text-slate-300 focus:outline-none focus:ring-0 md:text-3xl dark:text-zink-50 dark:placeholder:text-zink-400"><?= e($title) ?></textarea>
              <p data-chars-left-for="title" class="mt-1 hidden text-xs text-slate-500 dark:text-zink-300"></p>
              <?php foreach ((array) ($errors['title'] ?? []) as $message) { ?>
              <p class="<?= $fieldError ?>"><?= e($message) ?></p>
              <?php } ?>
            </div>

            <?php if (!empty($linkEditable)) { ?>
            <p class="flex min-w-0 items-center gap-2 text-xs text-slate-500 dark:text-zink-300">
              <span class="min-w-0 truncate [direction:rtl]"><span dir="ltr"><?= e($postPath) ?><span data-row-value="link"></span></span></span>
              <button type="button" data-modal-target="postLinkDialog" class="shrink-0 font-medium text-custom-500 hover:underline focus:underline focus:outline-none">Edit link</button>
            </p>
            <?php } ?>

            <div>
              <?php $content = old('content') ?? $post['content'] ?? ''; ?>
              <textarea
                id="content"
                name="content"
                rows="14"
                aria-label="Post text"
                class="js-post-editor <?= $fieldInput ?>"
                placeholder="Write your post here">{{ content }}</textarea>
              <?php foreach ((array) ($errors['content'] ?? []) as $message) { ?>
              <p class="<?= $fieldError ?>"><?= e($message) ?></p>
              <?php } ?>
            </div>
          </div>
        </section>

        <style>
  .post-panel > summary { list-style: none; }
  .post-panel > summary::-webkit-details-marker { display: none; }
  .post-panel[open] > .post-panel-head { border-bottom-width: 1px; }
  .post-panel[open] .post-panel-chevron { transform: rotate(180deg); }
</style>

        {% slot "editor.main" %}

      </main>

      <aside data-editor-side class="w-full shrink-0 space-y-4 lg:sticky lg:top-[calc(theme('spacing.header')_+_5rem)] lg:w-80 lg:self-start">

        <?php if (!empty($visibility)) { ?>
        <div class="px-1">
          {% cmp="visibility-line" line="{$visibility}" quiet="true" %}
        </div>
        <?php } ?>

        <section class="divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:divide-zink-600 dark:border-zink-600 dark:bg-zink-700">
          <?php $rowInvalid = $dialogWithError === 'postSummaryDialog'; ?>
          {% cmp="editor-row" target="postSummaryDialog" icon="text" label="Summary" value="summary" invalid="{$rowInvalid}" %}
          <?php $rowInvalid = $dialogWithError === 'postOrganizeDialog'; ?>
          {% cmp="editor-row" target="postOrganizeDialog" icon="tag" label="Category and tags" value="organize" invalid="{$rowInvalid}" %}
          <?php $rowInvalid = $dialogWithError === 'postOptionsDialog'; ?>
          {% cmp="editor-row" target="postOptionsDialog" icon="sliders-horizontal" label="Options" value="options" invalid="{$rowInvalid}" %}
          <?php $rowInvalid = $dialogWithError === 'postSearchDialog'; ?>
          {% cmp="editor-row" target="postSearchDialog" icon="search" label="Search engines" value="search" invalid="{$rowInvalid}" %}
          <?php $rowInvalid = $dialogWithError === 'postSocialDialog'; ?>
          {% cmp="editor-row" target="postSocialDialog" icon="share-2" label="Social sharing" value="social" invalid="{$rowInvalid}" %}
        </section>

        <?php if (!empty($postAnalytics)) { ?>
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-zink-600 dark:bg-zink-700">
          <div class="p-4">
            <div class="flex items-center justify-between gap-2">
              <h3 class="text-sm font-semibold text-slate-900 dark:text-zink-100"><?= e($t('analytics.pageTitle')) ?></h3>
              <a href="<?= e($postAnalytics['url']) ?>" class="text-xs text-custom-500 hover:underline"><?= e($t('analytics.topPosts.details')) ?></a>
            </div>
            <?php
            $writerPresent = new \App\Presenters\AnalyticsPresenter(\App\Services\LocaleState::get()->chromeLocale, $t, app(\App\Services\LocaleRegistry::class), 'post');
            $writerSource = $writerPresent->topSource($postAnalytics['weekSources']);
            ?>
            <p class="mt-2 text-[11px] font-medium uppercase tracking-wide text-slate-500 dark:text-zink-300"><?= e($t('analytics.writer.thisWeek')) ?></p>
            <dl class="mt-1 grid grid-cols-3 gap-2 text-center">
              <div>
                <dt class="text-[11px] text-slate-500 dark:text-zink-300"><?= e($t('analytics.metrics.views')) ?></dt>
                <dd class="text-base font-semibold text-slate-900 dark:text-zink-50 tabular-nums"><?= e($writerPresent->number($postAnalytics['weekViews'])) ?></dd>
              </div>
              <div>
                <dt class="text-[11px] text-slate-500 dark:text-zink-300"><?= e($t('analytics.metrics.readRatio')) ?></dt>
                <dd class="text-base font-semibold text-slate-900 dark:text-zink-50 tabular-nums"><?= e($writerPresent->percent($postAnalytics['weekReadRate']) ?? $t('analytics.metrics.none')) ?></dd>
              </div>
              <div class="min-w-0">
                <dt class="text-[11px] text-slate-500 dark:text-zink-300"><?= e($t('analytics.metrics.topSource')) ?></dt>
                <dd class="text-sm font-semibold text-slate-900 dark:text-zink-50 truncate" dir="auto" title="<?= e($writerSource ?? '') ?>"><?= e($writerSource ?? $t('analytics.metrics.none')) ?></dd>
              </div>
            </dl>
            <p class="mt-2 text-[11px] text-slate-500 dark:text-zink-300"><?= e($t('analytics.writer.allTime', ['views' => $writerPresent->number($postAnalytics['views'])])) ?></p>
          </div>
        </section>
        <?php } ?>

      </aside>
    </div>

    <?php /* The dialogs. Each body is built first, then handed to the shared modal. */ ?>

    <?php if (!empty($linkEditable)) { ?>
    <?php ob_start(); ?>
    <div class="space-y-2 text-left">
      <?php $slug = $post['slug'] ?? ''; ?>
      {% cmp="input" type="text" label="Post link" name="slug" value="{$slug}" placeholder="a-short-title" prefix="{$postAddress}" %}
      <p class="text-xs text-slate-500 dark:text-zink-300">Small letters, numbers and hyphens. It can change until the post is public.</p>
    </div>
    <?php $dialogBody = ob_get_clean(); ?>
    <?php $dialogOpen = $dialogWithError === 'postLinkDialog'; ?>
    {% cmp="modal" id="postLinkDialog" title="Post link" icon="link" size="lg" phone="sheet" body="{$dialogBody}" noConfirm="true" cancelText="Done" openOnLoad="{$dialogOpen}" %}
    <?php } ?>

    <?php ob_start(); ?>
    <div class="text-left">
      <?php $blogIdForLibrary = (string) ($post['blog_id'] ?? $blog['id'] ?? ''); ?>
      {% cmp="dropzone" label="Cover image" name="featured_image" maxsize="10" resource="{$post}" library="{$blogIdForLibrary}" altTarget="og_image_alt" %}
      <?php foreach ((array) ($errors['featured_image'] ?? []) as $message) { ?>
      <p class="<?= $fieldError ?>"><?= e($message) ?></p>
      <?php } ?>
    </div>
    <?php $dialogBody = ob_get_clean(); ?>
    <?php $dialogOpen = $dialogWithError === 'postCoverDialog'; ?>
    {% cmp="modal" id="postCoverDialog" title="Cover image" icon="image" size="md" phone="sheet" body="{$dialogBody}" noConfirm="true" cancelText="Done" openOnLoad="{$dialogOpen}" %}

    <?php ob_start(); ?>
    <div class="space-y-3 text-left">
      <div data-auto-summary-box class="rounded-md bg-slate-50 p-3 dark:bg-zink-800/60">
        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500 dark:text-zink-300">Automatic summary</p>
        <p data-auto-summary class="mt-1 text-sm text-slate-700 dark:text-zink-100"></p>
      </div>
      <div>
        <?php $excerpt = old('excerpt') ?? $post['excerpt'] ?? ''; ?>
        <?php $summaryHelp = 'Shown in post lists and search results. Leave it empty to use the opening of the post.'; ?>
        <label for="excerpt" class="<?= $fieldLabel ?>">
          Your own summary
          {% cmp="hint" text="{$summaryHelp}" %}
          <span data-chars-left-for="excerpt" class="font-normal normal-case tracking-normal tabular-nums ltr:ml-auto rtl:mr-auto"></span>
        </label>
        <textarea id="excerpt" name="excerpt" rows="4" maxlength="300" data-chars-left="300"
          class="<?= $fieldInput ?>"
          placeholder="Write a sentence or two"><?= e($excerpt) ?></textarea>
        <?php foreach ((array) ($errors['excerpt'] ?? []) as $message) { ?>
        <p class="<?= $fieldError ?>"><?= e($message) ?></p>
        <?php } ?>
      </div>
      <button type="button" data-summary-clear class="hidden text-xs font-medium text-custom-500 hover:underline focus:underline focus:outline-none">Use the automatic summary</button>
    </div>
    <?php $dialogBody = ob_get_clean(); ?>
    <?php $dialogOpen = $dialogWithError === 'postSummaryDialog'; ?>
    {% cmp="modal" id="postSummaryDialog" title="Summary" icon="text" size="lg" phone="sheet" body="{$dialogBody}" noConfirm="true" cancelText="Done" openOnLoad="{$dialogOpen}" %}

    <?php ob_start(); ?>
    <div class="space-y-4 text-left">
      <div>
        <?php $categoryHelp = 'Optional. Manage the list on the Categories and Tags page.'; ?>
        <label for="category_id" class="<?= $fieldLabel ?>">
          Category
          {% cmp="hint" text="{$categoryHelp}" %}
        </label>
        <?php $currentCategory = (int) (old('category_id') ?? $post['category_id'] ?? 0); ?>
        <select id="category_id" name="category_id" data-native class="form-select <?= $fieldInput ?>">
          <option value="">None</option>
          <?php foreach (($categories ?? []) as $cat) { ?>
          <option value="<?= (int) $cat['id'] ?>" <?= $currentCategory === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
          <?php } ?>
        </select>
        <?php foreach ((array) ($errors['category_id'] ?? []) as $message) { ?>
        <p class="<?= $fieldError ?>"><?= e($message) ?></p>
        <?php } ?>
      </div>

      <div>
        <?php $tagsHelp = 'Optional. Type a tag and press Enter. New ones are created as you type.'; ?>
        <label for="tagInput" class="<?= $fieldLabel ?>">
          Tags
          {% cmp="hint" text="{$tagsHelp}" %}
        </label>
        <div id="tagChips" class="mb-1.5 flex flex-wrap gap-1.5"></div>
        <?php $knownTags = array_values(array_map(static function (array $tag): string {
            return (string) $tag['name'];
        }, $allTags ?? [])); ?>
        <div class="relative">
          <input id="tagInput" type="text" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="tagSuggestions"
            placeholder="Type a tag, press Enter"
            class="<?= $fieldInput ?>"
            data-tags="<?= e(json_encode($knownTags)) ?>">
          <div id="tagSuggestions" role="listbox"
            class="absolute inset-x-0 top-full z-40 mt-1 hidden max-h-48 overflow-y-auto rounded-md border border-slate-200 bg-white py-1 shadow-md dark:border-zink-500 dark:bg-zink-600"></div>
        </div>
        <input type="hidden" name="tags" id="tagsField" value="<?= e(implode(',', $postTags ?? [])) ?>">
        <?php foreach ((array) ($errors['tags'] ?? []) as $message) { ?>
        <p class="<?= $fieldError ?>"><?= e($message) ?></p>
        <?php } ?>

        <?php if ($quickTags !== []) { ?>
        <div data-quick-tags class="mt-3">
          <p class="mb-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-500 dark:text-zink-300">Most used</p>
          <div class="flex flex-wrap gap-1.5">
            <?php foreach ($quickTags as $quickTag) { ?>
            <button type="button" data-quick-tag="<?= e($quickTag) ?>"
              class="inline-flex min-h-8 items-center gap-1 rounded-full border border-slate-200 px-3 text-xs text-slate-600 transition-colors hover:border-custom-400 hover:text-custom-600 dark:border-zink-500 dark:text-zink-200">
              <i data-lucide="plus" class="size-3" aria-hidden="true"></i>
              <?= e($quickTag) ?>
            </button>
            <?php } ?>
          </div>
        </div>
        <?php } ?>
      </div>
    </div>
    <?php $dialogBody = ob_get_clean(); ?>
    <?php $dialogOpen = $dialogWithError === 'postOrganizeDialog'; ?>
    {% cmp="modal" id="postOrganizeDialog" title="Category and tags" icon="tag" size="lg" phone="sheet" body="{$dialogBody}" noConfirm="true" cancelText="Done" openOnLoad="{$dialogOpen}" %}

    <?php ob_start(); ?>
    <div class="space-y-4 text-left">
      <?php if (!empty($canAssignAuthor)) { ?>
      <div>
        <?php $selectedAuthor = (string) (old('author_id') ?? $authorId ?? ''); ?>
        <?php $authorHelp = 'Anyone on this blog\'s team who can write posts.'; ?>
        <label for="author_id" class="<?= $fieldLabel ?>">
          Author
          {% cmp="hint" text="{$authorHelp}" %}
        </label>
        <select id="author_id" name="author_id" data-native class="form-select <?= $fieldInput ?>">
          <?php foreach ($authorOptions as $candidateId => $candidateHandle) { ?>
          <option value="<?= e((string) $candidateId) ?>" <?= $selectedAuthor === (string) $candidateId ? 'selected' : '' ?>>@<?= e($candidateHandle) ?></option>
          <?php } ?>
        </select>
        <?php foreach ((array) ($errors['author_id'] ?? []) as $message) { ?>
        <p class="<?= $fieldError ?>"><?= e($message) ?></p>
        <?php } ?>
      </div>
      <?php } elseif (!empty($authorHandle)) { ?>
      <div>
        <p class="<?= $fieldLabel ?>">Author</p>
        <p class="text-sm text-slate-700 dark:text-zink-100">@<?= e($authorHandle) ?></p>
      </div>
      <?php } ?>

      <?php if (!$hasPublishCheck) { ?>
      {% include "partials/dashboard/post/_publish_fields.lex.php" %}
      <?php } ?>

      <label class="flex min-h-10 cursor-pointer items-center gap-3">
        <input type="checkbox" name="comments_enabled" value="1"
          <?= (!isset($post['comments_enabled']) || !empty($post['comments_enabled'])) ? 'checked' : '' ?>
          class="size-4 rounded border-slate-300 text-custom-500 focus:ring-custom-500 dark:border-zink-600">
        <span class="text-sm text-slate-700 dark:text-zink-100">Allow comments</span>
      </label>
      <div class="flex min-h-10 items-center gap-1">
        <label class="flex cursor-pointer items-center gap-3">
          <input type="checkbox" name="is_featured" value="1"
            <?= !empty($post['is_featured']) ? 'checked' : '' ?>
            class="size-4 rounded border-slate-300 text-custom-500 focus:ring-custom-500 dark:border-zink-600">
          <span class="text-sm text-slate-700 dark:text-zink-100">Feature on the blog's front page</span>
        </label>
        <?php $featuredHelp = 'Shows this post as the blog\'s headline. One post at a time, once it is published.'; ?>
        {% cmp="hint" text="{$featuredHelp}" %}
      </div>
    </div>
    <?php $dialogBody = ob_get_clean(); ?>
    <?php $dialogOpen = $dialogWithError === 'postOptionsDialog'; ?>
    {% cmp="modal" id="postOptionsDialog" title="Options" icon="sliders-horizontal" size="lg" phone="sheet" body="{$dialogBody}" noConfirm="true" cancelText="Done" openOnLoad="{$dialogOpen}" %}

    <?php if ($hasPublishCheck) { ?>
    <?php ob_start(); ?>
    <div class="space-y-4 text-left">
      {% include "partials/dashboard/post/_publish_fields.lex.php" %}

      <ul data-publish-notes class="hidden space-y-1.5 rounded-md bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
        <li data-publish-note="cover" class="hidden items-center gap-2">
          <i data-lucide="image" class="size-3.5 shrink-0" aria-hidden="true"></i>
          <span class="grow">No cover image yet.</span>
          <button type="button" data-publish-fix="postCoverDialog" class="shrink-0 font-medium underline hover:no-underline">Add one</button>
        </li>
        <li data-publish-note="category" class="hidden items-center gap-2">
          <i data-lucide="tag" class="size-3.5 shrink-0" aria-hidden="true"></i>
          <span class="grow">No category yet.</span>
          <button type="button" data-publish-fix="postOrganizeDialog" class="shrink-0 font-medium underline hover:no-underline">Pick one</button>
        </li>
      </ul>

      <?php $confirm = $actions['primary']; ?>
      <button type="submit" name="intent" value="<?= e($confirm['intent']) ?>"
        data-actionbar-primary
        data-label-later="<?= e($confirm['later']['label']) ?>" data-icon-later="<?= e($confirm['later']['icon']) ?>"
        data-label-now="<?= e($confirm['now']['label']) ?>" data-icon-now="<?= e($confirm['now']['icon']) ?>"
        class="inline-flex h-11 w-full items-center justify-center gap-1.5 rounded-md border border-emerald-600 bg-emerald-600 px-4 text-sm font-medium text-white transition-colors hover:border-emerald-700 hover:bg-emerald-700 focus:ring focus:ring-emerald-100 dark:focus:ring-emerald-500/20">
        <i data-lucide="<?= e($confirm['icon']) ?>" class="size-4" aria-hidden="true"></i>
        <span data-primary-label><?= e($confirm['label']) ?></span>
      </button>
    </div>
    <?php $dialogBody = ob_get_clean(); ?>
    <?php $dialogOpen = $dialogWithError === 'postPublishDialog'; ?>
    {% cmp="modal" id="postPublishDialog" title="Ready to publish?" icon="send" size="md" phone="sheet" body="{$dialogBody}" noConfirm="true" cancelText="Not yet" openOnLoad="{$dialogOpen}" %}
    <?php } ?>

    <?php ob_start(); ?>
    {% include "partials/dashboard/post/_seo_card.lex.php" %}
    <?php $dialogBody = ob_get_clean(); ?>
    <?php $dialogOpen = $dialogWithError === 'postSearchDialog'; ?>
    {% cmp="modal" id="postSearchDialog" title="Search engines" icon="search" size="xl" phone="full" body="{$dialogBody}" noConfirm="true" cancelText="Done" openOnLoad="{$dialogOpen}" %}

    <?php ob_start(); ?>
    {% include "partials/dashboard/post/_social_card.lex.php" %}
    <?php $dialogBody = ob_get_clean(); ?>
    <?php $dialogOpen = $dialogWithError === 'postSocialDialog'; ?>
    {% cmp="modal" id="postSocialDialog" title="Social sharing" icon="share-2" size="xl" phone="full" body="{$dialogBody}" noConfirm="true" cancelText="Done" openOnLoad="{$dialogOpen}" %}
