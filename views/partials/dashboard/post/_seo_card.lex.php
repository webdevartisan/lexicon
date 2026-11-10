<?php
/**
 * Search engine fields for the post editor, shown inside their own dialog
 * next to a preview of the search result.
 *
 * Expects: $post, $blog
 */
$inputClass = 'block w-full px-3 py-2 text-sm border rounded-md outline-none border-slate-300/80 text-slate-900 placeholder:text-slate-400 bg-white focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:bg-zink-800 dark:border-zink-600 dark:text-zink-100 dark:placeholder:text-zink-400';
$labelClass = 'block mb-1.5 text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300';

$postPath = base_url().'/blog/'.($blog['blog_slug'] ?? '').'/'.($post['slug'] ?? '');
?>
<div>

  <div class="grid gap-6 text-left md:grid-cols-[minmax(0,1fr)_320px]">

    <?php /* min-w-0 stops a grid item's auto minimum from overflowing on narrow screens */ ?>
    <div class="min-w-0 space-y-4">

      <div>
        <label for="focus_keyword" class="<?= $labelClass ?>">Focus keyword
          <?php $hintText = 'The main phrase this post should be found for. Guidance only, it is not published.'; ?>
          {% cmp="hint" text="{$hintText}" %}
        </label>
        <input id="focus_keyword" name="focus_keyword" type="text"
          value="<?= e($post['focus_keyword'] ?? '') ?>"
          class="<?= $inputClass ?>"
          placeholder="e.g. php mvc framework">
      </div>

      <div>
        <div class="mb-1.5 flex items-center justify-between">
          <label for="meta_title" class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zink-300">Meta title
          <?php $hintText = '50-60 characters displays without truncation. Left empty, it uses the post title and blog name.'; ?>
          {% cmp="hint" text="{$hintText}" %}
        </label>
          <span id="meta_title_count" class="text-xs tabular-nums text-slate-400 dark:text-zink-400">0/60</span>
        </div>
        <input id="meta_title" name="meta_title" type="text" maxlength="70"
          value="<?= e($post['meta_title'] ?? '') ?>"
          data-char-limit="60"
          data-title-suffix="<?= e(search_title_suffix((string) ($blog['blog_name'] ?? ''))) ?>"
          class="<?= $inputClass ?>"
          placeholder="Defaults to post title">
      </div>

      <div>
        <div class="mb-1.5 flex items-center justify-between">
          <label for="meta_description" class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zink-300">Meta description
          <?php $hintText = '150-160 characters. Falls back to the excerpt when empty.'; ?>
          {% cmp="hint" text="{$hintText}" %}
        </label>
          <span id="meta_description_count" class="text-xs tabular-nums text-slate-400 dark:text-zink-400">0/160</span>
        </div>
        <textarea id="meta_description" name="meta_description" rows="3" maxlength="200"
          data-char-limit="160"
          class="<?= $inputClass ?>"
          placeholder="Compelling summary for search results"><?= e($post['meta_description'] ?? '') ?></textarea>
      </div>

      <div>
        <label for="canonical_url" class="<?= $labelClass ?>">Canonical URL
          <?php $hintText = 'Only needed when this post is republished from somewhere else.'; ?>
          {% cmp="hint" text="{$hintText}" %}
        </label>
        <div class="relative">
          <input id="canonical_url" name="canonical_url" type="url"
            value="<?= e($post['canonical_url'] ?? '') ?>"
            class="<?= $inputClass ?> pr-10"
            placeholder="https://example.com/original-post">
          <button type="button"
            data-canonical-fill
            data-post-url="<?= e($postPath) ?>"
            class="absolute inset-y-0 flex items-center text-slate-400 hover:text-custom-500 ltr:right-0 ltr:pr-3 rtl:left-0 rtl:pl-3 dark:text-zink-500 dark:hover:text-custom-400"
            title="Use this post's URL">
            <i data-lucide="zap" class="size-4" aria-hidden="true"></i>
          </button>
        </div>
      </div>

      <div class="border-t border-slate-200 pt-3 dark:border-zink-600">
        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zink-300">Search engine visibility</p>
        <div class="flex flex-wrap gap-4">
          <label class="flex cursor-pointer items-center gap-2">
            <input type="checkbox" name="meta_noindex" value="1"
              <?= !empty($post['meta_noindex']) ? 'checked' : '' ?>
              class="rounded border-slate-300 text-custom-500 focus:ring-custom-500 dark:border-zink-600">
            <span class="text-xs text-slate-700 dark:text-zink-200">Noindex</span>
          </label>
          <label class="flex cursor-pointer items-center gap-2">
            <input type="checkbox" name="meta_nofollow" value="1"
              <?= !empty($post['meta_nofollow']) ? 'checked' : '' ?>
              class="rounded border-slate-300 text-custom-500 focus:ring-custom-500 dark:border-zink-600">
            <span class="text-xs text-slate-700 dark:text-zink-200">Nofollow</span>
          </label>
        </div>
        <p class="mt-2 text-[11px] text-slate-500 dark:text-zink-400">
          <strong>Noindex</strong> keeps this page out of search results.
          <strong>Nofollow</strong> asks engines not to follow its links.
        </p>
      </div>

    </div>

    <div class="min-w-0">
      <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zink-300">Search preview</p>
      <div class="rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-zink-600 dark:bg-zink-800/50">
        <div class="flex items-center gap-1.5">
          <span class="flex size-5 items-center justify-center rounded-full bg-slate-200 text-[9px] font-semibold text-slate-500 dark:bg-zink-600 dark:text-zink-300">
            <?= e(strtoupper(substr((string) ($blog['blog_name'] ?? 'B'), 0, 1))) ?>
          </span>
          <div class="min-w-0">
            <div class="truncate text-[11px] font-medium text-slate-700 dark:text-zink-200"><?= e($blog['blog_name'] ?? 'Blog') ?></div>
            <div id="seo_preview_url" class="truncate text-[10px] text-slate-500 dark:text-zink-400"><?= e($postPath) ?></div>
          </div>
        </div>
        <div id="seo_preview_title" class="mt-2 line-clamp-2 text-base leading-snug text-[#1a0dab] dark:text-blue-400">
          <?= e(trim((string) ($post['title'] ?? '')) !== '' ? post_search_title($post ?? [], (string) ($blog['blog_name'] ?? '')) : 'Your post title will appear here') ?>
        </div>
        <div id="seo_preview_desc" class="mt-1 line-clamp-3 text-xs leading-relaxed text-slate-600 dark:text-zink-300">
          <?= e(($post['meta_description'] ?? '') ?: (post_excerpt($post ?? []) ?: 'Your meta description or excerpt will appear here in search results...')) ?>
        </div>
      </div>
      <p class="mt-2 text-[11px] text-slate-400 dark:text-zink-400">
        Approximate. Search engines rewrite titles and descriptions when they judge a different one fits the query better.
      </p>
    </div>

  </div>
</div>
