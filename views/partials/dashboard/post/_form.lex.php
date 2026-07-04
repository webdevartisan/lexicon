<?php
$postStatus = ($post['status'] ?? 'draft');
$isPublished = $postStatus === 'published';
$isDraft = $postStatus === 'draft';
$isPending = $postStatus === 'pending';
$isArchived = $postStatus === 'archived';

?>
    <input type="hidden" name="_method" value="PUT">
    <input type="hidden" name="author_id" value="<?= e($currentUser['id'] ?? $post['author_id'] ?? '') ?>">
    {{ csrf_field() }}
    {% if (($postStatus !== 'draft') && ($postStatus !== 'pending')): %}
      <div class="flex justify-between">
        {% cmp="urlWithOpenButton" previewUrl="{$postUrl}" %} 
      </div>
    {% endif; %}
    <div class="grid gap-6 lg:grid-cols-[1fr_auto]">
      <main>
        <!-- Content -->
        <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">

          <div class="p-4 space-y-6 md:p-5">
            <!-- Title -->
            <?php $title = $post['title'] ?? ''; ?>
            {% cmp="input" type="text" label="title" value="{$title}" %}

            {% if (($postStatus === 'draft') || ($postStatus === 'pending')): %}
            <!-- Slug -->
            <?php $slug = $post['slug'] ?? ''; ?>
            <?php $baseUrl = base_url().'/blog/'.$blog['blog_slug'].'/' ?? '/'; ?>
            {% cmp="input" type="text" label="Post Link" name="slug" value="{$slug}" placeholder="a-short-url-friendly-title" prefix="{$baseUrl}" underlabel="Lowercase letters, numbers, and hyphens only."%}
            {% endif; %}
            <!-- Body -->
            <div>
              <?php $content = old('content') ?? $post['content'] ?? ''; ?>
              <label for="content" class="block mb-1 text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300">
                Body
              </label>
              <textarea
                id="content"
                name="content"
                rows="14"
                class="block w-full px-3 py-2 text-sm border rounded-md outline-none border-slate-300/80 text-slate-900 placeholder:text-slate-400 bg-white focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:bg-zink-800 dark:border-zink-600 dark:text-zink-100 js-post-editor"
                placeholder="Write your post content here...">{{ content }}</textarea>
                <?php if (!empty($errors['content'])) { ?>
                  <?php foreach ($errors['content'] as $msg) { ?>
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400"> <?= $msg ?> </p>
                  <?php } ?>
                <?php } ?>
            </div>

            <!-- Excerpt / summary -->
            <?php $excerpt = old('excerpt') ?? $post['excerpt'] ?? ''; ?>
            {% cmp="input" type="textarea" label="excerpt" value="{$excerpt}" rows="4" placeholder="Optional short summary used in listings and meta description when not set explicitly." %}

          </div>

        </section>
      </main>
      <aside class="sticky top-4 space-y-4 shrink-0 w-full sm:w-64">

        <!-- Reviewer feedback — shown to the author when a reviewer has requested changes -->
        <?php if (!empty($latestReview) && !empty($latestReview['feedback']) && ($workflowState ?? '') === 'needs_changes') { ?>
        <section class="border border-amber-200 bg-amber-50 rounded-lg dark:bg-amber-500/10 dark:border-amber-500/30">
            <div class="p-4 border-b border-amber-200 dark:border-amber-500/30 flex items-center gap-2">
                <i data-lucide="message-square-warning" class="size-4 text-amber-600 dark:text-amber-400 shrink-0"></i>
                <h3 class="text-sm font-semibold text-amber-800 dark:text-amber-300">Reviewer feedback</h3>
            </div>
            <div class="p-4 space-y-2">
                <p class="text-xs text-amber-900 dark:text-amber-200 leading-relaxed">
                    <?= e($latestReview['feedback']) ?>
                </p>
                <p class="text-[11px] text-amber-600 dark:text-amber-400">
                    — <?= e($latestReview['reviewer_username'] ?? 'Reviewer') ?>,
                    <?= e(date('M j, Y', strtotime((string) ($latestReview['reviewed_at'] ?? 'now')))) ?>
                </p>
            </div>
        </section>
        <?php } ?>

        <!-- Publishing Actions Card -->
        <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
          <div class="p-4 space-y-4">
            <!-- Primary Action Buttons -->
            <div class="flex w-full gap-2">
              <?php $label = $isPublished ? 'Update' : 'Save' ?>
              {% cmp="btn" type="submit" variant="blue" icon="save" label="{$label}" addClass="flex-1 " %}
              {% cmp="btn" href="{$backUrl}" variant="slate" icon="step-back" label="Back" %}
            </div>

            <?php
              $hintRole = $blogRole ?? '';
