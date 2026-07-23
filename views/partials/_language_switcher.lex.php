<?php
// Driven entirely by the alternates the head builder already computed for this
// page, so the switcher can never offer a language the page does not exist in,
// and the query string survives a switch because it is baked into every target.
//
// NOTE: $head is a template global, and views share one scope with the layout
// that includes them. A view that assigns its own $head clobbers this and the
// switcher silently disappears from the footer while the canonical and hreflang
// in the head, rendered earlier, still look right. Folio's pull quote did
// exactly that. Do not reuse the name in a view.
$alternates = $head['alternates'] ?? [];
$currentCode = $currentLang ?? '';

// Two shapes on purpose. A guest gets plain links: crawlable, and they work with
// JavaScript off. A signed-in reader's interface follows their stored preference,
// so their click has to save it too, which means a POST. Keeping the guest half
// free of POST forms is not cosmetic: CacheMiddleware bypasses the full-page
// cache for any body containing one, so a shared form would uncache every public
// page on the site.
$isSignedIn = auth()->check();

// Alternate hrefs are absolute because hreflang requires it, but safe_return_to()
// only accepts site-local paths, so the POST half sends the path and query alone.
$localPath = static function (string $href): string {
    $path = parse_url($href, PHP_URL_PATH) ?: '/';
    $query = parse_url($href, PHP_URL_QUERY);

    return $path.($query !== null && $query !== '' ? '?'.$query : '');
};
?>
<?php if (count($alternates) > 1) { ?>
<div class="lang-switcher" data-lang-switcher>
    <button type="button"
            class="lang-switcher__toggle"
            aria-haspopup="true"
            aria-expanded="false"
            data-lang-toggle>
        <span class="lang-switcher__current"><?= e(strtoupper($currentCode)) ?></span>
        <span class="lang-switcher__caret" aria-hidden="true">&#9662;</span>
    </button>

    <?php if ($isSignedIn) { ?>
    <div class="lang-switcher__menu" data-lang-menu>
        <?php foreach ($alternates as $alt) { ?>
        <form method="post" action="/language" class="lang-switcher__item">
            <?= csrf_field() ?>
            <input type="hidden" name="locale" value="<?= e($alt['hreflang']) ?>">
            <input type="hidden" name="return_to" value="<?= e($localPath($alt['href'])) ?>">
            <button type="submit"
                    lang="<?= e($alt['hreflang']) ?>"
                    <?= $alt['hreflang'] === $currentCode ? 'aria-current="true"' : '' ?>>
                <?= e(locale_native_name($alt['hreflang'])) ?>
            </button>
        </form>
        <?php } ?>
    </div>
    <?php } else { ?>
    <ul class="lang-switcher__menu" data-lang-menu>
        <?php foreach ($alternates as $alt) { ?>
        <li class="lang-switcher__item">
            <a href="<?= e($alt['href']) ?>"
               hreflang="<?= e($alt['hreflang']) ?>"
               lang="<?= e($alt['hreflang']) ?>"
               <?= $alt['hreflang'] === $currentCode ? 'aria-current="true"' : '' ?>>
                <?= e(locale_native_name($alt['hreflang'])) ?>
            </a>
        </li>
        <?php } ?>
    </ul>
    <?php } ?>
</div>
<?php } ?>
