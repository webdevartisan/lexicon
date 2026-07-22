<?php
/**
 * Shared post editor body, used by both the create and edit screens.
 *
 * Layout puts everything a writer touches constantly in the main column
 * (editor, then SEO, then social) and leaves the sidebar for the short
 * metadata cards. Actions live in the floating bar rather than a sidebar card,
 * so saving is reachable from anywhere on the page.
 */
$postStatus = ($post['status'] ?? 'draft');

// The publish date only makes sense while a post is still on its way out.
$showScheduling = !in_array($postStatus, ['published', 'archived'], true);
?>
    <input type="hidden" name="_method" value="PUT">
    <input type="hidden" name="author_id" value="<?= e($currentUser['id'] ?? $post['author_id'] ?? '') ?>">
    {{ csrf_field() }}

    {% include "partials/dashboard/post/_action_bar.lex.php" %}

    <?php /* Most fields render no inline error, so a rejected save used to say only "correct the errors". */ ?>
    <?php if (!empty($errors)) { ?>
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-500/30 dark:bg-red-500/10" role="alert">
      <div class="flex items-center gap-2">
        <i data-lucide="alert-circle" class="size-4 shrink-0 text-red-600 dark:text-red-400" aria-hidden="true"></i>
        <h3 class="text-sm font-semibold text-red-800 dark:text-red-300">This post wasn't saved</h3>
      </div>
      <ul class="mt-2 space-y-1 ltr:pl-6 rtl:pr-6">
        <?php foreach ($errors as $field => $messages) { ?>
          <?php foreach ((array) $messages as $message) { ?>
        <li class="list-disc text-xs text-red-700 dark:text-red-300">
          <?= e($message) ?>
        </li>
          <?php } ?>
        <?php } ?>
      </ul>
    </div>
    <?php } ?>

    {% if (($postStatus !== 'draft') && ($postStatus !== 'pending')): %}
      <div class="flex justify-between">
        {% cmp="urlWithOpenButton" previewUrl="{$postUrl}" %}
      </div>
    {% endif; %}

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] max-lg:pb-24">

      <main class="min-w-0 space-y-6">

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-zink-600 dark:bg-zink-700">
          <div class="space-y-6 p-4 md:p-5">

            <?php $title = $post['title'] ?? ''; ?>
            {% cmp="input" type="text" label="title" value="{$title}" %}

            {% if (($postStatus === 'draft') || ($postStatus === 'pending')): %}
            <?php $slug = $post['slug'] ?? ''; ?>
            <?php $baseUrl = base_url().'/blog/'.$blog['blog_slug'].'/' ?? '/'; ?>
            {% cmp="input" type="text" label="Post Link" name="slug" value="{$slug}" placeholder="a-short-url-friendly-title" prefix="{$baseUrl}" underlabel="Lowercase letters, numbers, and hyphens only."%}
            {% endif; %}

            <div>
              <?php $content = old('content') ?? $post['content'] ?? ''; ?>
              <label for="content" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zink-300">
                Body
              </label>
              <textarea
                id="content"
                name="content"
                rows="14"
                class="js-post-editor block w-full rounded-md border border-slate-300/80 bg-white px-3 py-2 text-sm text-slate-900 outline-none placeholder:text-slate-400 focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:border-zink-600 dark:bg-zink-800 dark:text-zink-100"
                placeholder="Write your post content here...">{{ content }}</textarea>
                <?php if (!empty($errors['content'])) { ?>
                  <?php foreach ($errors['content'] as $msg) { ?>
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400"> <?= $msg ?> </p>
                  <?php } ?>
                <?php } ?>
            </div>

            <?php $excerpt = old('excerpt') ?? $post['excerpt'] ?? ''; ?>
            {% cmp="input" type="textarea" label="excerpt" value="{$excerpt}" rows="4" placeholder="Optional short summary used in listings and meta description when not set explicitly." %}

          </div>
        </section>

        {% include "partials/dashboard/post/_seo_card.lex.php" %}
        {% include "partials/dashboard/post/_social_card.lex.php" %}

      </main>

      <aside class="w-full shrink-0 space-y-4 lg:sticky lg:top-[calc(theme('spacing.header')_+_5rem)] lg:w-72 lg:self-start">

        <!-- Reviewer feedback — shown to the author when a reviewer has requested changes -->
        <?php if (!empty($latestReview) && !empty($latestReview['feedback']) && ($workflowState ?? '') === 'needs_changes') { ?>
        <section class="rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10">
            <div class="flex items-center gap-2 border-b border-amber-200 p-4 dark:border-amber-500/30">
                <i data-lucide="message-square" class="size-4 shrink-0 text-amber-600 dark:text-amber-400"></i>
                <h3 class="text-sm font-semibold text-amber-800 dark:text-amber-300">Reviewer feedback</h3>
            </div>
            <div class="space-y-2 p-4">
                <p class="text-xs leading-relaxed text-amber-900 dark:text-amber-200">
                    <?= e($latestReview['feedback']) ?>
                </p>
                <p class="text-[11px] text-amber-600 dark:text-amber-400">
                    — <?= e($latestReview['reviewer_username'] ?? 'Reviewer') ?>,
                    <?= e(local_datetime($latestReview['reviewed_at'] ?? null, 'M j, Y')) ?>
                </p>
            </div>
        </section>
        <?php } ?>

        <!-- Featured Image -->
        <?php $blogIdForLibrary = (string) ($post['blog_id'] ?? $blog['id'] ?? ''); ?>
        {% cmp="dropzone" label="Featured Image" resource="{$post}" library="{$blogIdForLibrary}" %}

        <!-- Organize -->
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-zink-600 dark:bg-zink-700">
          <div class="space-y-4 p-4">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Organize</h3>

            <div>
              <label for="category_id" class="mb-1.5 block text-xs font-medium text-slate-500 dark:text-zink-300">Category</label>
              <?php $currentCategory = (int) (old('category_id') ?? $post['category_id'] ?? 0); ?>
              <select id="category_id" name="category_id"
                class="block w-full rounded-md border border-slate-300/80 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:border-zink-600 dark:bg-zink-800 dark:text-zink-100">
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
              <label for="tagInput" class="mb-1.5 block text-xs font-medium text-slate-500 dark:text-zink-300">Tags</label>
              <div id="tagChips" class="mb-1.5 flex flex-wrap gap-1.5"></div>
              <div class="relative">
                <input id="tagInput" type="text" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="tagSuggestions"
                  placeholder="Type a tag, press Enter"
                  class="block w-full rounded-md border border-slate-300/80 bg-white px-3 py-2 text-sm text-slate-900 outline-none placeholder:text-slate-400 focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:border-zink-600 dark:bg-zink-800 dark:text-zink-100 dark:placeholder:text-zink-400"
                  data-tags='<?= e(json_encode(array_values(array_map(static fn ($t) => (string) $t['name'], $allTags ?? [])))) ?>'>
                <div id="tagSuggestions" role="listbox"
                  class="absolute inset-x-0 top-full z-40 mt-1 hidden max-h-48 overflow-y-auto rounded-md border border-slate-200 bg-white py-1 shadow-md dark:border-zink-500 dark:bg-zink-600"></div>
              </div>
              <input type="hidden" name="tags" id="tagsField" value="<?= e(implode(',', $postTags ?? [])) ?>">
              <p class="mt-1 text-[11px] text-slate-400 dark:text-zink-400">Optional. Suggestions come from this blog's existing tags; new ones are created as you type.</p>
            </div>
          </div>
        </section>

        <!-- Post settings -->
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-zink-600 dark:bg-zink-700">
          <div class="space-y-4 p-4">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Post settings</h3>

            <?php if ($showScheduling) { ?>
            <div>
              <label for="published_at" class="mb-1.5 block text-xs font-medium text-slate-500 dark:text-zink-300">
                Publish date &amp; time
              </label>
              <?php $publishedAt = old('published_at') ?? $post['published_at'] ?? null; ?>
              <input type="hidden" name="timezone" id="timezone">
              <input
                id="published_at"
                name="published_at"
                value="<?= e($publishedAt) ?>"
                type="text"
                class="form-input border-slate-200 placeholder:text-slate-400 focus:border-custom-500 focus:outline-none disabled:border-slate-300 disabled:bg-slate-100 disabled:text-slate-500 dark:border-zink-500 dark:bg-zink-700 dark:text-zink-100 dark:placeholder:text-zink-200 dark:focus:border-custom-800 dark:disabled:border-zink-500 dark:disabled:bg-zink-600 dark:disabled:text-zink-200"
                data-provider="flatpickr"
                data-min-date="today"
                data-date-format="d.m.y"
                data-enable-time=""
                readonly="readonly"
                placeholder="Publish immediately">
                {% if errors.published_at|notempty %}
                    {% foreach ($errors['published_at'] as $msg): %}
                      <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ msg }}</p>
                    {% endforeach %}
                {% endif %}
              <div class="mt-1.5 flex items-start justify-between gap-2">
                <p data-schedule-hint class="text-[11px] leading-relaxed text-slate-500 dark:text-zink-400">
                  Leave empty to go live as soon as you publish.
                </p>
                <button type="button" data-clear-schedule
                  class="shrink-0 text-[11px] font-medium text-slate-500 underline hover:no-underline dark:text-zink-400 <?= $publishedAt ? '' : 'hidden' ?>">
                  Clear
                </button>
              </div>
            </div>
            <?php } ?>

            <div class="space-y-2.5 <?= $showScheduling ? 'border-t border-slate-200 pt-4 dark:border-zink-600' : '' ?>">
              <label class="flex cursor-pointer items-center gap-2">
                <input
                  type="checkbox"
                  name="comments_enabled"
                  value="1"
                  <?= (!isset($post['comments_enabled']) || !empty($post['comments_enabled'])) ? 'checked' : '' ?>
                  class="rounded border-slate-300 text-custom-500 focus:ring-custom-500 dark:border-zink-600">
                <span class="text-xs text-slate-700 dark:text-zink-200">Allow comments</span>
              </label>
              <label class="flex cursor-pointer items-center gap-2">
                <input
                  type="checkbox"
                  name="is_featured"
                  value="1"
                  <?= !empty($post['is_featured']) ? 'checked' : '' ?>
                  class="rounded border-slate-300 text-custom-500 focus:ring-custom-500 dark:border-zink-600">
                <span class="text-xs text-slate-700 dark:text-zink-200">Feature on homepage</span>
              </label>
              <p class="text-[11px] text-slate-400 dark:text-zink-400">Shows this post as the blog's headline. Only one post can be featured; takes effect once published.</p>
            </div>
          </div>
        </section>

        <!-- Danger zone -->
        {% if post|notempty %}
        <section class="rounded-lg border border-red-200 bg-red-50/50 dark:border-red-500/30 dark:bg-red-500/5">
          <div class="space-y-2 p-4">
            <h3 class="text-sm font-semibold text-red-700 dark:text-red-400">Danger zone</h3>
            <p class="text-[11px] leading-relaxed text-red-600/80 dark:text-red-400/80">
              Deleting removes the post and its comments. Archive it instead if you only want it off the public site.
            </p>
            {% cmp="btn" type="button" variant="red" icon="trash-2" label="Delete post" size="xs" dataModalTarget="confirmModal" %}
          </div>
        </section>
        {% endif %}

      </aside>
    </div>

