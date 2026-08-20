{% extends "front.lex.php" %}

{% block title %}<?= e($t('reader.metaMyComments')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<section class="lx-reader">
    {% include "partials/public/_reader_header.lex.php" %}

    <?php if (empty($items)) {
        $readerEmptyMessage = $t('reader.emptyMyComments'); ?>
        {% include "partials/public/_reader_empty.lex.php" %}
    <?php } else { ?>
    <ul class="lx-reader-list">
        <?php foreach ($items as $row) {
            $commentId = (int) ($row['id'] ?? 0);
            $commentUrl = lurl('/blog/'.rawurlencode((string) $row['blog_slug']).'/'.rawurlencode((string) $row['post_slug']))
                .'#comment-'.$commentId;
            $isPending = ($row['status'] ?? '') === 'pending';
            ?>
        <li class="lx-reader-row">
            <div class="lx-reader-body">
                <p class="lx-reader-context">
                    <?= e($t('reader.commentContext')) ?>
                    <a href="<?= e($commentUrl) ?>"><?= e($row['post_title'] ?? '') ?></a>
                </p>
                <p class="lx-reader-excerpt"><?= e(truncate((string) ($row['content'] ?? ''), 200)) ?></p>
                <p class="lx-reader-meta">
                    <time datetime="<?= e(iso_datetime($row['created_at'] ?? null)) ?>"><?= e(local_datetime($row['created_at'] ?? null)) ?></time>
                    <?php
                    // Their own writing, so a comment still in a queue is shown
                    // and labelled rather than hidden, which would read as loss.
                    if ($isPending) { ?>
                    <span class="lx-reader-flag"><?= e($t('reader.awaitingModeration')) ?></span>
                    <?php } ?>
                </p>
            </div>
        </li>
        <?php } ?>
    </ul>

    {% include "partials/public/_reader_pagination.lex.php" %}
    <?php } ?>
</section>
{% endblock %}
