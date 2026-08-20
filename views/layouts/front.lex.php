<?php
$flash = flash();
$errors = errors();
$old = old();

// Home passes 'over' so the bar starts transparent on the dark cover; every
// other public page gets the paper bar from the first paint.
$navMode = ($navMode ?? '') === 'over' ? 'over' : 'paper';

$siteName = site_setting('site_name', 'Lexicon');
$frontNav = $nav_items ?? [];

// The wordmark is the Home link and the primary button is "create a blog", so
// repeating either in the bar would be dead weight. Both stay in the drawer
// and the footer, where the full map is the point.
$barNav = array_filter($frontNav, static function (array $it): bool {
    return ($it['href'] ?? '') !== '/' && ($it['key'] ?? '') !== 'navigation.createBlog';
});
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
		<meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="theme-color" content="#16233f" />

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

        <?php
        // The two display faces block first paint, so they are fetched in
        // parallel with the stylesheet rather than discovered inside it.
?>
        <link rel="preload" href="/assets/fonts/newsreader-latin.woff2" as="font" type="font/woff2" crossorigin />
        <link rel="preload" href="/assets/fonts/instrumentsans-latin.woff2" as="font" type="font/woff2" crossorigin />

        <?php /* Lexicon's own controls, shared with every blog theme. */ ?>
        <link rel="stylesheet" href="/assets/css/platform-chrome.css" />
        <link rel="stylesheet" href="/assets/css/lexicon.css" />
        <link rel="stylesheet" href="/assets/css/fontawesome-all.min.css" />

        {% yield meta %}

	</head>

	<body>

        <a class="lx-skip" href="#main"><?= e($t('a11y.skipToContent')) ?></a>

        <header class="lx-nav<?= $navMode === 'over' ? ' is-over' : '' ?>" data-lx-nav="<?= e($navMode) ?>">
            <div class="lx-wrap lx-nav__inner">
                <a href="<?= e(lurl('/')) ?>" class="lx-logo">
                    <span class="lx-logo__mark" aria-hidden="true"></span>
                    <?= e($siteName) ?>
                </a>

                <ul class="lx-nav__links">
                    <?php
            // NavigationService hands over a translation key alongside the
            // English label; rendering the label alone is what left the
            // menu in English on /el and /ar.
            foreach ($barNav as $it) { ?>
                    <li>
                        <a class="lx-nav__link" href="<?= e(lurl($it['href'])) ?>" {{ it['current_attr'] }}><?= e(nav_label($it)) ?></a>
                    </li>
                    <?php } ?>
                </ul>

                <ul class="lx-nav__actions" data-auth-nav="platform">
                    <?php $authNavVariant = 'platform'; ?>
                    {% include "partials/_auth_nav.lex.php" %}
                </ul>

                <button type="button" class="lx-nav__toggle" data-lx-drawer-open
                        aria-expanded="false" aria-controls="lx-drawer"
                        aria-label="<?= e($t('a11y.openMenu')) ?>">
                    <span aria-hidden="true"></span>
                </button>
            </div>
        </header>

        <div class="lx-drawer" id="lx-drawer" hidden>
            <div class="lx-drawer__head">
                <a href="<?= e(lurl('/')) ?>" class="lx-logo">
                    <span class="lx-logo__mark" aria-hidden="true"></span>
                    <?= e($siteName) ?>
                </a>
                <button type="button" class="lx-drawer__close" data-lx-drawer-close
                        aria-label="<?= e($t('a11y.closeMenu')) ?>">&times;</button>
            </div>

            <ul class="lx-drawer__links">
                <?php foreach ($frontNav as $it) { ?>
                <li><a href="<?= e(lurl($it['href'])) ?>" {{ it['current_attr'] }}><?= e(nav_label($it)) ?></a></li>
                <?php } ?>
            </ul>

            <div class="lx-drawer__actions">
                {% if (!auth()->check()): %}
                    <a class="lx-btn lx-btn--gilt lx-btn--fit" href="<?= e(lurl('/register')) ?>">{{ t('navigation.createBlog') }}</a>
                    <a class="lx-btn lx-btn--ghost lx-btn--fit" href="<?= e(lurl('/login')) ?>">{{ t('header.signIn') }}</a>
                {% else %}
                    <?php
                    // Same rule as the bar: a reader has no dashboard, so the
                    // drawer offers them the blog form instead of a redirect
                    // back to where they already are.
                    $drawerIsReader = !empty($viewer['is_reader']);