<script>
(function () {
    var field = document.getElementById('tagsField');
    var chips = document.getElementById('tagChips');
    var input = document.getElementById('tagInput');
    var panel = document.getElementById('tagSuggestions');
    if (!field || !chips || !input || !panel) return;

    var tags = field.value ? field.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean) : [];

    var known = [];
    try { known = JSON.parse(input.dataset.tags || '[]'); } catch (e) { known = []; }

    var activeIndex = -1;
    var visible = [];

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
        closePanel();
        sync();
    }

    function closePanel() {
        panel.classList.add('hidden');
        input.setAttribute('aria-expanded', 'false');
        activeIndex = -1;
        visible = [];
    }

    function paintPanel() {
        panel.innerHTML = '';
        visible.forEach(function (name, i) {
            var item = document.createElement('button');
            item.type = 'button';
            item.setAttribute('role', 'option');
            item.className = 'flex w-full px-3 py-1.5 text-sm text-left transition-colors '
                + (i === activeIndex
                    ? 'bg-custom-50 text-custom-700 dark:bg-custom-500/20 dark:text-custom-300'
                    : 'text-slate-600 hover:bg-slate-50 dark:text-zink-200 dark:hover:bg-zink-500');
            item.textContent = name;
            // mousedown fires before the input's blur, so the click always lands
            item.addEventListener('mousedown', function (e) {
                e.preventDefault();
                add(name);
            });
            panel.appendChild(item);
        });
        panel.classList.toggle('hidden', visible.length === 0);
        input.setAttribute('aria-expanded', visible.length ? 'true' : 'false');
    }

    function suggest() {
        var q = input.value.trim().toLowerCase();
        if (q === '') { closePanel(); paintPanel(); return; }
        visible = known.filter(function (name) {
            return tags.indexOf(name) === -1 && name.toLowerCase().indexOf(q) !== -1;
        }).slice(0, 8);
        activeIndex = -1;
        paintPanel();
    }

    input.addEventListener('input', suggest);
    input.addEventListener('focus', suggest);

    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' && visible.length) {
            e.preventDefault();
            activeIndex = (activeIndex + 1) % visible.length;
            paintPanel();
        } else if (e.key === 'ArrowUp' && visible.length) {
            e.preventDefault();
            activeIndex = (activeIndex - 1 + visible.length) % visible.length;
            paintPanel();
        } else if (e.key === 'Escape') {
            closePanel();
            paintPanel();
        } else if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            add(activeIndex >= 0 && visible[activeIndex] ? visible[activeIndex] : input.value);
        } else if (e.key === 'Backspace' && input.value === '' && tags.length) {
            tags.pop();
            sync();
        }
    });

    input.addEventListener('blur', function () {
        closePanel();
        paintPanel();
        if (input.value.trim()) add(input.value);
    });

    sync();
})();
</script>
