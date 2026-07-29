{% extends "front.lex.php" %}

{% block title %}<?= e(site_content('meta.title')) ?>{% endblock %}

{% block meta %}
<meta name="description" content="<?= e(site_content('meta.description')) ?>" />
<meta property="og:title" content="<?= e(site_content('meta.title')) ?>" />
<meta property="og:description" content="<?= e(site_content('meta.description')) ?>" />
<meta property="og:type" content="website" />
{% endblock %}

{% block body %}
<?php
/**
 * Render a headword with its syllable breaks marked up.
 *
 * Admins type the interpunct directly ("lex·i·con"); the dots become spans so
 * they can take the foil colour, and the aria-label restores the plain word so
 * a screen reader says "lexicon" rather than spelling out the parts.
 *
 * @param  string  $word  Headword, optionally containing U+00B7 separators
 * @return string Escaped HTML for the headword
 */
$headwordHtml = static function (string $word): string {
    $parts = array_map('e', preg_split('/\x{00B7}/u', $word) ?: [$word]);
    $dot = '<span class="lx-dot" aria-hidden="true">&middot;</span>';

    return '<span aria-label="'.e(str_replace("\u{00B7}", '', $word)).'">'
        .implode($dot, $parts)
        .'</span>';
};

$coverHeadword = site_content('banner.headword');
$statsPosts = (int) ($stats['posts'] ?? 0);
?>

<!-- Cover: the bound front matter of the volume. Everything here is the
     banner content the admin already edits, re-set as a dictionary entry. -->
<section class="lx-cover lx-bleed">
    <div class="lx-wrap lx-cover__inner">
        <p class="lx-cover__meta"><?= e(site_content('banner.eyebrow')) ?></p>

        <h1 class="lx-headword"><?= $headwordHtml($coverHeadword) ?></h1>

        <p class="lx-cover__pron">
            <span class="lx-pron"><?= e(site_content('banner.pronunciation')) ?></span>
            <em class="lx-pos"><?= e(site_content('banner.pos')) ?></em>
            <span class="lx-cover__rule" aria-hidden="true"></span>
        </p>

        <ol class="lx-senses">
            <li><p><?= e(site_content('banner.title')) ?></p></li>
            <li><p><?= e(site_content('banner.subtitle')) ?></p></li>
        </ol>

        <?php
        // Actions before the usage note: it keeps the call to action above the
        // fold on short laptops, and a dictionary puts the usage paragraph at
        // the end of an entry anyway.
?>
        <div class="lx-cover__actions">
            {% if (!auth()->check()): %}
                <a href="<?= e(lurl('/register')) ?>" class="lx-btn lx-btn--gilt lx-btn--big"><?= e(site_content('banner.cta')) ?></a>
                <a href="<?= e(lurl('/blogs')) ?>" class="lx-btn lx-btn--ghost lx-btn--big">{{ t('navigation.exploreBlogs') }}</a>
            {% else %}
                <a href="<?= e(lurl('/dashboard')) ?>" class="lx-btn lx-btn--gilt lx-btn--big"><?= e(site_content('banner.ctaDashboard')) ?></a>
                <a href="<?= e(lurl('/blogs')) ?>" class="lx-btn lx-btn--ghost lx-btn--big">{{ t('navigation.exploreBlogs') }}</a>
            {% endif %}
        </div>

        <p class="lx-cover__note">
            <span class="lx-label"><?= e(site_content('banner.noteLabel')) ?></span> <?= e(site_content('banner.body')) ?>
        </p>
    </div>
</section>

<!-- The turn from cover to paper, carrying the only numbers on the page. -->
{% if ($statsPosts > 0): %}
<section class="lx-band lx-bleed" aria-label="{{ t('stats.posts') }}">
    <div class="lx-wrap lx-band__inner">
        <div class="lx-band__item">
            <span class="lx-band__n"><?= number_format($statsPosts) ?></span>
            <span class="lx-band__label">{{ t('stats.posts') }}</span>
        </div>
        <div class="lx-band__item">
            <span class="lx-band__n"><?= number_format((int) ($stats['blogs'] ?? 0)) ?></span>
            <span class="lx-band__label">{{ t('stats.blogs') }}</span>
        </div>
        <div class="lx-band__item">
            <span class="lx-band__n"><?= number_format((int) ($stats['writers'] ?? 0)) ?></span>
            <span class="lx-band__label">{{ t('stats.writers') }}</span>
        </div>
    </div>
