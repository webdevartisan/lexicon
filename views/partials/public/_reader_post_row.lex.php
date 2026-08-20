<?php
// One post in Saved or Liked. The two lists are the same row with a different
// remove endpoint, so the page sets $readerRemoveBase and $readerRemoveLabelKey
// and everything else is shared.
//
// $row comes from the enclosing loop.
$rowTitle = (string) ($row['title'] ?? '');
$rowBlogSlug = (string) ($row['blog_slug'] ?? '');
$rowSlug = (string) ($row['slug'] ?? '');
$rowUrl = lurl('/blog/'.rawurlencode($rowBlogSlug).'/'.rawurlencode($rowSlug));
$rowExcerpt = trim((string) ($row['excerpt'] ?? ''));
$rowCover = (string) ($row['featured_image'] ?? '');
$rowPostId = (int) ($row['id'] ?? 0);
?>
<li class="lx-reader-row">
    <?php if ($rowCover !== '') { ?>
    <a class="lx-reader-thumb" href="<?= e($rowUrl) ?>" tabindex="-1" aria-hidden="true">
        <img src="<?= e($rowCover) ?>" alt="" loading="lazy" />
    </a>
    <?php } ?>

    <div class="lx-reader-body">
        <a class="lx-reader-blog" href="<?= e(lurl('/blog/'.rawurlencode($rowBlogSlug))) ?>"><?= e($row['blog_name'] ?? '') ?></a>
        <h2 class="lx-reader-row-title"><a href="<?= e($rowUrl) ?>"><?= e($rowTitle) ?></a></h2>
        <?php if ($rowExcerpt !== '') { ?>
        <p class="lx-reader-excerpt"><?= e(truncate($rowExcerpt, 150)) ?></p>
        <?php } ?>
        <p class="lx-reader-meta">
            <time datetime="<?= e(iso_datetime($row['published_at'] ?? null)) ?>"><?= e(local_datetime($row['published_at'] ?? null)) ?></time>
        </p>
    </div>

    <?php
    // Always in the DOM, revealed by hover or focus-within and always visible
    // on touch. Inserting it on hover would put it out of reach of a keyboard.
    ?>
    <form class="lx-reader-act" method="post"
          action="<?= e(lurl($readerRemoveBase.'/'.$rowPostId.'/remove')) ?>">
        <?= csrf_field() ?>
        <?php if ((int) ($pagination['page'] ?? 1) > 1) { ?>
        <input type="hidden" name="page" value="<?= (int) $pagination['page'] ?>" />
        <?php } ?>
        <button type="submit" class="lx-reader-act-btn"
                aria-label="<?= e($t($readerRemoveLabelKey, ['title' => $rowTitle])) ?>">
            <?= e($t('reader.remove')) ?>
        </button>
    </form>
</li>
