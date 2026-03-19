<?php
$flash = flash();
$errors = errors();
$old = old();
?>
<!DOCTYPE HTML>
<html lang="{{ currentLang }}" {{ isRtl|raw }}>

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

        <link rel="alternate" href="{{ head.xDefaultUrl }}" hreflang="x-default" />

        <!-- Open Graph locale -->
        <meta property="og:locale" content="{{ head.ogLocale }}" />

        {% foreach ($head['ogLocaleAlternates'] as $ogl): %}
        <meta property="og:locale:alternate" content="{{ ogl }}" />
        {% endforeach; %}

        <link rel="stylesheet" href="/assets/css/main.css" />

        <script>
            window.AppLocales = {
                supported: <?= json_encode($supportedLocales) ?>,
                default: "<?= $defaultLocale ?>"                    
            };
        </script>

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

                        <ul class="icons">
                            <!-- <li><a href="#" class="icon brands fa-twitter"><span class="label">Twitter</span></a></li>
                            <li><a href="#" class="icon brands fa-facebook-f"><span class="label">Facebook</span></a></li>
                            <li><a href="#" class="icon brands fa-snapchat-ghost"><span class="label">Snapchat</span></a></li>
                            <li><a href="#" class="icon brands fa-instagram"><span class="label">Instagram</span></a></li>
                            <li><a href="#" class="icon brands fa-medium-m"><span class="label">Medium</span></a></li> -->

                            {% if (!auth()->check()): %}
                                <li><a href="/login" class="button">{{ t('header.signIn') }}</a></li>
                            {% else %}
                                <li><a href="/dashboard" class="button">{{ t('header.dashboard') }}</a></li>
                                <li><a href="/logout" class="button">{{ t('header.signOut') }}</a></li>
                            {% endif %}
                        </ul>
                    </header>
                    {% yield body %}

                    <!-- Footer -->
                    <footer id="footer" role="contentinfo">
                        <!-- Dev: We use the existing .row /.col-* grid so we don’t invent a parallel grid just for the footer. -->
                        <div class="row gtr-50 items">
                            <!-- Brand & mission -->
                            <section class="col-4 col-12-small footer-section footer-brand">
                                <header class="major">
                                    <h2 class="footer-heading">{{ t('footer.aboutTitle') }}</h2>
                                </header>

                                <p class="footer-text">
                                    {{ t('footer.aboutText') }}
                                </p>
                            </section>

                            <!-- Quick navigation -->
                            <nav class="col-4 col-6-small footer-section footer-links"
                                aria-label="{{ t('footer.quickLinksTitle') }}">
                                <header class="major">
                                    <h2 class="footer-heading">{{ t('footer.quickLinksTitle') }}</h2>
                                </header>
                                <ul class="footer-list">
                                    <li>
                                        <a href="{# url('home') #}">
                                            {{ t('footer.linkHome') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{# url('blog.index') #}">
                                            {{ t('footer.linkBlog') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{# url('about') #}">
                                            {{ t('footer.linkAbout') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{# url('contact') #}">
                                            {{ t('footer.linkContact') }}
                                        </a>
                                    </li>
                                </ul>
                            </nav>

                            <!-- Legal & security -->
                            <nav class="col-4 col-6-small footer-section footer-legal"
                                aria-label="{{ t('footer.legalLinksAria') }}">
                                <header class="major">
                                    <h2 class="footer-heading">{{ t('footer.legalTitle') }}</h2>
                                </header>
                                <ul class="footer-list">
                                    <li>
                                        <a href="{# url('legal.privacy') #}">
                                            {{ t('footer.linkPrivacy') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{# url('legal.terms') #}">
                                            {{ t('footer.linkTerms') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{# url('legal.cookies') #}">
                                            {{ t('footer.linkCookies') }}
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>

                        <!-- Social & bottom line -->
                        <div class="footer-bottom">

                            <div class="lang-switcher"> <!-- Language Switcher -->
                                <button class="button small alt" id="langToggle" aria-haspopup="listbox" aria-expanded="false">
                                    <span class="icon solid fa fa-globe" aria-hidden="true"></span>
                                    <span id="currentLang">EN</span>
                                    <span class="caret" aria-hidden="true">▾</span>
                                </button>
                                <ul class="lang-menu" id="langMenu" role="listbox" aria-label="Select language">
                                    <li role="option" data-lang="en" aria-selected="false">English</li>
                                    <li role="option" data-lang="el" aria-selected="false">Ελληνικά</li>
                                    <li role="option" data-lang="ar" aria-selected="false">Arabic</li>
                                </ul>
                            </div>

                            <p class="footer-copy copyright">
                                &copy; <?= date('Y') ?> Lexicon.
                                {{ t('footer.rightsReserved') }}
                            </p>

                            <div class="footer-social" aria-label="{{ t('footer.socialAria') }}">
                                <ul class="icons">
                                    <li>
                                        <a href="https://twitter.com/yourprofile"
                                        class="icon brands fa fa-x-twitter"
                                        rel="noopener noreferrer"
                                        target="_blank"
                                        aria-label="{{ t('footer.socialTwitterAria') }}">
                                            <span class="label">Twitter</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="https://facebook.com/yourpage"
                                        class="icon brands fa fa-facebook-f"
                                        rel="noopener noreferrer"
                                        target="_blank"
                                        aria-label="{{ t('footer.socialFacebookAria') }}">
                                            <span class="label">Facebook</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#"
                                        class="icon brands fa fa-snapchat-ghost"
                                        rel="noopener noreferrer"
                                        target="_blank"
                                        aria-label="{{ t('footer.socialSnapchatAria') }}">
                                            <span class="label">Snapchat</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="https://instagram.com/yourprofile"
                                        class="icon brands fa fa-instagram"
                                        rel="noopener noreferrer"
                                        target="_blank"
                                        aria-label="{{ t('footer.socialInstagramAria') }}">
                                            <span class="label">Instagram</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#"
                                        class="icon brands fa fa-medium-m"
                                        rel="noopener noreferrer"
                                        target="_blank"
                                        aria-label="{{ t('footer.socialMediumAria') }}">
                                            <span class="label">Medium</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
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
		<script src="/assets/js/locale.js"></script>
		<script src="/assets/js/scrolltop.js"></script>
		{% yield scripts %}
	</body>
</html>