<?php
/**
 * Social sharing card for the post editor.
 *
 * One set of og: fields drives every platform, because that is how the
 * protocol actually works: Facebook, LinkedIn, WhatsApp, Slack and Discord all
 * read the same tags and cannot be given different text. X is the exception,
 * it reads its own twitter: namespace, so it gets the one override that is
 * technically possible.
 *
 * The tabs are previews, not separate field sets. Their value is showing that
 * each service crops and truncates differently, most notably LinkedIn, which
 * ignores the description entirely.
 *
 * Expects: $post, $blog
 */
$inputClass = 'block w-full px-3 py-2 text-sm border rounded-md outline-none border-slate-300/80 text-slate-900 placeholder:text-slate-400 bg-white focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:bg-zink-800 dark:border-zink-600 dark:text-zink-100 dark:placeholder:text-zink-400';
$labelClass = 'block mb-1.5 text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300';
$hintClass = 'mt-1.5 text-[11px] text-slate-500 dark:text-zink-400';

$host = parse_url(base_url(), PHP_URL_HOST) ?: 'example.com';
$cardType = $post['twitter_card_type'] ?? 'summary_large_image';
$hasTwitterOverride = !empty($post['twitter_title']) || !empty($post['twitter_description']) || !empty($post['twitter_image']);

