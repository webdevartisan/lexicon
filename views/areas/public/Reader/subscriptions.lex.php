{% extends "front.lex.php" %}

{% block title %}<?= e($t('reader.metaSubscriptions')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<section class="lx-reader">
    {% include "partials/public/_reader_header.lex.php" %}

    <?php
    // Undo cannot live on the row: the action redirects, so the row is already
    // out of the query by the time anything renders again. It becomes a strip
    // above the list instead, and it lasts exactly as long as the flash that
    // carried it, which is this one render.
    if (!empty($undo)) { ?>
    <div class="lx-reader-undo">
        <p><?= e($t('reader.unsubscribedFrom', ['blog' => (string) $undo['blog_name']])) ?></p>
        <form method="post" action="<?= e(lurl('/subscriptions/'.(int) $undo['blog_id'].'/resubscribe')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="lx-reader-act-btn"><?= e($t('reader.undo')) ?></button>
        </form>
    </div>
    <?php } ?>

    <?php if (empty($items)) {
        $readerEmptyMessage = $t('reader.emptySubscriptions'); ?>
        {% include "partials/public/_reader_empty.lex.php" %}
    <?php } else { ?>
    <ul class="lx-reader-list">
        <?php foreach ($items as $row) {
            $blogId = (int) ($row['blog_id'] ?? 0);
            $blogName = (string) ($row['blog_name'] ?? '');
            $blogUrl = lurl('/blog/'.rawurlencode((string) $row['blog_slug']));
            $isAvailable = ($row['blog_status'] ?? '') === 'published';
            $description = trim((string) ($row['description'] ?? ''));
            ?>
        <li class="lx-reader-row">
            <div class="lx-reader-body">
                <h2 class="lx-reader-row-title">
                    <?php
                    // A blog that is no longer public still gets a row. A
                    // subscription you cannot see is one you cannot cancel.
                    if ($isAvailable) { ?>
                    <a href="<?= e($blogUrl) ?>"><?= e($blogName) ?></a>
                    <?php } else { ?>
                    <span><?= e($blogName) ?></span>
                    <?php } ?>
                </h2>
                <?php if ($description !== '') { ?>
                <p class="lx-reader-excerpt"><?= e(truncate($description, 150)) ?></p>
                <?php } ?>
                <p class="lx-reader-meta">
                    <time datetime="<?= e(iso_datetime($row['subscribed_at'] ?? null)) ?>"><?= e(local_datetime($row['subscribed_at'] ?? null)) ?></time>
                    <?php if (!$isAvailable) { ?>
                    <span class="lx-reader-flag"><?= e($t('reader.unavailable')) ?></span>
                    <?php } ?>
                </p>
            </div>

            <form class="lx-reader-act" method="post"
                  action="<?= e(lurl('/subscriptions/'.$blogId.'/unsubscribe')) ?>">
                <?= csrf_field() ?>
                <?php if ((int) ($pagination['page'] ?? 1) > 1) { ?>
                <input type="hidden" name="page" value="<?= (int) $pagination['page'] ?>" />
                <?php } ?>
                <button type="submit" class="lx-reader-act-btn"
                        aria-label="<?= e($t('reader.unsubscribeFrom', ['blog' => $blogName])) ?>">
                    <?= e($t('reader.unsubscribe')) ?>
                </button>
            </form>
        </li>
        <?php } ?>
    </ul>

    {% include "partials/public/_reader_pagination.lex.php" %}
    <?php } ?>
</section>
{% endblock %}
