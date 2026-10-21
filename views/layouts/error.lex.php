<?php
// The sign-in screens' quiet chrome around one card painted like the menus and
// the confirm dialog. Each error view supplies status, title, tone, heading,
// message and an optional extra action.
//
// Nothing here reads the database or the locale globals: a 404 for an unknown
// route is thrown before the locale middleware runs, and a 500 may be the
// database failing. The copy is English, so the page says so.

$homeHref = rtrim(lurl('/'), '/');

$destinations = [
    ['path' => '/discover', 'title' => 'Discover', 'text' => 'Browse blogs and the latest posts.'],
    ['path' => '/getting-started', 'title' => 'Getting started', 'text' => 'How to start a blog and publish your first post.'],
    ['path' => '/contact', 'title' => 'Contact', 'text' => 'Write to the people who run Lexicon.'],
];

$debugRows = array_filter([
    'Type' => $exceptionClass ?? null,
    'Message' => $templateMessage ?? $exceptionMessage ?? null,
    'File' => isset($templateFile) ? $templateFile.':'.($templateLine ?? '') : (isset($exceptionFile) ? $exceptionFile.':'.($exceptionLine ?? '') : null),
]);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#f0f1ec">
    {% include "partials/_favicons.lex.php" %}
    <title>{% yield title %} | Lexicon</title>

    <link rel="preload" href="/assets/fonts/newsreader-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/instrumentsans-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(versioned_asset('/assets/css/lexicon.css')) ?>">
    <link rel="stylesheet" href="<?= e(versioned_asset('/assets/css/platform-chrome.css')) ?>">
    <script src="/assets/js/appearance.js"></script>
  </head>

  <body class="lx-authpage">
    {% include "partials/_impersonation_banner.lex.php" %}

    <header class="lx-authbar">
      <a class="lx-authmark" href="<?= e($homeHref) ?>" aria-label="Lexicon home">
        <span class="lx-authmark-dot" aria-hidden="true"></span>
        <span class="lx-authmark-word">Lexicon</span>
      </a>
    </header>

    <main class="lx-authmain lx-errormain">
      <section class="lx-error" data-tone="{% yield tone %}" aria-labelledby="lx-error-title">
        <div>
          <p class="lx-error-badge">Error {% yield status %}</p>
          <h1 class="lx-error-title" id="lx-error-title">{% yield heading %}</h1>
          <p class="lx-error-text">{% yield message %}</p>
          <div class="lx-error-actions">
            <a class="lx-error-btn is-primary" href="<?= e($homeHref) ?>">Back to home</a>
            {% yield action %}
          </div>
        </div>

        <nav aria-labelledby="lx-error-more">
          <p class="lx-error-more" id="lx-error-more">Or pick up from here</p>
          <ul class="lx-error-links">
            <?php foreach ($destinations as $destination) { ?>
              <li>
                <a class="lx-error-link" href="<?= e(lurl($destination['path'])) ?>">
                  <span>
                    <span class="lx-error-link-title"><?= e($destination['title']) ?></span>
                    <span class="lx-error-link-text"><?= e($destination['text']) ?></span>
                  </span>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M7 7h10v10"/></svg>
                </a>
              </li>
            <?php } ?>
          </ul>
        </nav>

        <?php if (!empty($isDev) && $debugRows !== []) { ?>
          <div class="lx-error-debug">
            <dl>
              <?php foreach ($debugRows as $label => $value) { ?>
                <dt><?= e($label) ?></dt>
                <dd><?= e($value) ?></dd>
              <?php } ?>
            </dl>
            <?php if (!empty($templateSnippet)) { ?>
              <?php // TemplateRenderException::snippet() escapes each line itself.?>
              <pre><?= $templateSnippet ?></pre>
            <?php } elseif (!empty($exceptionTrace)) { ?>
              <pre><?= e($exceptionTrace) ?></pre>
            <?php } ?>
          </div>
        <?php } ?>
      </section>
    </main>

    {% yield scripts %}
  </body>
</html>