</section>
{% endif %}

<!-- How it works. The three steps are a real sequence, so they are numbered;
     the headword above them names the verb they add up to. -->
<section class="lx-section lx-bleed" id="how">
    <div class="lx-wrap">
        <p class="lx-eyebrow"><?= e(site_content('how.title')) ?></p>

        <div class="lx-entryhead" data-reveal="0">
            <h2><?= $headwordHtml(site_content('how.entryWord')) ?></h2>
            <em class="lx-pos"><?= e(site_content('how.entryPos')) ?></em>
            <span class="lx-entryhead__rule" aria-hidden="true"></span>
        </div>

        <ol class="lx-steps">
            <?php foreach (['step1', 'step2', 'step3'] as $stepIndex => $stepKey) { ?>
            <li class="lx-step" data-reveal="<?= $stepIndex ?>">
                <h3><?= e(site_content('how.'.$stepKey.'.title')) ?></h3>
                <p><?= e(site_content('how.'.$stepKey.'.body')) ?></p>
            </li>
            <?php } ?>
        </ol>
    </div>
</section>

<!-- Features as a run of related entries: a headword, a grammar label and a
     definition, which is what each of these actually is. -->
<section class="lx-section lx-bleed" id="entries">
    <div class="lx-wrap">
        <p class="lx-eyebrow"><?= e(site_content('features.title')) ?></p>

        <div class="lx-entries">
            <?php foreach (['writing', 'team', 'ownership', 'readers'] as $entryIndex => $entryKey) { ?>
            <article class="lx-entry" data-reveal="<?= $entryIndex ?>">
                <div>
                    <h3 class="lx-entry__word"><?= $headwordHtml(site_content('features.items.'.$entryKey.'.word')) ?></h3>
                    <em class="lx-pos lx-entry__pos"><?= e(site_content('features.items.'.$entryKey.'.pos')) ?></em>
                </div>
                <div class="lx-entry__def">
                    <h3><?= e(site_content('features.items.'.$entryKey.'.title')) ?></h3>
                    <p><?= e(site_content('features.items.'.$entryKey.'.body')) ?></p>
                </div>
            </article>
            <?php } ?>
        </div>
    </div>
</section>

<!-- Citations: a dictionary shows a word in real use, attributed. That is
     exactly what the editors' picks are. With no picks, the guides keep the
     section presentable instead of promoting random user content. -->
<section class="lx-section lx-bleed" id="citations">
    <div class="lx-wrap">
        {% if (!empty($showcase)): %}
        <p class="lx-eyebrow"><?= e(site_content('showcase.title')) ?></p>
        <p class="lx-lede"><?= e(site_content('showcase.subtitle')) ?></p>

        <ul class="lx-cites">
            {% foreach ($showcase as $citeIndex => $post): %}
            <?php
        $postUrl = '/blog/'.rawurlencode($post['blog_slug']).'/'.rawurlencode($post['slug']);
