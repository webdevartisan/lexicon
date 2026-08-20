<?php
// Title and tabs for a reader surface. Each page sets $surface and this works
// out the rest, so adding a tab is one entry here rather than an edit in two
// views that then drift.
//
// Tabs are links to real URLs, not a widget: they are separate pages, so they
// get aria-current="page" and nothing else. An ARIA tab widget would promise
// arrow-key semantics these do not have.
$readerGroups = [
    'saved' => [
        'title' => 'reader.savedTitle',
        'tabs' => [
            ['surface' => 'saved', 'path' => '/saved', 'label' => 'reader.tabSaved'],
            ['surface' => 'liked', 'path' => '/saved/liked', 'label' => 'reader.tabLiked'],
        ],
    ],
    'replies' => [
        'title' => 'reader.repliesTitle',
        'tabs' => [
            ['surface' => 'replies', 'path' => '/replies', 'label' => 'reader.tabReplies'],
            ['surface' => 'my-comments', 'path' => '/replies/mine', 'label' => 'reader.tabMyComments'],
        ],
    ],
    'subscriptions' => [
        'title' => 'reader.subscriptionsTitle',
        'tabs' => [],
    ],
];

$readerSurface = $surface ?? 'saved';
$readerGroupKey = match ($readerSurface) {
    'liked' => 'saved',
    'my-comments' => 'replies',
    default => $readerSurface,
};
$readerGroup = $readerGroups[$readerGroupKey] ?? $readerGroups['saved'];
?>
<header class="lx-reader-head">
    <h1 class="lx-reader-title"><?= e($t($readerGroup['title'])) ?></h1>

    <?php if ($readerGroup['tabs'] !== []) { ?>
    <nav class="lx-reader-tabs" aria-label="<?= e($t($readerGroup['title'])) ?>">
        <?php foreach ($readerGroup['tabs'] as $readerTab) {
            $isCurrent = $readerTab['surface'] === $readerSurface;
            ?>
        <a class="lx-reader-tab<?= $isCurrent ? ' is-current' : '' ?>"
           href="<?= e(lurl($readerTab['path'])) ?>"
           <?= $isCurrent ? 'aria-current="page"' : '' ?>><?= e($t($readerTab['label'])) ?></a>
        <?php } ?>
    </nav>
    <?php } ?>
</header>
