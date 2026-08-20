{% extends "front.lex.php" %}

{% block title %}<?= e($t('reader.metaReplies')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<section class="lx-reader">
    {% include "partials/public/_reader_header.lex.php" %}

    <?php if (empty($items)) {
        $readerEmptyMessage = $t('reader.emptyReplies'); ?>
        {% include "partials/public/_reader_empty.lex.php" %}
    <?php } else { ?>
    <ul class="lx-reader-list" data-reader-replies>
        <?php
        $renderedIds = [];

        foreach ($items as $row) {
            $payload = json_decode((string) ($row['data'] ?? '{}'), true) ?: [];
            $replyId = (int) ($row['id'] ?? 0);
            $renderedIds[] = $replyId;

            $isUnread = empty($row['read_at']);
            $commentId = (int) ($payload['comment_id'] ?? 0);
            $replyUrl = lurl('/blog/'.rawurlencode((string) $row['blog_slug']).'/'.rawurlencode((string) $row['post_slug']))
                .'#comment-'.$commentId;
            ?>
        <li class="lx-reader-row<?= $isUnread ? ' is-unread' : '' ?>" data-reply-id="<?= $replyId ?>">
            <div class="lx-reader-body">
                <p class="lx-reader-context">
                    <?php
                    // Display text comes from the payload snapshot; the link is
                    // rebuilt from the live slugs, so a renamed post keeps working.
                    ?>
                    <strong><?= e($payload['commenter_name'] ?? $t('reader.someone')) ?></strong>
                    <?= e($t('reader.replyContext', ['title' => (string) ($payload['post_title'] ?? '')])) ?>
                </p>
                <p class="lx-reader-excerpt"><a href="<?= e($replyUrl) ?>"><?= e($payload['comment_excerpt'] ?? '') ?></a></p>
                <p class="lx-reader-meta">
                    <time datetime="<?= e(iso_datetime($row['created_at'] ?? null)) ?>"><?= e(local_datetime($row['created_at'] ?? null)) ?></time>
                    <?php if ($isUnread) { ?>
                    <span class="lx-reader-dot"><?= e($t('reader.unread')) ?></span>
                    <?php } ?>
                </p>
            </div>
        </li>
        <?php } ?>
    </ul>

    <?php
    // Marking read is a POST and only ever a POST, so nothing a browser does on
    // its own -- prefetching, preloading, a HEAD request -- can clear the badge.
    // The button is always rendered rather than being a scripting-off fallback,
    // so there is one code path and a reader without JavaScript keeps control of
    // their own badge.
    ?>
    <form class="lx-reader-markread" method="post" action="<?= e(lurl('/replies/mark-read')) ?>" data-reader-markread>
        <?= csrf_field() ?>
        <?php foreach ($renderedIds as $renderedId) { ?>
        <input type="hidden" name="ids[]" value="<?= (int) $renderedId ?>" />
        <?php } ?>
        <button type="submit" class="lx-btn lx-btn--quiet lx-btn--small"><?= e($t('reader.markRead')) ?></button>
    </form>

    {% include "partials/public/_reader_pagination.lex.php" %}
    <?php } ?>
</section>
{% endblock %}

{% block scripts %}
<script src="/assets/js/reader.js" defer></script>
{% endblock %}