$hintEligible = !empty($workflowEnabled)
    && in_array($hintRole, ['author', 'contributor', 'owner', 'editor'], true)
    && (
        $postStatus === 'draft'
        || ($postStatus === 'pending' && ($workflowState ?? '') === 'needs_changes')
    );
?>
            <?php if ($hintEligible) { ?>
            <!-- Hint replacing the old standalone button — pending status now drives the review pipeline. -->
            <div class="text-[11px] text-slate-500 dark:text-zink-300 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 rounded-md p-2.5 leading-relaxed">
              <i data-lucide="info" class="size-3.5 inline -mt-0.5 text-amber-600 dark:text-amber-400"></i>
              <?php if (($workflowState ?? '') === 'needs_changes') { ?>
                After addressing the feedback, keep <strong>Visibility</strong> on <strong>Pending</strong> and save — the post will be sent back to the reviewer automatically.
              <?php } else { ?>
                Ready for review? Switch <strong>Visibility</strong> below to <strong>Pending</strong> and save — reviewers will pick it up automatically.
              <?php } ?>
            </div>
            <?php } ?>

            <!-- Auto-save Status -->
            <div id="autosave-indicator" class="flex items-center gap-2 text-xs text-slate-500 dark:text-zink-400" style="display: none;">
              <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
              </svg>
              <span id="autosave-message">Auto-saved at 4:28 PM</span>
            </div>

            <!-- Divider -->
            <div class="border-t border-slate-200 dark:border-zink-600"></div>

            <!-- Visibility with Horizontal Button-Style Radios -->
            <div>
              <p class="mb-2 text-xs font-medium text-slate-500 dark:text-zink-300">Visibility</p>
              <?php
              // Which transitions we offer depends on whether the blog uses the review pipeline.
              // With workflow ON, Pending is the mandatory bridge from Draft to Published.
              // With workflow OFF, Draft can go straight to Published (backend already allows it).
              $workflowOn = !empty($workflowEnabled);
              $transitionMap = $workflowOn
                  ? [
                      'draft'     => ['draft', 'pending'],
                      'pending'   => ['draft', 'pending', 'published'],
                      'published' => ['draft', 'published', 'archived'],
                      'archived'  => ['draft', 'published', 'archived'],
                  ]
                  : [
                      'draft'     => ['draft', 'published'],
                      'pending'   => ['draft', 'published', 'archived'],
                      'published' => ['draft', 'published', 'archived'],
                      'archived'  => ['draft', 'published', 'archived'],
                  ];

              $allowed = $transitionMap[$postStatus] ?? ['draft'];

              // Publishing rights mirror the backend rule: owner/editor always,
              // author only while the review pipeline is off, contributor never.
              $canPublishHere = in_array($blogRole ?? '', ['owner', 'editor'], true)
                  || (($blogRole ?? '') === 'author' && !$workflowOn);

              $visibilityLocked = false;
              if (!$canPublishHere) {
                  if (in_array($postStatus, ['published', 'archived'], true)) {
                      // An editor put it here; this user can't move it out.
                      $allowed = [$postStatus];
                      $visibilityLocked = true;
                  } else {
                      $allowed = array_values(array_diff($allowed, ['published', 'archived']));
                  }
              }

              $optionMeta = [
                  'draft'     => ['label' => 'Draft',     'checked' => 'peer-checked:bg-slate-500 peer-checked:border-slate-500 dark:peer-checked:bg-slate-400 dark:peer-checked:border-slate-400'],
                  'pending'   => ['label' => 'Pending',   'checked' => 'peer-checked:bg-amber-500 peer-checked:border-amber-500 dark:peer-checked:bg-amber-500 dark:peer-checked:border-amber-500'],
                  'published' => ['label' => 'Published', 'checked' => 'peer-checked:bg-emerald-500 peer-checked:border-emerald-500 dark:peer-checked:bg-emerald-500 dark:peer-checked:border-emerald-500'],
                  'archived'  => ['label' => 'Archived',  'checked' => 'peer-checked:bg-slate-800 peer-checked:border-slate-800 dark:peer-checked:bg-slate-800 dark:peer-checked:border-slate-800'],
              ];

              $lastIdx = count($allowed) - 1;
              ?>
              <div class="flex flex-col sm:flex-row w-full border rounded-md border-slate-200 dark:border-zink-600">
                <?php foreach ($allowed as $idx => $value) {
                    $meta = $optionMeta[$value];
                    $isFirst = $idx === 0;
                    $isLast  = $idx === $lastIdx;
                    $radius  = $isFirst && $isLast
                        ? 'rounded-md'
                        : ($isFirst ? 'rounded-l-md' : ($isLast ? 'rounded-r-md' : ''));
                    $borderR = $isLast ? '' : 'border-r';
                ?>
                <label class="flex-1 text-center cursor-pointer group">
                  <input
                    type="radio"
                    name="status"
                    value="<?= e($value) ?>"
                    class="sr-only peer"
                    <?= $postStatus === $value ? 'checked' : '' ?>>
                  <span class="block px-3 py-2 text-xs font-medium transition-colors text-slate-700 bg-slate-50 border-slate-200 dark:bg-zink-600 dark:text-zink-200 dark:border-zink-600 peer-checked:text-white <?= $borderR ?> <?= $meta['checked'] ?> <?= $radius ?>">
                    <?= e($meta['label']) ?>
                  </span>
                </label>
                <?php } ?>
              </div>

              <?php if ($visibilityLocked) { ?>
              <p class="mt-2 text-[11px] text-slate-500 dark:text-zink-400">
                Visibility of a published post is managed by the blog's editor or owner.
              </p>
              <?php } elseif (!$canPublishHere && !$workflowOn) { ?>
              <p class="mt-2 text-[11px] text-slate-500 dark:text-zink-400">
                Drafts on this blog are published by its editor or owner.
              </p>
              <?php } elseif ($isPublished && $workflowOn) { ?>
              <p class="mt-2 text-[11px] text-slate-500 dark:text-zink-400">
                ℹ️ Move back to Draft to keep editing privately, or use Archived to hide from the public site.
              </p>
              <?php } ?>
            </div>


            <!-- Comments & Other Options -->
            <div class="space-y-2.5">
              <label class="flex items-center gap-2 cursor-pointer group">
                <input 
                  type="checkbox" 
                  name="comments_enabled" 
                  value="1"
                  <?= !empty($post['comments_enabled']) ? 'checked' : '' ?>
                  class="text-custom-500 border-slate-300 rounded focus:ring-custom-500 dark:border-zink-600">
                <span class="text-xs text-slate-700 dark:text-zink-200  ">
                  Allow comments
                </span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer group">
                <input
                  type="checkbox"
                  name="is_featured"
                  value="1"
                  <?= !empty($post['is_featured']) ? 'checked' : '' ?>
                  class="text-custom-500 border-slate-300 rounded focus:ring-custom-500 dark:border-zink-600">
                <span class="text-xs text-slate-700 dark:text-zink-200">
                  Feature on homepage
                </span>
              </label>
              <p class="text-[11px] text-slate-400 dark:text-zink-400">Shows this post as the blog's headline. Only one post can be featured; takes effect once published.</p>
            </div>

            {% if (($postStatus !== 'published') && ($postStatus !== 'archived')): %}
            <!-- Divider -->
            <div class="border-t border-slate-200 dark:border-zink-600"></div>
            <!-- Scheduled Publishing -->
            <div class="space-y-3">
              <div>
                <label for="published_at" class="block mb-1.5 text-xs font-medium text-slate-500 dark:text-zink-300">
                  Publish Date & Time
                </label>
                <?php
      $publishedAt = old('published_at') ?? $post['published_at'] ?? null;
