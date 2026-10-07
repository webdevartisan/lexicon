{% extends "back.lex.php" %}

{% block title %}Email Layouts{% endblock %}
{% block subtitle %}The HTML document every email goes into: its head, wrapper, header, footer and colours.{% endblock %}

{% block body %}
<?php $activeTab = 'layouts'; ?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    {% include "areas/admin/Email/_shared.lex.php" %}

    <div class="flex flex-wrap items-center gap-3 mb-5">
        <p class="grow text-sm text-slate-500 dark:text-zink-300">
            A layout has no words of its own, since it is not translated; each email's words go where it says <code><?= e($ph('content')) ?></code>.
        </p>
        <a href="<?= e(lurl('/admin/email-templates/layouts/create')) ?>" class="btn bg-custom-500 border-custom-500 text-white hover:bg-custom-600 inline-flex items-center gap-2">
            <i data-lucide="plus" class="size-4"></i> New layout
        </a>
    </div>

    <div class="card mb-0">
        <div class="card-body overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-slate-500 dark:text-zink-200 bg-slate-100 dark:bg-zink-600">
                    <tr>
                        <th class="px-3.5 py-2 font-semibold">Layout</th>
                        <th class="px-3.5 py-2 font-semibold">Colours</th>
                        <th class="px-3.5 py-2 font-semibold">Used by</th>
                        <th class="px-3.5 py-2"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($layouts as $slug => $layout) { ?>
                    <tr class="border-b border-slate-200 dark:border-zink-500 align-top">
                        <td class="px-3.5 py-2.5">
                            <a href="<?= e(lurl('/admin/email-templates/layouts/'.$slug.'/edit')) ?>" class="font-medium text-slate-900 hover:text-custom-500 dark:text-zink-50"><?= e($layout['name']) ?></a>
                            <?= $sourceBadge($layout['source']) ?>
                            <p class="text-xs text-slate-500 dark:text-zink-300"><code><?= e($slug) ?></code></p>
                        </td>
                        <td class="px-3.5 py-2.5">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="inline-block size-4 rounded border border-slate-200" style="background:<?= e($layout['primary_color']) ?>" title="Primary <?= e($layout['primary_color']) ?>"></span>
                                <span class="inline-block size-4 rounded border border-slate-200" style="background:<?= e($layout['background_color']) ?>" title="Background <?= e($layout['background_color']) ?>"></span>
                            </span>
                        </td>
                        <td class="px-3.5 py-2.5 text-slate-600 dark:text-zink-200">
                            <?= count($usage[$slug]) === 0 ? 'No emails' : count($usage[$slug]).' email'.(count($usage[$slug]) === 1 ? '' : 's') ?>
                        </td>
                        <td class="px-3.5 py-2.5 text-right whitespace-nowrap">
                            <a href="<?= e(lurl('/admin/email-templates/layouts/'.$slug.'/edit')) ?>" class="inline-flex items-center gap-1 px-2 py-1 text-slate-500 hover:text-custom-500">
                                <i data-lucide="pencil" class="size-4"></i> Edit<span class="sr-only"> <?= e($layout['name']) ?></span>
                            </a>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
{% endblock %}
