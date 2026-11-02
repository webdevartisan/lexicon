<?php
/**
 * Star shown before the title of the post a blog features on its homepage.
 */
$featuredLabel = $t('components.postCard.featured');
?>
<span class="shrink-0 inline-flex text-amber-500" title="<?= e($featuredLabel) ?>">
    {% cache 'lucide:star:featured' ttl=31536000 %}<i data-lucide="star" class="size-3.5 fill-amber-400" aria-hidden="true"></i>{% endcache %}
    <span class="sr-only"><?= e($featuredLabel) ?></span>
</span>
