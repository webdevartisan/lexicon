{% extends "back.lex.php" %}

{% block title %}{{ t('blog.themes.pageTitle') }}{% endblock %}
{% block subtitle %}{{ t('blog.themes.pageSubtitle') }}{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/dropzone.css">
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
<style>
  .theme-shot {
    aspect-ratio: 4 / 3;
    overflow: hidden;
    background: #e2e8f0;
  }
  .theme-shot img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.5s cubic-bezier(0.19, 1, 0.22, 1);
  }
  .theme-card {
    transition: box-shadow 0.25s ease, transform 0.25s ease;
  }
  .theme-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px -12px rgb(15 23 42 / 0.25);
  }
  .theme-card:hover .theme-shot img {
    transform: scale(1.03);
  }
  .theme-card.is-active {
    box-shadow: 0 0 0 2px rgb(var(--custom-500, 59 130 246));
  }
  @media (min-width: 768px) {
    .theme-hero-grid {
      display: grid;
      grid-template-columns: minmax(260px, 340px) 1fr;
    }
  }
  @media (prefers-reduced-motion: reduce) {
    .theme-card,
    .theme-shot img {
      transition: none;
    }
    .theme-card:hover {
      transform: none;
    }
    .theme-card:hover .theme-shot img {
      transform: none;
    }
  }
</style>
{% endblock %}

{% block body %}
<?php
$blogId = (string) ($blog['id'] ?? '');
$previewBase = base_url().'/blog/'.($blog['blog_slug'] ?? '');
$btnPrimary = 'bg-white text-custom-500 btn border-custom-500 hover:text-white hover:bg-custom-600 hover:border-custom-600 focus:text-white focus:bg-custom-600 focus:border-custom-600 focus:ring focus:ring-custom-100 active:text-white active:bg-custom-600 active:border-custom-600 dark:bg-zink-700 dark:hover:bg-custom-500 dark:ring-custom-400/20 dark:focus:bg-custom-500';
$btnGhost = 'inline-flex items-center justify-center gap-2 rounded-md font-medium focus:ring bg-white text-slate-500 border-slate-500 btn hover:text-white hover:bg-slate-600 hover:border-slate-600 dark:bg-zink-700 dark:hover:bg-slate-500';
?>

{% set bannerLabel = t('blog.form.fields.banner.label') %}
{% set logoLabel = t('blog.form.fields.logo.label') %}
{% set faviconLabel = t('blog.form.fields.favicon.label') %}
{% set taglineLabel = t('blog.form.fields.tagline.label') %}
{% set taglinePlaceholder = t('blog.form.fields.tagline.placeholder') %}
{% set taglineUnderlabel = t('blog.form.fields.tagline.underlabel') %}
{% set subtitleLabel = t('blog.form.fields.subtitle.label') %}
{% set subtitlePlaceholder = t('blog.form.fields.subtitle.placeholder') %}
{% set subtitleUnderlabel = t('blog.form.fields.subtitle.underlabel') %}
{% set aboutTextLabel = t('blog.form.fields.aboutText.label') %}
{% set aboutTextPlaceholder = t('blog.form.fields.aboutText.placeholder') %}
{% set foundedYearLabel = t('blog.form.fields.foundedYear.label') %}
{% set foundedYearPlaceholder = t('blog.form.fields.foundedYear.placeholder') %}
{% set foundedYearUnderlabel = t('blog.form.fields.foundedYear.underlabel') %}
{% set newsHeadingLabel = t('blog.form.fields.newsletterHeading.label') %}
{% set newsHeadingPlaceholder = t('blog.form.fields.newsletterHeading.placeholder') %}
{% set newsHeadingUnderlabel = t('blog.form.fields.newsletterHeading.underlabel') %}
{% set newsTextLabel = t('blog.form.fields.newsletterText.label') %}
{% set newsTextPlaceholder = t('blog.form.fields.newsletterText.placeholder') %}
{% set newsTextUnderlabel = t('blog.form.fields.newsletterText.underlabel') %}
{% set updateBtnLabel = t('blog.form.actions.update') %}