?>
                    <a class="lx-btn lx-btn--gilt lx-btn--fit" href="<?= e(lurl($drawerIsReader ? '/dashboard/blog/new' : '/dashboard')) ?>"><?= e($t($drawerIsReader ? 'navigation.createBlog' : 'header.dashboard')) ?></a>
                    <form method="post" action="<?= e(lurl('/logout')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="lx-btn lx-btn--ghost lx-btn--fit">{{ t('header.signOut') }}</button>
                    </form>
                {% endif %}
            </div>
        </div>

        <main id="main" class="lx-main">
            {% yield body %}
        </main>

        <?php
// Footer as colophon. It carries the full site map that used to live in
// the right sidebar, so removing the sidebar loses no navigation.
$footerEmail = site_content('contact.email', '');
$footerPhone = site_content('contact.phone', '');
$footerAddress = site_content('contact.address', '');

// The guide blurbs are full sentences in the content store. The first
// sentence of each makes a usable link label without a second key.
$guideLinks = [];
foreach ([
    ['/getting-started/start-your-first-blog', 'sidebar.gettingStarted.items.tip1'],
    ['/getting-started/write-posts-people-read', 'sidebar.gettingStarted.items.tip2'],
    ['/getting-started/blog-with-your-team', 'sidebar.gettingStarted.items.tip3'],
] as [$href, $key]) {
    $blurb = trim(site_content($key));
    if ($blurb === '') {
        continue;
    }
    $label = trim((string) strtok($blurb, '.'));
    $guideLinks[] = ['href' => $href, 'label' => $label !== '' ? $label : $blurb];
}

