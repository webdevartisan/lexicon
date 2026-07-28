<?php
// Minimal chrome for the sign-in / sign-up / password screens. These pages
// have one job, so they get no masthead nav, no footer site map and no
// consent-banner competition: a slim bar, the mark, and the form.
//
// The layout owns the flash and error rendering so the four auth views stop
// repeating it, and yields: heading, sub, altAction, body, help, legal.

// lurl('/') returns "/en/", which the router 302s to "/en". The mark and the
// back link are on every one of these pages, so the redirect is worth avoiding.
$homeHref = rtrim(lurl('/'), '/');

$authFlash = flash();
$authError = $error ?? null;

$siteName = site_setting('site_name', 'Lexicon');
?>
<!DOCTYPE HTML>
<html lang="{{ chromeLang }}" dir="{{ chromeDir }}">

    <head>
        <title>{% yield title %}</title>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="theme-color" content="#f0f1ec" />
        <?php // Credential screens have no business in an index. ?>
        <meta name="robots" content="noindex, nofollow" />

        <link rel="icon" type="image/png" href="/assets/icon/favicon-32x32.png" sizes="32x32" />
        <link rel="icon" type="image/svg+xml" href="/assets/icon/favicon.svg" />
        <link rel="shortcut icon" href="/assets/icon/favicon.ico" />
        <link rel="apple-touch-icon" sizes="180x180" href="/assets/icon/apple-touch-icon.png" />

        <link rel="preload" href="/assets/fonts/newsreader-latin.woff2" as="font" type="font/woff2" crossorigin />
        <link rel="preload" href="/assets/fonts/instrumentsans-latin.woff2" as="font" type="font/woff2" crossorigin />

        <link rel="stylesheet" href="/assets/css/lexicon.css" />

        {% yield meta %}
    </head>

    <body class="lx-authpage">

        <a class="lx-skip" href="#lx-auth-main"><?= e($t('a11y.skipToContent')) ?></a>

        <header class="lx-authbar">
            <a class="lx-authbar__back" href="<?= e($homeHref) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
                <?= e($t('auth.back')) ?>
            </a>
            <div class="lx-authbar__alt">{% yield altAction %}</div>
        </header>

        <main class="lx-authmain" id="lx-auth-main">
            <div class="lx-authcol">

                <a class="lx-authmark" href="<?= e($homeHref) ?>" aria-label="<?= e($siteName) ?>">
                    <span class="lx-authmark__dot" aria-hidden="true"></span>
                    <span class="lx-authmark__word"><?= e($siteName) ?></span>
                </a>

                <h1 class="lx-authtitle">{% yield heading %}</h1>
                <p class="lx-authsub">{% yield sub %}</p>

                <?php
                // Flash first, then the controller's explicit $error: a redirect
                // carries the flash, a re-render carries the error, and only one
                // of the two is ever set on a given request.
                foreach ($authFlash as $type => $messages) {
                    foreach ($messages as $message) { ?>
                        <p class="lx-authalert<?= $type === 'success' ? ' is-ok' : '' ?>" role="alert"><?= e($message) ?></p>
                    <?php }
                }

                if (!empty($authError)) { ?>
                    <p class="lx-authalert" role="alert"><?= e($authError) ?></p>
                <?php } ?>

                {% yield body %}

                <div class="lx-authhelp">{% yield help %}</div>
            </div>
        </main>

        <footer class="lx-authlegal">
            {% yield legal %}
        </footer>

        <?php
        // Kept even though these screens load no analytics: someone can land
        // here first, and dropping the banner from a whole set of pages would
        // be a compliance change, not a design one. It stays hidden once a
        // choice is stored, so on most visits it costs nothing visually.
        ?>
        {% include "partials/_consent_bootstrap.lex.php" %}
        {% include "partials/_consent_banner.lex.php" %}

        <script src="/assets/js/front.js" defer></script>
        {% yield scripts %}
    </body>
</html>