<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">

  {% if errors|notempty %}
  <div class="mb-4">
    <div class="flex items-start gap-3 p-3 text-sm rounded-md bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/30 dark:text-red-100 dark:border-red-700">
      <div class="mt-0.5"><i class="fas fa-exclamation-circle text-sm"></i></div>
      <div>
        <p class="font-semibold">{{ t('blog.form.errors.title') }}</p>
        <ul class="mt-2 space-y-1 list-disc list-inside">
          {% foreach ($errors as $field => $msgs): %}
            {% foreach ($msgs as $msg): %}
              <li>{{ msg }}</li>
            {% endforeach %}
          {% endforeach %}
        </ul>
      </div>
    </div>
  </div>
  {% endif %}

  <!-- Section tabs -->
  <div class="flex flex-wrap gap-1 mb-6 border-b border-slate-200 dark:border-zink-600" role="tablist" aria-label="{{ t('blog.themes.pageTitle') }}">
    <button type="button" data-appearance-tab="theme" role="tab" class="px-4 py-2.5 -mb-px text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-custom-500 dark:text-zink-300">
      {{ t('blog.form.sections.theme.title') }}
    </button>
    <button type="button" data-appearance-tab="branding" role="tab" class="px-4 py-2.5 -mb-px text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-custom-500 dark:text-zink-300">
      {{ t('blog.themes.customize') }}
    </button>
  </div>

  <!-- ============ Theme ============ -->
  <div data-appearance-panel="theme">

    {% if currentTheme|notempty %}
    <section class="mb-8 bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden dark:bg-zink-700 dark:border-zink-600">
      <div class="theme-hero-grid">
        <figure class="theme-shot border-b border-slate-200 md:border-b-0 md:border-r dark:border-zink-600">
          {% if currentTheme.screenshot|notempty %}
          <img src="<?= e($currentTheme['screenshot']) ?>" alt="{{ currentTheme.name }}">
          {% endif %}
        </figure>
        <div class="p-5 md:p-6 flex flex-col">
          <p class="mb-2 text-[11px] font-semibold tracking-wide uppercase text-custom-500">
            {{ t('blog.themes.currentTheme') }}
          </p>
          <h2 class="text-xl font-semibold text-slate-900 dark:text-zink-100">{{ currentTheme.name }}</h2>
          <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">
            {{ t('blog.themes.versionPrefix') }} {{ currentTheme.version }} &middot; {{ t('blog.themes.byAuthor') }} {{ currentTheme.author }}
          </p>
          <p class="mt-3 text-sm text-slate-600 dark:text-zink-200 max-w-xl">{{ currentTheme.description }}</p>
          <div class="flex flex-wrap gap-2 mt-5 pt-4 border-t border-dashed border-slate-200 dark:border-zink-600">
            <a href="<?= e($previewBase) ?>" target="_blank" rel="noopener" class="<?= $btnPrimary ?>">
              <i data-lucide="external-link" class="inline-block size-4" aria-hidden="true"></i>
              {{ t('blog.themes.viewBlog') }}
            </a>
            <a href="#branding" class="<?= $btnGhost ?>">
              <i data-lucide="brush" class="inline-block size-4" aria-hidden="true"></i>
              {{ t('blog.themes.customize') }}
            </a>
          </div>
        </div>
      </div>
    </section>
    {% endif %}

    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
      <div class="flex items-center gap-2">
        <h2 class="text-base font-semibold text-slate-900 dark:text-zink-100">{{ t('blog.themes.allThemes') }}</h2>
        <span class="px-2 py-0.5 text-[11px] font-medium rounded-full bg-slate-100 text-slate-500 dark:bg-zink-600 dark:text-zink-200"><?= count($themes) ?></span>
      </div>
    </div>
    <p class="mb-5 text-xs text-slate-500 dark:text-zink-300 max-w-2xl">{{ t('blog.themes.previewHint') }}</p>

    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 mb-8">
      {% foreach ($themes as $themeKey => $meta): %}
      <?php
      $isActive = $themeKey === $currentKey;