// Icons appear only for networks the admin configured, so a fresh
// install never ships dead placeholder links.
$socialNetworks = [
    ['key' => 'social.twitter', 'mark' => 'x', 'label' => 'Twitter', 'ariaKey' => 'footer.socialTwitterAria'],
    ['key' => 'social.facebook', 'mark' => 'facebook', 'label' => 'Facebook', 'ariaKey' => 'footer.socialFacebookAria'],
    ['key' => 'social.instagram', 'mark' => 'instagram', 'label' => 'Instagram', 'ariaKey' => 'footer.socialInstagramAria'],
    ['key' => 'social.medium', 'mark' => 'medium', 'label' => 'Medium', 'ariaKey' => 'footer.socialMediumAria'],
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
        <footer class="lx-foot" role="contentinfo">
            <div class="lx-wrap lx-foot__inner">
                <div class="lx-foot__brand">
                    <a href="<?= e(lurl('/')) ?>" class="lx-logo">
                        <span class="lx-logo__mark" aria-hidden="true"></span>
                        <?= e($siteName) ?>
                    </a>
                    <p class="lx-foot__tagline">{{ t('header.logoTagline') }}</p>
                    <p class="lx-foot__about"><?= e(site_content('footer.aboutText')) ?></p>
                </div>

                <nav aria-label="{{ t('footer.quickLinksTitle') }}">
                    <h2>{{ t('footer.quickLinksTitle') }}</h2>
                    <ul>
                        <?php foreach ($frontNav as $it) { ?>
                        <li><a href="<?= e(lurl($it['href'])) ?>"><?= e(nav_label($it)) ?></a></li>
                        <?php } ?>
                    </ul>
                </nav>

                <nav aria-label="<?= e(site_content('sidebar.gettingStarted.title')) ?>">
                    <h2><?= e(site_content('sidebar.gettingStarted.title')) ?></h2>
                    <ul>
                        {% foreach ($guideLinks as $guide): %}
                        <li><a href="<?= e(lurl($guide['href'])) ?>">{{ guide.label }}</a></li>
                        {% endforeach; %}
                        <li><a href="<?= e(lurl('/getting-started')) ?>"><?= e(site_content('sidebar.gettingStarted.actionMore')) ?></a></li>
                    </ul>
                </nav>

                <div>
                    <h2>{{ t('footer.legalTitle') }}</h2>
                    <ul>
                        <li><a href="<?= e(lurl('/privacy')) ?>">{{ t('footer.linkPrivacy') }}</a></li>
                        <li><a href="<?= e(lurl('/terms')) ?>">{{ t('footer.linkTerms') }}</a></li>
                        <li><a href="<?= e(lurl('/cookies')) ?>">{{ t('footer.linkCookies') }}</a></li>
                    </ul>

                    <h2 style="margin-top:2rem">{{ t('contact.title') }}</h2>
                    <ul class="lx-foot__contact">
                        {% if (!empty($footerEmail)): %}
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="2.5" y="4.5" width="19" height="15" rx="2"/><path d="m3 6 9 6.5L21 6"/></svg>
                            <a href="mailto:<?= e($footerEmail) ?>"><?= e($footerEmail) ?></a>
                        </li>
                        {% endif %}
                        {% if (!empty($footerPhone)): %}
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M6 3h3l2 5-2.5 1.5a12 12 0 0 0 6 6L16 13l5 2v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4 5.2 2 2 0 0 1 6 3Z"/></svg>
                            <span><?= e($footerPhone) ?></span>
                        </li>
                        {% endif %}
                        {% if (!empty($footerAddress)): %}
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/></svg>
                            <span><?= e($footerAddress) ?></span>
                        </li>
                        {% endif %}
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M21 12a8 8 0 1 1-3.2-6.4"/><path d="M21 4.5V10h-5.5"/></svg>
                            <a href="<?= e(lurl('/contact')) ?>">{{ t('contact.link') }}</a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="lx-wrap lx-foot__bottom">
                <p class="lx-foot__copy">
                    &copy; <?= date('Y') ?> <?= e($siteName) ?>. {{ t('footer.rightsReserved') }}
                </p>

                {% include "partials/_language_switcher.lex.php" %}

                {% if (!empty($configuredSocials)): %}
                <ul class="lx-foot__social" aria-label="{{ t('footer.socialAria') }}">
                    {% foreach ($configuredSocials as $social): %}
                    <li>
                        <a href="{{ social.url }}" rel="noopener noreferrer" target="_blank"
                           aria-label="<?= e($t($social['ariaKey'])) ?>">
                            <?php if ($social['mark'] === 'x') { ?>
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.53 3h3.02l-6.6 7.54L21.7 21h-5.7l-4.47-5.84L6.4 21H3.38l7.06-8.07L2.6 3h5.84l4.04 5.34L17.53 3Zm-1.06 16.2h1.67L7.6 4.71H5.8l10.67 14.49Z"/></svg>
                            <?php } elseif ($social['mark'] === 'facebook') { ?>
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-8h2.7l.4-3.13H13.5V7.87c0-.9.25-1.52 1.55-1.52h1.65V3.55c-.29-.04-1.27-.13-2.42-.13-2.4 0-4.04 1.47-4.04 4.16v2.29H7.5V13h2.74v8h3.26Z"/></svg>
                            <?php } elseif ($social['mark'] === 'instagram') { ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5.2"/><circle cx="12" cy="12" r="4.1"/><circle cx="17.3" cy="6.7" r="1.1" fill="currentColor" stroke="none"/></svg>
                            <?php } else { ?>
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.37 7.5a.72.72 0 0 0-.23-.6L2.7 5.18v-.27h4.6l3.56 7.8 3.13-7.8h4.38v.27l-1.26 1.2a.37.37 0 0 0-.14.35v8.84c-.02.14.04.28.14.36l1.23 1.2v.26h-6.2v-.26l1.28-1.24c.12-.13.12-.16.12-.35V8.35l-3.55 9.01h-.48L5.5 8.35v6.04c-.03.25.05.5.23.68l1.66 2.02v.26H2.68v-.26l1.66-2.02a.78.78 0 0 0 .21-.68V7.5Z"/></svg>
                            <?php } ?>
                        </a>
                    </li>
                    {% endforeach; %}
                </ul>
                {% endif %}
            </div>
        </footer>

        {% include "partials/_consent_bootstrap.lex.php" %}
        {% include "partials/_consent_banner.lex.php" %}

        <button type="button" class="fab scroll-top-btn" title="<?= e($t('a11y.backToTop')) ?>" aria-label="<?= e($t('a11y.backToTop')) ?>"></button>

		<!-- Scripts -->
		<script src="/assets/js/platform-menu.js" defer></script>
		<script src="/assets/js/front.js" defer></script>
		<script src="/assets/js/lang-switcher.js" defer></script>
		<script src="/assets/js/scrolltop.js" defer></script>
		{% include "partials/_auth_modal.lex.php" %}
		{% yield scripts %}
	</body>
</html>
