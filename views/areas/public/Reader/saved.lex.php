{% extends "front.lex.php" %}

{% block title %}<?= e($t('reader.metaSaved')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<section class="lx-reader">
    {% include "partials/public/_reader_header.lex.php" %}

    <?php if (empty($items)) {
        $readerEmptyMessage = $t('reader.emptySaved'); ?>
        {% include "partials/public/_reader_empty.lex.php" %}
    <?php } else {
        $readerRemoveBase = '/saved';
        $readerRemoveLabelKey = 'reader.removeFromSaved'; ?>
    <ul class="lx-reader-list">
        <?php foreach ($items as $row) { ?>
            {% include "partials/public/_reader_post_row.lex.php" %}
        <?php } ?>
    </ul>

    {% include "partials/public/_reader_pagination.lex.php" %}
    <?php } ?>
</section>
{% endblock %}