$previewUrl = $previewBase.'?_theme='.rawurlencode($themeKey);
?>
      <article class="theme-card <?= $isActive ? 'is-active' : '' ?> flex flex-col bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden dark:bg-zink-700 dark:border-zink-600">
        <figure class="theme-shot relative border-b border-slate-200 dark:border-zink-600">
          {% if meta.screenshot|notempty %}
          <img src="<?= e($meta['screenshot']) ?>" alt="{{ meta.name }}" loading="lazy">
          {% else %}
          <div class="flex items-center justify-center h-full text-slate-400 dark:text-zink-400">
            <span class="text-2xl font-semibold"><?= e(mb_substr($meta['name'], 0, 1)) ?></span>
          </div>
          {% endif %}
          {% if isActive %}
          <span class="absolute top-3 right-3 inline-flex items-center gap-1 px-2 py-1 text-[11px] font-semibold rounded-full text-white bg-custom-500 shadow-sm">
            <i data-lucide="check" class="inline-block size-3" aria-hidden="true"></i>
            {{ t('blog.themes.activeBadge') }}
          </span>
          {% endif %}
        </figure>

        <div class="flex flex-col grow p-4">
          <div class="flex items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-zink-100">{{ meta.name }}</h3>
            <span class="shrink-0 text-[11px] text-slate-400 dark:text-zink-400">v{{ meta.version }}</span>
          </div>
          <p class="mt-0.5 text-[11px] text-slate-400 dark:text-zink-400">{{ t('blog.themes.byAuthor') }} {{ meta.author }}</p>
          <p class="mt-2 text-xs leading-relaxed text-slate-600 dark:text-zink-200 line-clamp-3" title="<?= e($meta['description']) ?>">{{ meta.description }}</p>

          <div class="flex items-center gap-2 mt-auto pt-4">
            {% if isActive %}
            <a href="<?= e($previewBase) ?>" target="_blank" rel="noopener" class="<?= $btnGhost ?> w-full">
              <i data-lucide="external-link" class="inline-block size-4" aria-hidden="true"></i>
              {{ t('blog.themes.viewBlog') }}
            </a>
            {% else %}
            <form method="post" action="/dashboard/blog/<?= e($blogId) ?>/appearance/activate" class="grow">
              {{ csrf_field() }}
              <input type="hidden" name="theme" value="<?= e($themeKey) ?>">
              <button type="submit" class="<?= $btnPrimary ?> w-full">
                {{ t('blog.themes.activate') }}
              </button>
            </form>
            <a href="<?= e($previewUrl) ?>" target="_blank" rel="noopener" class="<?= $btnGhost ?>" title="{{ t('blog.themes.preview') }}">
              <i data-lucide="eye" class="inline-block size-4" aria-hidden="true"></i>
              <span class="sr-only">{{ t('blog.themes.preview') }}</span>
            </a>
            {% endif %}
          </div>
        </div>
      </article>
      {% endforeach %}
    </div>
  </div>

  <!-- ============ Branding & texts ============ -->
  <div data-appearance-panel="branding" class="hidden">
    <form
      method="post"
      action="/dashboard/blog/<?= e($blogId) ?>/appearance/update"
      enctype="multipart/form-data"
      data-dropzone-form>
      {{ csrf_field() }}

      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600 mb-6">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">{{ t('blog.form.sections.branding.title') }}</h2>
          <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">{{ t('blog.form.sections.branding.description') }}</p>
        </div>
        <div class="p-4 md:p-5">
          <div class="grid gap-4 md:grid-cols-3">
            <?php $blogIdForLibrary = (string) ($blog['id'] ?? ''); ?>
            {% cmp="dropzone2" label="{$bannerLabel}" name="banner" resource="{$settings}" library="{$blogIdForLibrary}" %}
            {% cmp="dropzone2" label="{$logoLabel}" name="logo" resource="{$settings}" library="{$blogIdForLibrary}" %}
            {% cmp="dropzone2" label="{$faviconLabel}" name="favicon" resource="{$settings}" library="{$blogIdForLibrary}" %}
          </div>
        </div>
      </section>

      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600 mb-6">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">{{ t('blog.form.sections.frontPage.title') }}</h2>
          <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">{{ t('blog.form.sections.frontPage.description') }}</p>
        </div>
        <div class="p-4 space-y-4 md:p-5">
          <?php $value = $settings['tagline'] ?? ''; ?>
          {% cmp="input" type="text" name="tagline" label="{$taglineLabel}" value="{$value}" placeholder="{$taglinePlaceholder}" underlabel="{$taglineUnderlabel}" %}

          <?php $value = $settings['subtitle'] ?? ''; ?>
          {% cmp="input" type="text" name="subtitle" label="{$subtitleLabel}" value="{$value}" placeholder="{$subtitlePlaceholder}" underlabel="{$subtitleUnderlabel}" %}

          <?php $value = $settings['about_text'] ?? ''; ?>
          {% cmp="input" type="textarea" name="about_text" label="{$aboutTextLabel}" value="{$value}" rows="3" placeholder="{$aboutTextPlaceholder}" %}

          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <?php $value = $settings['founded_year'] ?? ''; ?>
              {% cmp="input" type="text" name="founded_year" label="{$foundedYearLabel}" value="{$value}" placeholder="{$foundedYearPlaceholder}" underlabel="{$foundedYearUnderlabel}" %}
            </div>
          </div>

          <div class="pt-3 border-t border-dashed border-slate-200 dark:border-zink-600 space-y-4">
            <?php $value = $settings['newsletter_heading'] ?? ''; ?>
            {% cmp="input" type="text" name="newsletter_heading" label="{$newsHeadingLabel}" value="{$value}" placeholder="{$newsHeadingPlaceholder}" underlabel="{$newsHeadingUnderlabel}" %}

            <?php $value = $settings['newsletter_text'] ?? ''; ?>
            {% cmp="input" type="text" name="newsletter_text" label="{$newsTextLabel}" value="{$value}" placeholder="{$newsTextPlaceholder}" underlabel="{$newsTextUnderlabel}" %}
          </div>
        </div>
      </section>

      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600 mb-6">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">{{ t('blog.form.sections.social.title') }}</h2>
          <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">{{ t('blog.form.sections.social.description') }}</p>
        </div>
        <div class="p-4 md:p-5">
          <div class="grid gap-4 md:grid-cols-2">
            {% foreach ($socialPlatforms as $platform): %}
              <?php
          $socialName = 'social_'.$platform;