?>
                <input type="hidden" name="timezone" id="timezone">
                <input 
                  id="published_at"
                  name="published_at"
                  value="<?= e($publishedAt) ?>"
                  type="text" 
                  class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 disabled:bg-slate-100 dark:disabled:bg-zink-600 disabled:border-slate-300 dark:disabled:border-zink-500 dark:disabled:text-zink-200 disabled:text-slate-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200" 
                  data-provider="flatpickr" 
                  data-min-date="today"
                  data-date-format="d.m.y" 
                  data-enable-time="" 
                  readonly="readonly" 
                  placeholder="Select date-time">
                  {% if errors.published_at|notempty %}
                      {% foreach ($errors['published_at'] as $msg): %}
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ msg }}</p>
                      {% endforeach %}
                  {% endif %}
                <p class="mt-1.5 text-[11px] text-slate-500 dark:text-zink-400">
                For scheduled posts, set a future date and select "Published" status
                </p>
              </div>
            </div>
            {% endif %}

            <!-- Divider -->
            <div class="border-t border-slate-200 dark:border-zink-600"></div>

            <!-- Delete Action -->
            {% if post|notempty %}
            {% cmp="btn2" type="button" variant="red" icon="trash-2" label="Delete" size="xs" dataModalTarget="confirmModal" %}
            {% endif %}
          </div>
        </section>

        <!-- Categories & Tags -->
        <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
          <div class="p-4 space-y-4">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Organize</h3>

            <div>
              <label for="category_id" class="block mb-1.5 text-xs font-medium text-slate-500 dark:text-zink-300">Category</label>
              <?php $currentCategory = (int) (old('category_id') ?? $post['category_id'] ?? 0); ?>
              <select id="category_id" name="category_id"
                class="block w-full px-3 py-2 text-sm border rounded-md outline-none border-slate-300/80 text-slate-900 bg-white focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:bg-zink-800 dark:border-zink-600 dark:text-zink-100">
                <option value="">— None —</option>
                <?php foreach (($categories ?? []) as $cat) { ?>
                <option value="<?= (int) $cat['id'] ?>" <?= $currentCategory === (int) $cat['id'] ? 'selected' : '' ?>>
                  <?= e($cat['name']) ?>
                </option>
                <?php } ?>
              </select>
              <p class="mt-1 text-[11px] text-slate-400 dark:text-zink-400">Optional. Manage the list on the Categories &amp; Tags page.</p>
            </div>

            <div>
              <label for="tagInput" class="block mb-1.5 text-xs font-medium text-slate-500 dark:text-zink-300">Tags</label>
              <div id="tagChips" class="flex flex-wrap gap-1.5 mb-1.5"></div>
              <input id="tagInput" type="text" list="tagSuggestions" autocomplete="off"
                placeholder="Type a tag, press Enter"
                class="block w-full px-3 py-2 text-sm border rounded-md outline-none border-slate-300/80 text-slate-900 placeholder:text-slate-400 bg-white focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:bg-zink-800 dark:border-zink-600 dark:text-zink-100 dark:placeholder:text-zink-400">
              <datalist id="tagSuggestions">
                <?php foreach (($allTags ?? []) as $t) { ?>
                <option value="<?= e($t['name']) ?>"></option>
                <?php } ?>
              </datalist>
              <input type="hidden" name="tags" id="tagsField" value="<?= e(implode(',', $postTags ?? [])) ?>">
              <p class="mt-1 text-[11px] text-slate-400 dark:text-zink-400">Optional. New tags are created as you type.</p>
            </div>
          </div>
        </section>

        <!-- Featured Image -->
        <?php $blogIdForLibrary = (string) ($post['blog_id'] ?? $blog['id'] ?? ''); ?>
        {% cmp="dropzone2" label="Featured Image" resource="{$post}" library="{$blogIdForLibrary}" %}

        <!-- SEO Settings Card -->
        <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
          
          <!-- Collapsible Header -->
          <button type="button"
            onclick="this.querySelector('svg').classList.toggle('rotate-180'); document.getElementById('seo_fields').classList.toggle('hidden')"
            class="flex items-center justify-between w-full p-4 text-left border-b border-slate-200 dark:border-zink-600 hover:bg-slate-50 dark:hover:bg-zink-600/50 transition-colors">
            <div>
              <h3 class="text-sm font-semibold text-slate-900 dark:text-zink-100">SEO Settings</h3>
              <p class="mt-0.5 text-xs text-slate-500 dark:text-zink-300">
                Optimize for search engines
              </p>
            </div>
            <svg class="w-5 h-5 text-slate-400 transition-transform dark:text-zink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>

          <!-- SEO Fields (Initially collapsed) -->
          <div id="seo_fields" class="hidden p-4 space-y-4 md:p-5">
            
            <!-- Focus Keyword -->
            <div>
              <label for="focus_keyword" class="block mb-1.5 text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300">
                Focus keyword
              </label>
              <input
                id="focus_keyword"
                name="focus_keyword"
                type="text"
                value="<?= e($post['focus_keyword'] ?? '') ?>"
                class="block w-full px-3 py-2 text-sm border rounded-md outline-none border-slate-300/80 text-slate-900 placeholder:text-slate-400 bg-white focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:bg-zink-800 dark:border-zink-600 dark:text-zink-100 dark:placeholder:text-zink-400"
                placeholder="e.g., php mvc framework">
              <p class="mt-1.5 text-[11px] text-slate-500 dark:text-zink-400">
                Primary keyword you're targeting for this post
              </p>
            </div>

            <!-- Meta Title with Character Counter -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label for="meta_title" class="text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300">
                  Meta title
                </label>
                <span id="meta_title_count" 
                  class="text-xs tabular-nums text-slate-400 dark:text-zink-400">
                  0/60
                </span>
              </div>
              <input
                id="meta_title"
                name="meta_title"
                type="text"
                maxlength="70"
                value="<?= e($post['meta_title'] ?? '') ?>"
                oninput="updateCharCount('meta_title', 60)"
                class="block w-full px-3 py-2 text-sm border rounded-md outline-none border-slate-300/80 text-slate-900 placeholder:text-slate-400 bg-white focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:bg-zink-800 dark:border-zink-600 dark:text-zink-100 dark:placeholder:text-zink-400"
                placeholder="Defaults to post title">
              <p class="mt-1.5 text-[11px] text-slate-500 dark:text-zink-400">
                Recommended: 50-60 characters for optimal display
              </p>
            </div>

            <!-- Meta Description with Character Counter -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label for="meta_description" class="text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300">
                  Meta description
                </label>
                <span id="meta_description_count" 
                  class="text-xs tabular-nums text-slate-400 dark:text-zink-400">
                  0/160
                </span>
              </div>
              <textarea
                id="meta_description"
                name="meta_description"
                rows="3"
                maxlength="200"
                oninput="updateCharCount('meta_description', 160)"
                class="block w-full px-3 py-2 text-sm border rounded-md outline-none border-slate-300/80 text-slate-900 placeholder:text-slate-400 bg-white focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:bg-zink-800 dark:border-zink-600 dark:text-zink-100 dark:placeholder:text-zink-400"
                placeholder="Compelling summary for search results"><?= e($post['meta_description'] ?? '') ?></textarea>
              <p class="mt-1.5 text-[11px] text-slate-500 dark:text-zink-400">
                Recommended: 150-160 characters for best SERP display
              </p>
            </div>

            <!-- Canonical URL -->
            <div>
              <label for="canonical_url" class="block mb-1.5 text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300">
                Canonical URL
              </label>
              <div class="relative">
                <input
                  id="canonical_url"
                  name="canonical_url"
                  type="url"
                  value="<?= e($post['canonical_url'] ?? '') ?>"
                  class="block w-full px-3 py-2 pr-10 text-sm border rounded-md outline-none border-slate-300/80 text-slate-900 placeholder:text-slate-400 bg-white focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:bg-zink-800 dark:border-zink-600 dark:text-zink-100 dark:placeholder:text-zink-400"
                  placeholder="https://example.com/original-post">
                <button type="button"
                  onclick="document.getElementById('canonical_url').value = window.location.origin + '/posts/<?= $post['slug'] ?? '' ?>'"
                  class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-custom-500 dark:text-zink-500 dark:hover:text-custom-400"
                  title="Use current post URL">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                  </svg>
                </button>
              </div>
              <p class="mt-1.5 text-[11px] text-slate-500 dark:text-zink-400">
                Prevent duplicate content issues if this post is republished elsewhere
              </p>
            </div>

            <!-- Robot Meta Tags (noindex/nofollow) -->
            <div class="pt-3 border-t border-slate-200 dark:border-zink-600">
              <p class="mb-2 text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300">
                Search engine visibility
              </p>
              <div class="flex flex-wrap gap-4">
                <label class="flex items-center gap-2 cursor-pointer group">
                  <input type="checkbox" 
                    name="meta_noindex" 
                    value="1"
                    <?= !empty($post['meta_noindex']) ? 'checked' : '' ?>
                    class="text-custom-500 border-slate-300 rounded focus:ring-custom-500 dark:border-zink-600">
                  <span class="text-xs text-slate-700 dark:text-zink-200  ">
                    Noindex
                  </span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer group">
                  <input type="checkbox" 
                    name="meta_nofollow" 
                    value="1"
                    <?= !empty($post['meta_nofollow']) ? 'checked' : '' ?>
                    class="text-custom-500 border-slate-300 rounded focus:ring-custom-500 dark:border-zink-600">
                  <span class="text-xs text-slate-700 dark:text-zink-200  ">
                    Nofollow
                  </span>
                </label>
              </div>
              <p class="mt-2 text-[11px] text-slate-500 dark:text-zink-400">
                <strong>Noindex:</strong> Prevent search engines from indexing this page. 
                <strong>Nofollow:</strong> Tell search engines not to follow links on this page.
              </p>
            </div>

            <!-- SEO Preview -->
            <div class="pt-3 mt-3 border-t border-slate-200 dark:border-zink-600">
              <p class="mb-2 text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300">
                Search preview
              </p>
              <div class="p-3 rounded-md bg-slate-50 dark:bg-zink-800/50">
                <div id="seo_preview_title" class="text-sm font-medium text-blue-700 dark:text-blue-400 line-clamp-1">
                  <?= e($post['title'] ?? 'Your post title will appear here') ?>
                </div>
                <div class="mt-0.5 text-xs text-emerald-700 dark:text-emerald-400">
                  <?= $_SERVER['HTTP_HOST'] ?? 'example.com' ?> › posts › <?= e($post['slug'] ?? 'post-slug') ?>
                </div>
                <div id="seo_preview_desc" class="mt-1 text-xs leading-relaxed text-slate-600 dark:text-zink-300 line-clamp-2">
                  <?= e($post['excerpt'] ?? 'Your meta description or excerpt will appear here in search results...') ?>
                </div>
              </div>
            </div>

          </div>
        </section>

        <!-- Social Media Preview Card -->
        <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
          
          <!-- Collapsible Header -->
          <button type="button"
            onclick="this.querySelector('svg').classList.toggle('rotate-180'); document.getElementById('social_fields').classList.toggle('hidden')"
            class="flex items-center justify-between w-full p-4 text-left border-b border-slate-200 dark:border-zink-600 hover:bg-slate-50 dark:hover:bg-zink-600/50 transition-colors">
            <div>
              <h3 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Social Media</h3>
              <p class="mt-0.5 text-xs text-slate-500 dark:text-zink-300">
                Control how this post appears when shared
              </p>
            </div>
            <svg class="w-5 h-5 text-slate-400 transition-transform dark:text-zink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>

          <!-- Social Media Fields (Initially collapsed) -->
          <div id="social_fields" class="hidden p-4 space-y-4 md:p-5">
            
            <!-- OG Title -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label for="og_title" class="text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300">
                  Social title
                </label>
                <span id="og_title_count" 
                  class="text-xs tabular-nums text-slate-400 dark:text-zink-400">
                  0/60
                </span>
              </div>
              <input
                id="og_title"
                name="og_title"
                type="text"
                maxlength="70"
                value="<?= e($post['og_title'] ?? '') ?>"
                oninput="updateCharCount('og_title', 60); updateSocialPreview()"
                class="block w-full px-3 py-2 text-sm border rounded-md outline-none border-slate-300/80 text-slate-900 placeholder:text-slate-400 bg-white focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:bg-zink-800 dark:border-zink-600 dark:text-zink-100 dark:placeholder:text-zink-400"
                placeholder="Defaults to post title">
              <p class="mt-1.5 text-[11px] text-slate-500 dark:text-zink-400">
                Recommended: 55-60 characters
              </p>
            </div>

            <!-- OG Description -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label for="og_description" class="text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300">
                  Social description
                </label>
                <span id="og_description_count" 
                  class="text-xs tabular-nums text-slate-400 dark:text-zink-400">
                  0/65
                </span>
              </div>
              <textarea
                id="og_description"
                name="og_description"
                rows="2"
                maxlength="100"
                oninput="updateCharCount('og_description', 65); updateSocialPreview()"
                class="block w-full px-3 py-2 text-sm border rounded-md outline-none border-slate-300/80 text-slate-900 placeholder:text-slate-400 bg-white focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:bg-zink-800 dark:border-zink-600 dark:text-zink-100 dark:placeholder:text-zink-400"
                placeholder="Engaging description for social shares"><?= e($post['og_description'] ?? '') ?></textarea>
              <p class="mt-1.5 text-[11px] text-slate-500 dark:text-zink-400">
                Recommended: 60-65 characters
              </p>
            </div>

            <!-- OG Image URL (optional if using featured image) -->
            <div>
              <label for="og_image" class="block mb-1.5 text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300">
                Social image URL
              </label>
              <input
                id="og_image"
                name="og_image"
                type="url"
                value="<?= e($post['og_image'] ?? '') ?>"
                oninput="updateSocialPreview()"
                class="block w-full px-3 py-2 text-sm border rounded-md outline-none border-slate-300/80 text-slate-900 placeholder:text-slate-400 bg-white focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:bg-zink-800 dark:border-zink-600 dark:text-zink-100 dark:placeholder:text-zink-400"
                placeholder="Defaults to featured image">
              <p class="mt-1.5 text-[11px] text-slate-500 dark:text-zink-400">
                Recommended: 1200 × 630 pixels (1.91:1 ratio)
              </p>
            </div>

            <!-- Twitter Card Type -->
            <div>
              <label for="twitter_card_type" class="block mb-1.5 text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300">
                Twitter card type
              </label>
              <select
                id="twitter_card_type"
                name="twitter_card_type"
                class="block w-full px-3 py-2 text-sm border rounded-md outline-none border-slate-300/80 text-slate-900 bg-white focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:bg-zink-800 dark:border-zink-600 dark:text-zink-100">
                <option value="summary" <?= ($post['twitter_card_type'] ?? '') === 'summary' ? 'selected' : '' ?>>
                  Summary Card
                </option>
                <option value="summary_large_image" <?= ($post['twitter_card_type'] ?? 'summary_large_image') === 'summary_large_image' ? 'selected' : '' ?>>
                  Summary Card with Large Image
                </option>
              </select>
              <p class="mt-1.5 text-[11px] text-slate-500 dark:text-zink-400">
                Large image recommended for better engagement
              </p>
            </div>

            <!-- Social Preview -->
            <div class="pt-3 mt-3 border-t border-slate-200 dark:border-zink-600">
              <p class="mb-2 text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300">
                Social media preview
              </p>
              <div class="overflow-hidden border rounded-md border-slate-200 dark:border-zink-600">
                <!-- Preview Image -->
                <div id="social_preview_image" class="relative w-full bg-slate-100 dark:bg-zink-800 aspect-[1.91/1] flex items-center justify-center">
                  <svg class="w-12 h-12 text-slate-300 dark:text-zink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                </div>
                <!-- Preview Content -->
                <div class="p-3 bg-white dark:bg-zink-700">
                  <div class="text-xs text-slate-500 dark:text-zink-400">
                    <?= $_SERVER['HTTP_HOST'] ?? 'example.com' ?>
                  </div>
                  <div id="social_preview_title" class="mt-1 text-sm font-semibold text-slate-900 dark:text-zink-100 line-clamp-2">
                    <?= e($post['title'] ?? 'Your post title will appear here') ?>
                  </div>
                  <div id="social_preview_desc" class="mt-0.5 text-xs text-slate-600 dark:text-zink-300 line-clamp-2">
                    <?= e($post['excerpt'] ?? 'Your description will appear here when shared on social media...') ?>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </section>

      </aside>
    </div>

