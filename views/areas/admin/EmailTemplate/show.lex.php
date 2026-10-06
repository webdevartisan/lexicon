{% extends "back.lex.php" %}

{% block title %}Preview: <?= e($template['label']) ?>{% endblock %}
{% block subtitle %}Filled with each block's sample values. Emails using this template fill it with their own wording.{% endblock %}

{% block body %}
<?php
$activeTab = 'templates';
$basePath = '/admin/email-templates';
$tabButton = 'px-4 py-2 -mb-px text-sm font-medium border-b-2 aria-selected:border-custom-500 aria-selected:text-custom-500 border-transparent text-slate-500 dark:text-zink-200';

// Which blocks each placeholder appears in, for the Placeholders tab.
$placeholderBlocks = [];
foreach ($template['layout'] as $componentSlug) {
    $component = $components[$componentSlug] ?? null;
    foreach ($component === null ? [] : \App\Services\TemplateRendererService::placeholdersOfComponent($component) as $name) {
        $placeholderBlocks[$name][$component['label']] = true;
    }
}
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    {% include "areas/admin/EmailTemplate/_tabs.lex.php" %}

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="card mb-0 lg:col-span-2">
            <div class="card-body !p-0">
                <div class="flex border-b border-slate-200 dark:border-zink-500 px-2" role="tablist" aria-label="Preview views">
                    <button type="button" role="tab" id="tab-html" aria-controls="panel-html" aria-selected="true" class="<?= $tabButton ?>">Email</button>
                    <button type="button" role="tab" id="tab-text" aria-controls="panel-text" aria-selected="false" class="<?= $tabButton ?>">Plain text</button>
                    <button type="button" role="tab" id="tab-placeholders" aria-controls="panel-placeholders" aria-selected="false" class="<?= $tabButton ?>">Placeholders</button>
                </div>

                <div id="panel-html" role="tabpanel" aria-labelledby="tab-html" class="p-3 bg-slate-100 dark:bg-zink-800">
                    <iframe src="<?= e(lurl($basePath.'/'.$template['slug'].'/preview/render')) ?>" title="Email preview" sandbox
                            class="w-full bg-white border-0 rounded shadow-sm" style="height: 720px;"></iframe>
                </div>

                <div id="panel-text" role="tabpanel" aria-labelledby="tab-text" hidden class="p-4">
                    <pre class="p-4 overflow-x-auto text-xs leading-relaxed whitespace-pre-wrap rounded bg-slate-50 text-slate-700 dark:bg-zink-700 dark:text-zink-100"><?= e($rendered->text) ?></pre>
                </div>

                <div id="panel-placeholders" role="tabpanel" aria-labelledby="tab-placeholders" hidden class="p-4">
                    <table class="w-full text-sm">
                        <thead class="text-left text-slate-500 dark:text-zink-200">
                            <tr><th class="py-2 font-semibold">Placeholder</th><th class="py-2 font-semibold">In block</th><th class="py-2 font-semibold">Filled by</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($placeholders as $name) { ?>
                            <tr class="border-t border-slate-200 dark:border-zink-500">
                                <td class="py-2"><code><?= e($ph($name)) ?></code></td>
                                <td class="py-2"><?= e(implode(', ', array_keys($placeholderBlocks[$name] ?? []))) ?></td>
                                <td class="py-2 text-slate-500"><?= in_array($name, $globals, true) ? 'Always available' : 'Each email\'s wording' ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <aside class="space-y-5">
            <div class="card mb-0">
                <div class="card-body space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-slate-900 dark:text-zink-50"><?= e($template['label']) ?></h2>
                        <?= $sourceBadge($template['source']) ?>
                    </div>
                    <?php if ($template['description'] !== '') { ?>
                    <p class="text-slate-500 dark:text-zink-300"><?= e($template['description']) ?></p>
                    <?php } ?>
                    <div>
                        <h3 class="mb-1 text-xs font-semibold tracking-wide uppercase text-slate-400">Blocks</h3>
                        <ol class="ltr:pl-5 rtl:pr-5 list-decimal space-y-0.5">
                            <?php foreach ($template['layout'] as $componentSlug) { ?>
                            <li>
                                <?php if (isset($components[$componentSlug])) { ?>
                                <a class="hover:text-custom-500" href="<?= e(lurl($basePath.'/components/'.$componentSlug.'/edit')) ?>"><?= e($components[$componentSlug]['label']) ?></a>
                                <?php } else { ?>
                                <span class="text-red-600">Missing: <?= e($componentSlug) ?></span>
                                <?php } ?>
                            </li>
                            <?php } ?>
                        </ol>
                    </div>
                    <div>
                        <h3 class="mb-1 text-xs font-semibold tracking-wide uppercase text-slate-400">Used by</h3>
                        <?php if ($usedBy === []) { ?>
                        <p class="text-slate-500">No emails yet.</p>
                        <?php } else { ?>
                        <div class="flex flex-wrap gap-1.5">
                            <?php foreach ($usedBy as $short) { ?>
                            <a href="<?= e(lurl($basePath.'/emails/'.$short.'/edit')) ?>" class="px-2 py-0.5 text-xs rounded bg-slate-100 hover:bg-slate-200 dark:bg-zink-600"><?= e($short) ?></a>
                            <?php } ?>
                        </div>
                        <?php } ?>
                    </div>
                    <a href="<?= e(lurl($basePath.'/'.$template['slug'].'/edit')) ?>" class="btn w-full bg-custom-500 border-custom-500 text-white hover:bg-custom-600 inline-flex items-center justify-center gap-2">
                        <i data-lucide="pencil" class="size-4"></i> Edit template
                    </a>
                </div>
            </div>
            <p class="text-xs text-slate-500 dark:text-zink-300">
                To see a real email built from this template, or send yourself a test, open it under
                <a class="underline" href="<?= e(lurl($basePath.'/emails')) ?>">Emails</a>.
            </p>
        </aside>
    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
(function () {
    var tabs = document.querySelectorAll('[role="tab"]');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (other) {
                var selected = other === tab;
                other.setAttribute('aria-selected', selected ? 'true' : 'false');
                document.getElementById(other.getAttribute('aria-controls')).hidden = !selected;
            });
        });
    });
})();
</script>
{% endblock %}