$platforms = [
    'facebook' => ['label' => 'Facebook', 'icon' => 'facebook'],
    'x' => ['label' => 'X', 'icon' => 'twitter'],
    'linkedin' => ['label' => 'LinkedIn', 'icon' => 'linkedin'],
    'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'message-circle'],
    'discord' => ['label' => 'Discord', 'icon' => 'message-square'],
];
?>
<section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-zink-600 dark:bg-zink-700"
         data-social-card
         data-host="<?= e($host) ?>">

  <div class="flex items-center gap-2 border-b border-slate-200 p-4 dark:border-zink-600">
    <i data-lucide="share-2" class="size-4 shrink-0 text-slate-400 dark:text-zink-400" aria-hidden="true"></i>
    <div>
      <h3 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Social sharing</h3>
      <p class="mt-0.5 text-xs text-slate-500 dark:text-zink-300">How this post looks when someone shares the link</p>
    </div>
  </div>

  <div class="grid gap-6 p-4 md:grid-cols-[minmax(0,1fr)_340px] md:p-5">

    <?php /* min-w-0 stops a grid item's auto minimum from overflowing on narrow screens */ ?>
    <div class="min-w-0 space-y-4">

      <div>
        <div class="mb-1.5 flex items-center justify-between">
          <label for="og_title" class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zink-300">Social title</label>
          <span id="og_title_count" class="text-xs tabular-nums text-slate-400 dark:text-zink-400">0/60</span>
        </div>
        <input id="og_title" name="og_title" type="text" maxlength="70"
          value="<?= e($post['og_title'] ?? '') ?>"
          data-char-limit="60"
          class="<?= $inputClass ?>"
          placeholder="Defaults to post title">
        <p class="<?= $hintClass ?>">55-60 characters survives on every platform.</p>
      </div>

      <div>
        <div class="mb-1.5 flex items-center justify-between">
          <label for="og_description" class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zink-300">Social description</label>
          <span id="og_description_count" class="text-xs tabular-nums text-slate-400 dark:text-zink-400">0/65</span>
        </div>
        <textarea id="og_description" name="og_description" rows="2" maxlength="100"
          data-char-limit="65"
          class="<?= $inputClass ?>"
          placeholder="Engaging description for social shares"><?= e($post['og_description'] ?? '') ?></textarea>
        <p class="<?= $hintClass ?>">60-65 characters. LinkedIn drops this entirely, so don't rely on it there.</p>
      </div>

      <div>
        <label for="og_image" class="<?= $labelClass ?>">Social image URL</label>
        <input id="og_image" name="og_image" type="url"
          value="<?= e($post['og_image'] ?? '') ?>"
          class="<?= $inputClass ?>"
          placeholder="Defaults to featured image">
        <p class="<?= $hintClass ?>">1200 &times; 630 pixels (1.91:1). Smaller images get cropped or dropped.</p>
      </div>

      <div>
        <label for="og_image_alt" class="<?= $labelClass ?>">Image alt text</label>
        <input id="og_image_alt" name="og_image_alt" type="text" maxlength="255"
          value="<?= e($post['og_image_alt'] ?? '') ?>"
          class="<?= $inputClass ?>"
          placeholder="Describe the image for screen readers">
        <p class="<?= $hintClass ?>">Read aloud in place of the image on assistive technology.</p>
      </div>

      <div>
        <label for="twitter_card_type" class="<?= $labelClass ?>">X card type</label>
        <select id="twitter_card_type" name="twitter_card_type"
          class="block w-full rounded-md border border-slate-300/80 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:border-zink-600 dark:bg-zink-800 dark:text-zink-100">
          <option value="summary_large_image" <?= $cardType === 'summary_large_image' ? 'selected' : '' ?>>Large image</option>
          <option value="summary" <?= $cardType === 'summary' ? 'selected' : '' ?>>Small square thumbnail</option>
        </select>
        <p class="<?= $hintClass ?>">Switch the X tab to see the difference.</p>
      </div>

      <details class="rounded-md border border-slate-200 dark:border-zink-600" <?= $hasTwitterOverride ? 'open' : '' ?>>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 p-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:text-zink-100 dark:hover:bg-zink-600/50">
          <span class="flex items-center gap-2">
            <i data-lucide="twitter" class="size-4 shrink-0 text-slate-400" aria-hidden="true"></i>
            Override for X
          </span>
          <i data-lucide="chevron-down" class="size-4 shrink-0 text-slate-400" aria-hidden="true"></i>
        </summary>
        <div class="space-y-3 border-t border-slate-200 p-3 dark:border-zink-600">
          <p class="text-[11px] leading-relaxed text-slate-500 dark:text-zink-400">
            X is the only platform that reads its own tags, so it is the only one that can differ.
            Leave these empty and it uses the social fields above.
          </p>
          <div>
            <label for="twitter_title" class="<?= $labelClass ?>">X title</label>
            <input id="twitter_title" name="twitter_title" type="text" maxlength="70"
              value="<?= e($post['twitter_title'] ?? '') ?>"
              class="<?= $inputClass ?>"
              placeholder="Inherits the social title">
          </div>
          <div>
            <label for="twitter_description" class="<?= $labelClass ?>">X description</label>
            <textarea id="twitter_description" name="twitter_description" rows="2" maxlength="200"
              class="<?= $inputClass ?>"
              placeholder="Inherits the social description"><?= e($post['twitter_description'] ?? '') ?></textarea>
          </div>
          <div>
            <label for="twitter_image" class="<?= $labelClass ?>">X image URL</label>
            <input id="twitter_image" name="twitter_image" type="url"
              value="<?= e($post['twitter_image'] ?? '') ?>"
              class="<?= $inputClass ?>"
              placeholder="Inherits the social image">
          </div>
        </div>
      </details>

    </div>

    <div class="min-w-0 md:sticky md:top-[calc(theme('spacing.header')_+_5rem)] md:self-start">

      <div class="mb-2 flex flex-wrap gap-1" role="tablist" aria-label="Platform previews">
        <?php foreach ($platforms as $key => $meta) { ?>
        <button type="button" role="tab"
          data-social-tab="<?= e($key) ?>"
          aria-selected="<?= $key === 'facebook' ? 'true' : 'false' ?>"
          class="rounded-md px-2.5 py-1.5 text-[11px] font-medium transition-colors
                 text-slate-500 hover:bg-slate-100 dark:text-zink-300 dark:hover:bg-zink-600
                 aria-selected:bg-slate-900 aria-selected:text-white dark:aria-selected:bg-zink-500 dark:aria-selected:text-white">
          <?= e($meta['label']) ?>
        </button>
        <?php } ?>
      </div>

      <!-- Facebook: 1.91:1, uppercase domain above the title, one line of description -->
      <div data-social-panel="facebook" class="overflow-hidden rounded-md border border-slate-200 dark:border-zink-600">
        <div data-social-image class="flex aspect-[1.91/1] w-full items-center justify-center bg-slate-100 bg-cover bg-center dark:bg-zink-800">
          <i data-lucide="image" class="size-10 text-slate-300 dark:text-zink-600" aria-hidden="true"></i>
        </div>
        <div class="bg-slate-50 p-3 dark:bg-zink-800/60">
          <div data-social-host class="text-[10px] uppercase tracking-wide text-slate-500 dark:text-zink-400"><?= e($host) ?></div>
          <div data-social-title class="mt-0.5 line-clamp-2 text-sm font-semibold leading-snug text-slate-900 dark:text-zink-100"></div>
          <div data-social-desc class="mt-0.5 line-clamp-1 text-xs text-slate-600 dark:text-zink-300"></div>
        </div>
      </div>

      <!-- X: the two card types are structurally different, so each gets its own markup -->
      <div data-social-panel="x" hidden>
        <div data-x-card="summary_large_image" class="overflow-hidden rounded-2xl border border-slate-200 dark:border-zink-600">
          <div data-social-image class="flex aspect-[1.91/1] w-full items-center justify-center bg-slate-100 bg-cover bg-center dark:bg-zink-800">
            <i data-lucide="image" class="size-10 text-slate-300 dark:text-zink-600" aria-hidden="true"></i>
          </div>
          <div class="p-3">
            <div data-social-title class="line-clamp-1 text-sm text-slate-900 dark:text-zink-100"></div>
            <div data-social-desc class="mt-0.5 line-clamp-2 text-xs text-slate-500 dark:text-zink-400"></div>
            <div data-social-host class="mt-1 text-xs text-slate-500 dark:text-zink-400"><?= e($host) ?></div>
          </div>
        </div>
        <div data-x-card="summary" class="flex overflow-hidden rounded-2xl border border-slate-200 dark:border-zink-600" hidden>
          <div data-social-image class="flex aspect-square w-[125px] shrink-0 items-center justify-center border-slate-200 bg-slate-100 bg-cover bg-center ltr:border-r rtl:border-l dark:border-zink-600 dark:bg-zink-800">
            <i data-lucide="image" class="size-8 text-slate-300 dark:text-zink-600" aria-hidden="true"></i>
          </div>
          <div class="min-w-0 self-center p-3">
            <div data-social-host class="text-xs text-slate-500 dark:text-zink-400"><?= e($host) ?></div>
            <div data-social-title class="mt-0.5 line-clamp-2 text-sm text-slate-900 dark:text-zink-100"></div>
            <div data-social-desc class="mt-0.5 line-clamp-2 text-xs text-slate-500 dark:text-zink-400"></div>
          </div>
        </div>
      </div>

      <!-- LinkedIn: no description at all, title sits on a grey plate under the image -->
      <div data-social-panel="linkedin" hidden class="overflow-hidden rounded-md border border-slate-200 dark:border-zink-600">
        <div data-social-image class="flex aspect-[1.91/1] w-full items-center justify-center bg-slate-100 bg-cover bg-center dark:bg-zink-800">
          <i data-lucide="image" class="size-10 text-slate-300 dark:text-zink-600" aria-hidden="true"></i>
        </div>
        <div class="bg-white p-3 dark:bg-zink-700">
          <div data-social-title class="line-clamp-2 text-sm font-semibold leading-snug text-slate-900 dark:text-zink-100"></div>
          <div data-social-host class="mt-1 text-xs text-slate-500 dark:text-zink-400"><?= e($host) ?></div>
        </div>
        <p class="border-t border-slate-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-800 dark:border-zink-600 dark:bg-amber-500/10 dark:text-amber-300">
          LinkedIn does not show the description. Put what matters in the title.
        </p>
      </div>

      <!-- WhatsApp: small square thumbnail beside the text, in a chat bubble -->
      <div data-social-panel="whatsapp" hidden>
        <div class="rounded-lg bg-[#dcf8c6] p-1.5 dark:bg-emerald-900/30">
          <div class="overflow-hidden rounded-md bg-white/70 dark:bg-zink-800/70">
            <div class="flex gap-2 p-2">
              <div data-social-image class="flex size-[60px] shrink-0 items-center justify-center rounded bg-slate-100 bg-cover bg-center dark:bg-zink-800">
                <i data-lucide="image" class="size-5 text-slate-300 dark:text-zink-600" aria-hidden="true"></i>
              </div>
              <div class="min-w-0">
                <div data-social-title class="line-clamp-2 text-xs font-medium leading-snug text-slate-900 dark:text-zink-100"></div>
                <div data-social-desc class="mt-0.5 line-clamp-2 text-[11px] text-slate-500 dark:text-zink-400"></div>
                <div data-social-host class="mt-0.5 text-[10px] text-slate-400 dark:text-zink-500"><?= e($host) ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Discord: accent bar on the left, description shown in full -->
      <div data-social-panel="discord" hidden>
        <div class="rounded-md bg-[#2b2d31] p-3">
          <div class="rounded border-slate-600 bg-[#1e1f22] p-3 ltr:border-l-4 rtl:border-r-4" style="border-color:#5865f2">
            <div data-social-host class="text-[11px] text-slate-400"><?= e($host) ?></div>
            <div data-social-title class="mt-0.5 text-sm font-semibold text-[#00a8fc]"></div>
            <div data-social-desc class="mt-1 text-xs leading-relaxed text-slate-300"></div>
            <div data-social-image class="mt-2 flex aspect-[1.91/1] w-full items-center justify-center rounded bg-slate-700 bg-cover bg-center">
              <i data-lucide="image" class="size-8 text-slate-500" aria-hidden="true"></i>
            </div>
          </div>
        </div>
      </div>

      <p class="mt-2 text-[11px] text-slate-400 dark:text-zink-400">
        Platforms cache what they scrape. After changing these, published posts may keep showing the old card until the platform refetches.
      </p>

    </div>

  </div>
</section>
