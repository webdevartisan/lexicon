<?php
$flash = flash();
$errors = errors();
$old = old();
?>
<!DOCTYPE HTML>
<?php
// Declared from the chrome locale, not the content locale: every visible string
// in this layout comes from $t(), which follows the reader's own preference. A
// guest has them equal, so this only differs for someone signed in who chose a
// language, and it is what stops Arabic text rendering inside lang="en" ltr.
// The canonical and hreflang below stay content-based, because those describe
// the URL rather than the text.
?>
<html lang="{{ chromeLang }}" dir="{{ chromeDir }}">

	<head>
		<title>{% yield title %}</title>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />

        <link rel="icon" type="image/png" href="/assets/icon/favicon-32x32.png" sizes="32x32" />
        <link rel="icon" type="image/png" href="/assets/icon/favicon-16x16.png" sizes="16x16" />
        <link rel="icon" type="image/svg+xml" href="/assets/icon/favicon.svg" />
        <link rel="shortcut icon" href="/assets/icon/favicon.ico" />
        <link rel="apple-touch-icon" sizes="180x180" href="/assets/icon/apple-touch-icon.png" />
        <link rel="manifest" href="/assets/icon/site.webmanifest" />

		<!-- Canonical and alternates -->
		<link rel="canonical" href="{{ head.canonicalUrl }}" />

        {% foreach ($head['alternates'] as $alt): %}
        <link rel="alternate" href="{{ alt.href }}" hreflang="{{ alt.hreflang }}" />
        {% endforeach; %}

        {% if (!empty($head['alternates'])): %}
        <link rel="alternate" href="{{ head.xDefaultUrl }}" hreflang="x-default" />
        {% endif; %}

        <!-- Open Graph locale -->
        <meta property="og:locale" content="{{ head.ogLocale }}" />

        {% foreach ($head['ogLocaleAlternates'] as $ogl): %}
        <meta property="og:locale:alternate" content="{{ ogl }}" />
        {% endforeach; %}

        <link rel="stylesheet" href="/assets/css/fontawesome-all.min.css" />
        <link rel="stylesheet" href="/assets/css/main.css" />
        <link rel="stylesheet" href="/assets/css/front.css" />

        {% yield meta %}

	</head>

	<body class="is-preload">

		<!-- Wrapper -->
        <div id="wrapper">

            <!-- Main -->
            <div id="main">
                <div class="inner">

                    <!-- Header -->
                    <header id="header">                        
                        <a href="/" class="logo">
                            <svg xmlns="http://www.w3.org/2000/svg" width="200" viewBox="0 0 720 180" role="img" aria-label="LEXICON">
                            <rect width="100%" height="100%" fill="white"/>
                            <text x="0" y="175"
                                    font-family="-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif"
                                    font-size="150"
                                    font-weight="800"
                                    letter-spacing="3"
                                    fill="#111827">LEXICON</text>
                            </svg><br>
                            {{ t('header.logoTagline') }}
                        </a>

                        <ul class="icons" data-auth-nav="platform">
                            <?php $authNavVariant = 'platform'; ?>
                            {% include "partials/_auth_nav.lex.php" %}
                        </ul>
                    </header>
                    {% yield body %}

                    <!-- Footer -->
                    <footer id="footer" role="contentinfo">
                        <div class="row gtr-50 items">
                            <!-- Brand & mission -->
                            <section class="col-4 col-12-small footer-section footer-brand">
                                <header class="major">
                                    <h2 class="footer-heading">{{ t('footer.aboutTitle') }}</h2>
                                </header>

                                <p class="footer-text">
                                    <?= e(site_content('footer.aboutText')) ?>
                                </p>
                            </section>

                            <!-- Quick navigation -->
                            <nav class="col-4 col-6-small footer-section footer-links"
                                aria-label="{{ t('footer.quickLinksTitle') }}">
                                <header class="major">
                                    <h2 class="footer-heading">{{ t('footer.quickLinksTitle') }}</h2>
                                </header>
                                <ul class="footer-list">
                                    <li><a href="/">{{ t('footer.linkHome') }}</a></li>
                                    <li><a href="/blogs">{{ t('footer.linkExplore') }}</a></li>
                                    <li><a href="/getting-started">{{ t('footer.linkGettingStarted') }}</a></li>
                                    <li><a href="/about">{{ t('footer.linkAbout') }}</a></li>
                                    <li><a href="/contact">{{ t('footer.linkContact') }}</a></li>
                                </ul>
                            </nav>

                            <!-- Legal & security -->
                            <nav class="col-4 col-6-small footer-section footer-legal"
                                aria-label="{{ t('footer.legalLinksAria') }}">
                                <header class="major">
                                    <h2 class="footer-heading">{{ t('footer.legalTitle') }}</h2>
                                </header>
                                <ul class="footer-list">
                                    <li><a href="/privacy">{{ t('footer.linkPrivacy') }}</a></li>
                                    <li><a href="/terms">{{ t('footer.linkTerms') }}</a></li>
                                    <li><a href="/cookies">{{ t('footer.linkCookies') }}</a></li>
                                </ul>
                            </nav>
                        </div>

                        <!-- Social & bottom line -->
                        <div class="footer-bottom">

                            {% include "partials/_language_switcher.lex.php" %}

                            <p class="footer-copy copyright">
                                &copy; <?= date('Y') ?> <?= e(site_setting('site_name', 'Lexicon')) ?>.
                                All rights reserved.
                            </p>

                            <?php
                            // Icons appear only for networks the admin configured,
                            // so a fresh install never ships dead placeholder links.
                            $socialNetworks = [
                                ['key' => 'social.twitter', 'icon' => 'fa-x-twitter', 'label' => 'Twitter', 'ariaKey' => 'footer.socialTwitterAria'],
                                ['key' => 'social.facebook', 'icon' => 'fa-facebook-f', 'label' => 'Facebook', 'ariaKey' => 'footer.socialFacebookAria'],
                                ['key' => 'social.instagram', 'icon' => 'fa-instagram', 'label' => 'Instagram', 'ariaKey' => 'footer.socialInstagramAria'],
                                ['key' => 'social.medium', 'icon' => 'fa-medium-m', 'label' => 'Medium', 'ariaKey' => 'footer.socialMediumAria'],
                            ];