$excerpt = $post['excerpt'] ?: truncate(strip_tags($post['content'] ?? ''), 160);
$postTitle = (string) ($post['title'] ?? '');
?>
            <li class="lx-cite" data-reveal="<?= (int) $citeIndex ?>">
                <a href="<?= e($postUrl) ?>" class="lx-cite__media" tabindex="-1" aria-hidden="true">
                    <?php if (!empty($post['featured_image'])) { ?>
                        <img src="<?= e($post['featured_image']) ?>" alt="" loading="lazy" />
                    <?php } else { ?>
                        <span class="lx-cite__plate"><?= e(mb_strtoupper(mb_substr(trim($postTitle), 0, 1))) ?></span>
                    <?php } ?>
                </a>

                <h3 class="lx-cite__title"><a href="<?= e($postUrl) ?>"><?= e($postTitle) ?></a></h3>

                <p class="lx-cite__attr">
                    <?= e($post['blog_name'] ?? '') ?>
                    <?php if (!empty($post['published_at'])) { ?>
                        <span aria-hidden="true">&middot;</span>
                        <time datetime="<?= e(iso_datetime($post['published_at'])) ?>"><?= e(local_datetime($post['published_at'], 'M j, Y', site_timezone())) ?></time>
                    <?php } ?>
                </p>

                <p class="lx-cite__excerpt"><?= e($excerpt) ?></p>

                <p class="lx-cite__more">
                    <a href="<?= e($postUrl) ?>">{{ t('showcase.readPost') }} <span aria-hidden="true">&rarr;</span></a>
                </p>
            </li>
            {% endforeach; %}
        </ul>
        {% else %}
        <p class="lx-eyebrow"><?= e(site_content('showcase.emptyTitle')) ?></p>

        <ul class="lx-cites">
            <?php
            $guides = [
['/getting-started/start-your-first-blog', 'sidebar.gettingStarted.items.tip1', '/images/pic07.jpg'],
['/getting-started/write-posts-people-read', 'sidebar.gettingStarted.items.tip2', '/images/pic08.jpg'],
['/getting-started/blog-with-your-team', 'sidebar.gettingStarted.items.tip3', '/images/pic09.jpg'],
            ];
foreach ($guides as $guideIndex => [$guideHref, $guideKey, $guideImage]) {
    $guideBlurb = trim(site_content($guideKey));
    $guideTitle = trim((string) strtok($guideBlurb, '.'));
    ?>
            <li class="lx-cite" data-reveal="<?= $guideIndex ?>">
                <a href="<?= e(lurl($guideHref)) ?>" class="lx-cite__media" tabindex="-1" aria-hidden="true">
                    <img src="<?= e($guideImage) ?>" alt="" loading="lazy" />
                </a>
                <h3 class="lx-cite__title"><a href="<?= e(lurl($guideHref)) ?>"><?= e($guideTitle !== '' ? $guideTitle : $guideBlurb) ?></a></h3>
                <p class="lx-cite__excerpt"><?= e($guideBlurb) ?></p>
                <p class="lx-cite__more">
                    <a href="<?= e(lurl($guideHref)) ?>">{{ t('pages.readGuide') }} <span aria-hidden="true">&rarr;</span></a>
                </p>
            </li>
            <?php } ?>
        </ul>
        {% endif %}
    </div>
</section>

<!-- Usage notes. -->
<section class="lx-section lx-bleed" id="faq">
    <div class="lx-wrap">
        <p class="lx-eyebrow"><?= e(site_content('faq.title')) ?></p>

        <div class="lx-notes">
            <?php foreach (['q1', 'q2', 'q3', 'q4', 'q5', 'q6'] as $noteIndex => $noteKey) { ?>
            <details class="lx-note">
                <summary>
                    <span class="lx-note__n"><?= str_pad((string) ($noteIndex + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <span><?= e(site_content('faq.items.'.$noteKey.'.q')) ?></span>
                    <span class="lx-note__sign" aria-hidden="true"></span>
                </summary>
                <p class="lx-note__body"><?= e(site_content('faq.items.'.$noteKey.'.a')) ?></p>
            </details>
            <?php } ?>
        </div>
    </div>
</section>

<!-- Back cover. -->
<section class="lx-close lx-bleed">
    <div class="lx-wrap lx-close__inner">
        <div>
            <h2><?= e(site_content('cta.title')) ?></h2>
            <p><?= e(site_content('cta.body')) ?></p>
        </div>
        <div class="lx-close__actions">
            {% if (!auth()->check()): %}
                <a href="<?= e(lurl('/register')) ?>" class="lx-btn lx-btn--gilt lx-btn--big"><?= e(site_content('banner.cta')) ?></a>
            {% else %}
                <a href="<?= e(lurl('/dashboard')) ?>" class="lx-btn lx-btn--gilt lx-btn--big"><?= e(site_content('banner.ctaDashboard')) ?></a>
            {% endif %}
        </div>
    </div>
</section>
{% endblock %}