$socialValue = $socialLinks[$platform] ?? '';
$socialLabel = $t('blog.form.fields.social.'.$platform);
?>
              {% cmp="input" type="url" name="{$socialName}" label="{$socialLabel}" value="{$socialValue}" placeholder="https://" %}
            {% endforeach %}
          </div>
        </div>
      </section>

      <div class="flex justify-end mb-8">
        {% cmp="btn" type="submit" variant="blue" icon="save" label="{$updateBtnLabel}" %}
      </div>
    </form>
  </div>
</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/libs/dropzone/dropzone-min.js"></script>
<script src="/cp-assets/js/dropzone.init.js"></script>
<script src="/cp-assets/js/media-picker.js"></script>
<script src="/cp-assets/js/modal.js"></script>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    var sections = ['theme', 'branding'];
    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-appearance-tab]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-appearance-panel]'));
    var activeClasses = ['border-custom-500', 'text-custom-500'];
    var hasErrors = <?= !empty($errors) ? 'true' : 'false' ?>;

    function activate(name, updateHash) {
      if (sections.indexOf(name) === -1) name = hasErrors ? 'branding' : 'theme';

      tabs.forEach(function (tab) {
        var isActive = tab.getAttribute('data-appearance-tab') === name;
        activeClasses.forEach(function (cls) { tab.classList.toggle(cls, isActive); });
        tab.classList.toggle('border-transparent', !isActive);
        tab.classList.toggle('text-slate-500', !isActive);
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });

      panels.forEach(function (panel) {
        panel.classList.toggle('hidden', panel.getAttribute('data-appearance-panel') !== name);
      });

      if (updateHash) history.replaceState(null, '', '#' + name);
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        activate(tab.getAttribute('data-appearance-tab'), true);
      });
    });

    // The hero's customize link is a plain #branding anchor; follow it here.
    window.addEventListener('hashchange', function () {
      activate((location.hash || '').replace('#', ''), false);
    });

    activate((location.hash || '').replace('#', ''), false);
  });
</script>
{% endblock %}
