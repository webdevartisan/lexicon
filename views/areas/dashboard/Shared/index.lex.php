{% extends "back.lex.php" %}
{% block title %}Shared with you{% endblock %}
{% block body %}
<?php
$roleBadge = [
    'editor' => 'bg-custom-50 text-custom-700 border-custom-100 dark:bg-custom-500/20 dark:border-custom-800 dark:text-custom-300',
    'author' => 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800',
    'contributor' => 'bg-sky-100 text-sky-700 border-sky-200 dark:bg-sky-900/40 dark:border-sky-800',
    'reviewer' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:border-amber-800',
];
$toneClasses = [
    'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    'sky' => 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
    'slate' => 'bg-slate-100 text-slate-700 dark:bg-zink-600 dark:text-zink-200',
];
$workflowDot = [
    'in_review' => 'bg-sky-500',
    'needs_changes' => 'bg-amber-500',
];
$fallbackBadge = 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-zink-600 dark:text-zink-200 dark:border-zink-500';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <header class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between mb-6">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 dark:text-zink-50 flex items-center gap-2">
                <i data-lucide="inbox" class="size-5 text-custom-500"></i>
                Shared with you
            </h1>
            <p class="text-sm text-slate-500 dark:text-zink-300 mt-1">
                Blogs other people invited you to. Each card is a place to do your work in that blog.
            </p>
        </div>
        <p class="text-xs text-slate-500 dark:text-zink-400">
            <?= count($cards) ?> blog<?= count($cards) === 1 ? '' : 's' ?>
        </p>
    </header>

    <?php if (empty($cards)) { ?>
    <div class="card">
        <div class="card-body text-center py-16 px-6">
            <i data-lucide="inbox" class="size-12 text-slate-300 dark:text-zink-500 mx-auto mb-3"></i>
            <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1">Nothing shared with you yet</h2>
            <p class="text-sm text-slate-500 dark:text-zink-300 max-w-md mx-auto">
                When someone invites you to write, review, or edit on their blog, it'll appear here.
            </p>
        </div>
    </div>
    <?php } else { ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($cards as $c) {
            $b = $c['blog'];
            $role = (string) $c['role'];
            $stats = (array) $c['stats'];
            $items = (array) ($c['items'] ?? []);
            $primary = $c['actions']['primary'];
            $secondary = $c['actions']['secondary'];
            ?>
        <article class="card flex flex-col">
            <div class="card-body flex flex-col grow">

                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 truncate">
                            <?= e($b['name']) ?>
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-zink-400 mt-0.5 truncate">
                            by <?= e($b['owner_name']) ?>
                            <?php if ($b['status'] !== 'published') { ?>
                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium rounded bg-slate-100 text-slate-600 dark:bg-zink-600 dark:text-zink-200 capitalize">
                                <?= e($b['status']) ?>
                            </span>
                            <?php } ?>
                        </p>
                    </div>
                    <span class="shrink-0 inline-flex items-center px-2 py-0.5 text-[11px] font-medium rounded-full border capitalize <?= $roleBadge[$role] ?? $fallbackBadge ?>">
                        <?= e($role) ?>
                    </span>
                </div>

                <?php if (!empty($stats)) { ?>
                <ul class="grid grid-cols-3 gap-2 mb-4">
                    <?php foreach ($stats as $s) {
                        $tone = (string) ($s['tone'] ?? 'slate');
                        $toneClass = $toneClasses[$tone] ?? $toneClasses['slate'];
                        ?>
                    <li class="rounded-md px-2 py-2 text-center <?= $toneClass ?>">
                        <div class="text-lg font-semibold leading-tight"><?= (int) $s['value'] ?></div>
                        <div class="text-[10px] uppercase tracking-wide opacity-80 mt-0.5"><?= e((string) $s['label']) ?></div>
                    </li>
                    <?php } ?>
                </ul>
                <?php } ?>

                <?php if (!empty($items)) { ?>
                <ul class="space-y-1 mb-4 border-t border-slate-100 dark:border-zink-600 pt-3">
                    <?php foreach ($items as $it) {
                        $state = (string) ($it['workflow_state'] ?? 'in_review');
                        $unassigned = empty($it['reviewer_id']);
                        ?>
                    <li>
                        <a href="/dashboard/post/<?= (int) $it['id'] ?>/review" class="flex items-center gap-2 text-xs py-1 hover:text-custom-500 group">
                            <span class="inline-block size-1.5 rounded-full shrink-0 <?= $workflowDot[$state] ?? 'bg-slate-400' ?>"></span>
                            <span class="truncate text-slate-700 dark:text-zink-200 group-hover:text-custom-500"><?= e($it['title']) ?></span>
                            <?php if ($unassigned) { ?>
                            <span class="ml-auto shrink-0 text-[10px] uppercase tracking-wide font-medium text-amber-600 dark:text-amber-400">Unassigned</span>
                            <?php } ?>
                        </a>
                    </li>
                    <?php } ?>
                </ul>
                <?php } ?>

                <div class="mt-auto flex items-center justify-between gap-3 pt-2 border-t border-slate-100 dark:border-zink-600">
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="<?= e((string) ($primary['href'] ?? '#')) ?>"
                           class="inline-flex items-center justify-center gap-2 rounded-md font-medium focus:ring bg-white text-custom-500 border border-custom-500 btn hover:text-white hover:bg-custom-600 hover:border-custom-600 dark:bg-zink-700 dark:hover:bg-custom-500 px-3 py-1.5 text-sm">
                            <i data-lucide="<?= e((string) ($primary['icon'] ?? 'arrow-right')) ?>" class="inline-block size-4" aria-hidden="true"></i>
                            <?= e((string) ($primary['label'] ?? '')) ?>
                        </a>
                        <?php if ($secondary) { ?>
                        <a href="<?= e((string) $secondary['href']) ?>" class="text-xs font-medium text-slate-500 hover:text-custom-500 dark:text-zink-300 inline-flex items-center gap-1">
                            <i data-lucide="<?= e((string) $secondary['icon']) ?>" class="size-3.5"></i>
                            <?= e((string) $secondary['label']) ?>
                        </a>
                        <?php } ?>
                    </div>
                    <form method="POST" action="/dashboard/blog/<?= (int) $b['id'] ?>/team/leave" class="m-0"
                          onsubmit="return confirm('Leave “<?= e(addslashes($b['name'])) ?>”? You will need a new invitation to rejoin.');">
                        {{ csrf_field() }}
                        <button type="submit" class="text-xs font-medium text-slate-400 hover:text-red-500 dark:text-zink-500" title="Leave this blog">
                            Leave
                        </button>
                    </form>
                </div>
            </div>
        </article>
        <?php } ?>
    </div>

    <?php } ?>

</div>
{% endblock %}