<script>
(function () {
    var field = document.getElementById('tagsField');
    var chips = document.getElementById('tagChips');
    var input = document.getElementById('tagInput');
    if (!field || !chips || !input) return;

    var tags = field.value ? field.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean) : [];

    function sync() {
        field.value = tags.join(',');
        chips.innerHTML = '';
        tags.forEach(function (name, i) {
            var chip = document.createElement('span');
            chip.className = 'inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-full bg-custom-50 text-custom-700 border border-custom-100 dark:bg-custom-500/20 dark:text-custom-300';
            chip.textContent = name;
            var x = document.createElement('button');
            x.type = 'button';
            x.className = 'leading-none text-sm hover:text-red-600';
            x.innerHTML = '&times;';
            x.addEventListener('click', function () { tags.splice(i, 1); sync(); });
            chip.appendChild(x);
            chips.appendChild(chip);
        });
    }

    function add(value) {
        value = value.trim().replace(/,+$/, '').trim();
        if (value && tags.indexOf(value) === -1) tags.push(value);
        input.value = '';
        sync();
    }

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            add(input.value);
        } else if (e.key === 'Backspace' && input.value === '' && tags.length) {
            tags.pop();
            sync();
        }
    });
    input.addEventListener('blur', function () { if (input.value.trim()) add(input.value); });

    sync();
})();
</script>