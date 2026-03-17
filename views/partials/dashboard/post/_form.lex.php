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
            </div>

            <!-- Excerpt / summary -->
            <?php $excerpt = old('excerpt') ?? $post['excerpt'] ?? ''; ?>
            {% cmp="input" type="textarea" label="excerpt" value="{$excerpt}" rows="4" placeholder="Optional short summary used in listings and meta description when not set explicitly." %}

          </div>

        </section>
      </main>
      <aside class="sticky top-4 space-y-4 shrink-0 w-full sm:w-64">
        <!-- Publishing Actions Card -->
        <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
          <div class="p-4 space-y-4">
            <!-- Primary Action Buttons -->
            <div class="flex w-full gap-2">
              <?php $label = $isPublished ? 'Update' : 'Save Draft' ?>
              {% cmp="btn" type="submit" variant="blue" icon="save" label="{$label}" addClass="flex-1 " %}
              {% cmp="btn" href="/dashboard" variant="slate" icon="step-back" label="Back" %}
            </div>

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
              <div class="flex flex-col sm:flex-row w-full border rounded-md border-slate-200 dark:border-zink-600">
                
              {% if (($postStatus !== 'published') && ($postStatus !== 'archived')): %}
                <label class="flex-1 text-center cursor-pointer group <?= $isPublished ? 'opacity-50 cursor-not-allowed' : '' ?>">
                  <input
                    type="radio"
                    name="status"
                    value="draft"
                    class="sr-only peer"
                    <?= $postStatus === 'draft' ? 'checked' : '' ?>
                    <?= $isPublished ? 'disabled' : '' ?>>
                  <span class="block px-3 py-2 text-xs font-medium transition-colors border-r text-slate-700 bg-slate-50 border-slate-200 peer-checked:bg-slate-500 peer-checked:text-white peer-checked:border-slate-500  dark:bg-zink-600 dark:text-zink-200 dark:border-zink-600 dark:peer-checked:bg-slate-400 dark:peer-checked:border-slate-400  rounded-l-md">
                    Draft
                  </span>
                </label>

                <label class="flex-1 text-center cursor-pointer group <?= $isPublished ? 'opacity-50 cursor-not-allowed' : '' ?>">
                  <input
                    type="radio"
                    name="status"
                    value="pending"
                    class="sr-only peer"
                    <?= $postStatus === 'pending' ? 'checked' : '' ?>
                    <?= $isPublished ? 'disabled' : '' ?>>
                  <span class="block px-3 py-2 text-xs font-medium transition-colors text-slate-700 bg-slate-50 peer-checked:bg-amber-500 peer-checked:text-white peer-checked:border-amber-500  dark:bg-zink-600 dark:text-zink-200 dark:peer-checked:bg-amber-500 dark:peer-checked:border-amber-500 <?= $isDraft ? 'rounded-r-md' : '' ?>">
                    Pending
                  </span>
                </label>
                {% endif %}
                {% if isDraft|empty %}
                <label class="flex-1 text-center cursor-pointer group <?= $isDraft ? 'opacity-50 cursor-not-allowed' : '' ?>">
                  <input
                    type="radio"
                    name="status"
                    value="published"
                    class="sr-only peer"
                    <?= $postStatus === 'published' ? 'checked' : '' ?>
                    <?= $isDraft ? 'disabled' : '' ?>>
                  <span class="block px-3 py-2 text-xs font-medium transition-colors border-r text-slate-700 bg-slate-50 border-slate-200 peer-checked:bg-emerald-500 peer-checked:text-white peer-checked:border-emerald-500  dark:bg-zink-600 dark:text-zink-200 dark:border-zink-600 dark:peer-checked:bg-emerald-500 dark:peer-checked:border-emerald-500 <?= ($isPublished || $isArchived) ? 'rounded-l-md' : '' ?>">
                    Published
                  </span>
                </label>
                {% endif %}
                
                {% if isDraft|empty %}
                {% if isPending|empty %}
                <label class="flex-1 text-center cursor-pointer group <?= $isDraft ? 'opacity-50 cursor-not-allowed' : '' ?>">
                  <input
                    type="radio"
                    name="status"
                    value="archived"
                    class="sr-only peer"
                    <?= $postStatus === 'archived' ? 'checked' : '' ?>>
                  <span class="block px-3 py-2 text-xs font-medium transition-colors text-slate-700 bg-slate-50 peer-checked:bg-slate-800 peer-checked:text-white peer-checked:border-slate-800  dark:bg-zink-600 dark:text-zink-200 dark:peer-checked:bg-slate-800 dark:peer-checked:border-slate-800  rounded-r-md">
                    Archived
                  </span>
                </label>
                {% endif %}{% endif %}
              </div>

                <?php if ($isPublished) { ?>
                <p class="mt-2 text-[11px] text-slate-500 dark:text-zink-400">
                ℹ️ Published posts cannot be reverted to Draft or Pending. Use "Archived" to hide from public.
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

        <!-- Featured Image -->
        {% cmp="dropzone2" label="Featured Image" resource="{$post}" %}

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