$configuredSocials = [];
foreach ($socialNetworks as $network) {
    $url = site_content($network['key'], '');
    if ($url !== '' && preg_match('#^https://#i', $url)) {
        $network['url'] = $url;
        $configuredSocials[] = $network;
    }
}
?>
                            {% if (!empty($configuredSocials)): %}
                            <div class="footer-social" aria-label="{{ t('footer.socialAria') }}">
                                <ul class="icons">
                                    {% foreach ($configuredSocials as $social): %}
                                    <li>
                                        <a href="{{ social.url }}"
                                        class="icon brands fa <?= e($social['icon']) ?>"
                                        rel="noopener noreferrer"
                                        target="_blank"
                                        aria-label="<?= e($t($social['ariaKey'])) ?>">
                                            <span class="label">{{ social.label }}</span>
                                        </a>
                                    </li>
                                    {% endforeach; %}
                                </ul>
                            </div>
                            {% endif %}
                        </div>

                    </footer>

                </div>
			</div>
            <!-- Sidebar -->
            {% include "partials/_sidebar_front_page.lex.php" %}
		</div>

        {% include "partials/_consent_bootstrap.lex.php" %}
        {% include "partials/_consent_banner.lex.php" %}
        <button type="button" class="fab scroll-top-btn" title="Back to top" aria-label="Scroll to top">
        </button>
		<!-- Scripts -->
		<script src="/assets/js/jquery.min.js"></script>
		<script src="/assets/js/browser.min.js"></script>
		<script src="/assets/js/breakpoints.min.js"></script>
		<script src="/assets/js/util.js"></script>
		<script src="/assets/js/main.js"></script>
		<script src="/assets/js/lang-switcher.js" defer></script>
		<script src="/assets/js/scrolltop.js"></script>
		{% include "partials/_auth_modal.lex.php" %}
		{% yield scripts %}
	</body>
</html>