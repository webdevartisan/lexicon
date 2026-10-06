{% extends "back.lex.php" %}

{% block title %}<?= $isNew ? 'New Email Template' : 'Edit Template: '.e($template['label']) ?>{% endblock %}
{% block subtitle %}Drag blocks into the layout, or use the buttons. The preview fills each block with its sample values.{% endblock %}

{% block body %}
<?php
$activeTab = 'templates';
$basePath = '/admin/email-templates';
$formAction = lurl($isNew ? $basePath.'/store' : $basePath.'/'.$template['slug'].'/update');

$paletteData = [];
foreach ($components as $componentSlug => $component) {
    $paletteData[$componentSlug] = [
        'label' => $component['label'],
        'category' => $component['category'],
        'placeholders' => $componentPlaceholders[$componentSlug] ?? [],
    ];
}

$byCategory = [];
foreach ($components as $componentSlug => $component) {
    $byCategory[$component['category']][$componentSlug] = $component;
}
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    {% include "areas/admin/EmailTemplate/_tabs.lex.php" %}

    <div class="grid gap-5 xl:grid-cols-12">
        <div class="xl:col-span-7 space-y-5">
            <form id="editor-form" method="POST" action="<?= e($formAction) ?>" class="space-y-5">
                {{ csrf_field() }}
                <input type="hidden" name="preview_kind" value="template">
                <input type="hidden" name="preview_format" value="html">
                <input type="hidden" name="original" value="<?= $isNew ? '' : e($template['slug']) ?>">

                <div class="card mb-0">
                    <div class="card-body grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="label" class="<?= e($labelClass) ?>">Name</label>
                            <input type="text" id="label" name="label" value="<?= e($template['label']) ?>" required maxlength="100" class="<?= e($fieldClass) ?>">
                        </div>
                        <div>
                            <label for="slug" class="<?= e($labelClass) ?>">Slug</label>
                            <?php if ($isNew) { ?>
                            <input type="text" id="slug" name="slug" value="<?= e($template['slug']) ?>" required pattern="[a-z][a-z0-9\-]*[a-z0-9]" maxlength="64" class="<?= e($fieldClass) ?> font-mono" aria-describedby="slug-hint">
                            <p id="slug-hint" class="<?= e($hintClass) ?>">Lowercase words and hyphens. Fixed once created.</p>
                            <?php } else { ?>
                            <div class="flex items-center gap-2 py-2"><code class="text-sm"><?= e($template['slug']) ?></code> <?= $sourceBadge($template['source']) ?></div>
                            <?php } ?>
                        </div>
                        <div>
                            <label for="category" class="<?= e($labelClass) ?>">Category</label>
                            <select id="category" name="category" class="<?= e($selectClass) ?>">
                                <?php foreach ($categories as $key => $label) { ?>
                                <option value="<?= e($key) ?>" <?= $template['category'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div>
                            <label for="description" class="<?= e($labelClass) ?>">Description</label>
                            <input type="text" id="description" name="description" value="<?= e($template['description']) ?>" maxlength="255" class="<?= e($fieldClass) ?>">
                        </div>
                    </div>
                </div>

                <div class="grid gap-5 md:grid-cols-5">
                    <!-- Palette -->
                    <div class="card mb-0 md:col-span-2">
                        <div class="card-body">
                            <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-zink-50">Blocks</h2>
                            <label for="palette-search" class="sr-only">Find a block</label>
                            <input type="search" id="palette-search" placeholder="Find a block…" class="<?= e($fieldClass) ?> mb-3">
                            <div class="space-y-4 overflow-y-auto max-h-[32rem] pr-1" id="palette">
                                <?php foreach ($componentCategories as $categoryKey => $categoryLabel) {
                                    if (empty($byCategory[$categoryKey])) {
                                        continue;
                                    } ?>
                                <div data-palette-group>
                                    <h3 class="mb-1.5 text-xs font-semibold tracking-wide uppercase text-slate-400"><?= e($categoryLabel) ?></h3>
                                    <ul class="space-y-1.5">
                                        <?php foreach ($byCategory[$categoryKey] as $componentSlug => $component) { ?>
                                        <li draggable="true" data-palette-item="<?= e($componentSlug) ?>"
                                            data-search="<?= e(strtolower($component['label'].' '.$componentSlug.' '.$component['description'])) ?>"
                                            class="flex items-center gap-2 px-2.5 py-2 text-sm border rounded-md cursor-grab bg-white border-slate-200 hover:border-custom-300 dark:bg-zink-700 dark:border-zink-500">
                                            <i data-lucide="grip-vertical" class="size-4 text-slate-300 shrink-0" aria-hidden="true"></i>
                                            <span class="grow min-w-0">
                                                <span class="block font-medium truncate text-slate-800 dark:text-zink-50"><?= e($component['label']) ?></span>
                                                <span class="block text-xs truncate text-slate-400" title="<?= e($component['description']) ?>"><?= e($component['description'] !== '' ? $component['description'] : $componentSlug) ?></span>
                                            </span>
                                            <button type="button" data-add="<?= e($componentSlug) ?>" class="px-2 py-1 text-xs font-medium rounded text-custom-500 hover:bg-custom-50 dark:hover:bg-custom-500/10">
                                                Add<span class="sr-only"> <?= e($component['label']) ?></span>
                                            </button>
                                        </li>
                                        <?php } ?>
                                    </ul>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <!-- Canvas -->
                    <div class="card mb-0 md:col-span-3">
                        <div class="card-body">
                            <div class="flex items-center justify-between mb-3">
                                <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-50">Layout</h2>
                                <span class="text-xs text-slate-400">Top to bottom, as the email reads</span>
                            </div>
                            <ol id="layout-canvas" class="space-y-2 min-h-[12rem] p-2 rounded-md border-2 border-dashed border-slate-200 dark:border-zink-500" aria-label="Template layout"></ol>
                            <p id="canvas-empty" class="hidden mt-2 text-sm text-center text-slate-500">Drag blocks here, or press Add.</p>
                            <p class="sr-only" aria-live="polite" id="canvas-status"></p>
                        </div>
                    </div>
                </div>

                <div class="card mb-0">
                    <div class="card-body space-y-4">
                        <div>
                            <h2 class="mb-1 text-sm font-semibold text-slate-900 dark:text-zink-50">What each email has to provide</h2>
                            <p class="mb-2 text-xs text-slate-500 dark:text-zink-300">
                                Every email using this template words each of these. A block whose placeholders are all left empty is dropped from that email.
                                <?= e($ph('app_name')) ?>, <?= e($ph('app_url')) ?> and <?= e($ph('year')) ?> are always filled in.
                            </p>
                            <div id="needed-placeholders" class="flex flex-wrap gap-1.5"></div>
                        </div>
                        <?php if (!$isNew) { ?>
                        <div>
                            <h2 class="mb-1 text-sm font-semibold text-slate-900 dark:text-zink-50">Used by</h2>
                            <?php if ($usedBy === []) { ?>
                            <p class="text-sm text-slate-500">No emails use this template yet. Choose it for an email under <a class="underline" href="<?= e(lurl('/admin/email-templates/emails')) ?>">Emails</a>.</p>
                            <?php } else { ?>
                            <p class="mb-2 text-xs text-slate-500 dark:text-zink-300">Saving checks each of these still builds. Anything that would break is listed and nothing is saved.</p>
                            <div class="flex flex-wrap gap-1.5">
                                <?php foreach ($usedBy as $short) { ?>
                                <a href="<?= e(lurl('/admin/email-templates/emails/'.$short.'/edit')) ?>" class="px-2 py-0.5 text-xs rounded bg-slate-100 hover:bg-slate-200 dark:bg-zink-600"><?= e($short) ?></a>
                                <?php } ?>
                            </div>
                            <?php } ?>
                        </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="btn bg-custom-500 border-custom-500 text-white hover:bg-custom-600 inline-flex items-center gap-2">
                        <i data-lucide="save" class="size-4"></i> <?= $isNew ? 'Create template' : 'Save template' ?>
                    </button>
                    <a href="<?= e(lurl($basePath)) ?>" class="btn bg-white border-slate-300 text-slate-600 hover:bg-slate-50 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-100">Cancel</a>
                    <?php if (!$isNew) { ?>
                    <a href="<?= e(lurl($basePath.'/'.$template['slug'].'/preview')) ?>" class="ml-auto text-sm text-custom-500 hover:underline">Full preview</a>
                    <?php } ?>
                </div>
            </form>

            <?php if (!$isNew && $template['source'] !== 'built-in') { ?>
            <div class="card mb-0 border-red-200 dark:border-red-500/30">
                <div class="card-body flex flex-wrap items-center gap-3">
                    <?php if ($template['source'] === 'customized') { ?>
                    <p class="grow text-sm text-slate-600 dark:text-zink-200">This is a built-in template with your changes. Resetting brings back the shipped version.</p>
                    <form method="POST" action="<?= e(lurl($basePath.'/'.$template['slug'].'/reset')) ?>" data-confirm="Reset this template to its built-in version? Your changes to it are lost.">
                        {{ csrf_field() }}
                        <button type="submit" class="btn bg-white border-amber-500 text-amber-600 hover:bg-amber-50 dark:bg-zink-700">Reset to default</button>
                    </form>
                    <?php } else { ?>
                    <p class="grow text-sm text-slate-600 dark:text-zink-200">Delete this template. Only possible when no email uses it.</p>
                    <form method="POST" action="<?= e(lurl($basePath.'/'.$template['slug'].'/delete')) ?>" data-confirm="Delete this template? This cannot be undone.">
                        {{ csrf_field() }}
                        <button type="submit" class="btn bg-white border-red-500 text-red-600 hover:bg-red-50 dark:bg-zink-700" <?= $usedBy !== [] ? 'disabled title="Emails still use it"' : '' ?>>Delete template</button>
                    </form>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>

        <div class="xl:col-span-5">
            {% include "areas/admin/EmailTemplate/_preview.lex.php" %}
        </div>
    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
(function () {
    var blocks = <?= json_encode($paletteData, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var initial = <?= json_encode(array_values($template['layout']), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var canvas = document.getElementById('layout-canvas');
    var emptyNote = document.getElementById('canvas-empty');
    var status = document.getElementById('canvas-status');
    var needed = document.getElementById('needed-placeholders');
    var dragging = null;

    function token(name) {
        return '{' + '{ ' + name + ' }' + '}';
    }

    function iconButton(label, icon, handler) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'p-1 rounded text-slate-400 hover:text-slate-700 hover:bg-slate-100 dark:hover:bg-zink-600';
        button.setAttribute('aria-label', label);
        button.title = label;
        button.innerHTML = '<i data-lucide="' + icon + '" class="size-4"></i>';
        button.addEventListener('click', handler);

        return button;
    }

    function makeItem(slug) {
        var block = blocks[slug] || { label: slug, placeholders: [] };
        var li = document.createElement('li');
        li.draggable = true;
        li.dataset.slug = slug;
        li.className = 'flex items-center gap-2 px-3 py-2 text-sm bg-white border rounded-md shadow-sm cursor-grab border-slate-200 dark:bg-zink-700 dark:border-zink-500';

        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'layout[]';
        input.value = slug;
        li.appendChild(input);

        var text = document.createElement('span');
        text.className = 'grow min-w-0';
        var name = document.createElement('span');
        name.className = 'block font-medium text-slate-800 dark:text-zink-50';
        name.textContent = block.label;
        var meta = document.createElement('span');
        meta.className = 'block text-xs truncate text-slate-400';
        meta.textContent = block.placeholders.length ? block.placeholders.map(token).join('  ') : 'No placeholders';
        text.appendChild(name);
        text.appendChild(meta);
        li.appendChild(text);

        li.appendChild(iconButton('Move ' + block.label + ' up', 'arrow-up', function () { move(li, -1); }));
        li.appendChild(iconButton('Move ' + block.label + ' down', 'arrow-down', function () { move(li, 1); }));
        li.appendChild(iconButton('Remove ' + block.label, 'x', function () {
            li.remove();
            changed(block.label + ' removed');
        }));

        li.addEventListener('dragstart', function (e) {
            dragging = li;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', slug);
            li.classList.add('opacity-50');
        });
        li.addEventListener('dragend', function () {
            li.classList.remove('opacity-50');
            dragging = null;
            clearMarker();
        });

        return li;
    }

    function move(li, direction) {
        var sibling = direction < 0 ? li.previousElementSibling : li.nextElementSibling;
        if (!sibling) {
            return;
        }
        direction < 0 ? canvas.insertBefore(li, sibling) : canvas.insertBefore(sibling, li);
        li.querySelector('button[aria-label^="Move"]' + (direction < 0 ? '' : ':nth-of-type(2)')).focus();
        changed(blocks[li.dataset.slug].label + ' moved ' + (direction < 0 ? 'up' : 'down'));
    }

    function changed(message) {
        emptyNote.classList.toggle('hidden', canvas.children.length > 0);
        status.textContent = message || '';
        renderNeeded();
        if (window.lucide) { window.lucide.createIcons(); }
        if (window.emailPreview) { window.emailPreview.refresh(); }
    }

    function renderNeeded() {
        var names = [];
        canvas.querySelectorAll('li').forEach(function (li) {
            (blocks[li.dataset.slug] || { placeholders: [] }).placeholders.forEach(function (name) {
                if (names.indexOf(name) === -1) { names.push(name); }
            });
        });

        needed.innerHTML = '';
        if (!names.length) {
            needed.textContent = 'Nothing: this layout has no placeholders.';
            return;
        }
        names.forEach(function (name) {
            var chip = document.createElement('code');
            chip.className = 'px-2 py-0.5 text-xs rounded bg-custom-50 text-custom-700 dark:bg-custom-500/10 dark:text-custom-300';
            chip.textContent = token(name);
            needed.appendChild(chip);
        });
    }

    // Where a drop at clientY would land: before the first item whose middle is below it.
    function itemAfter(y) {
        var items = Array.prototype.filter.call(canvas.children, function (li) { return li !== dragging; });
        for (var i = 0; i < items.length; i++) {
            var box = items[i].getBoundingClientRect();
            if (y < box.top + box.height / 2) { return items[i]; }
        }
        return null;
    }

    function clearMarker() {
        canvas.querySelectorAll('li').forEach(function (li) { li.classList.remove('border-t-4', 'border-t-custom-500'); });
        canvas.classList.remove('border-custom-500');
    }

    canvas.addEventListener('dragover', function (e) {
        e.preventDefault();
        clearMarker();
        var after = itemAfter(e.clientY);
        if (after) { after.classList.add('border-t-4', 'border-t-custom-500'); } else { canvas.classList.add('border-custom-500'); }
    });
    canvas.addEventListener('dragleave', function (e) {
        if (!canvas.contains(e.relatedTarget)) { clearMarker(); }
    });
    canvas.addEventListener('drop', function (e) {
        e.preventDefault();
        var after = itemAfter(e.clientY);
        var item = dragging && dragging.parentNode === canvas ? dragging : makeItem(e.dataTransfer.getData('text/plain'));
        if (!blocks[item.dataset.slug]) { clearMarker(); return; }
        canvas.insertBefore(item, after);
        clearMarker();
        changed(blocks[item.dataset.slug].label + ' placed');
    });

    document.querySelectorAll('[data-palette-item]').forEach(function (entry) {
        entry.addEventListener('dragstart', function (e) {
            dragging = entry;
            e.dataTransfer.effectAllowed = 'copy';
            e.dataTransfer.setData('text/plain', entry.getAttribute('data-palette-item'));
        });
        entry.addEventListener('dragend', function () { dragging = null; clearMarker(); });
    });

    document.querySelectorAll('[data-add]').forEach(function (button) {
        button.addEventListener('click', function () {
            var slug = button.getAttribute('data-add');
            canvas.appendChild(makeItem(slug));
            changed(blocks[slug].label + ' added at the end');
        });
    });

    var paletteSearch = document.getElementById('palette-search');
    paletteSearch.addEventListener('input', function () {
        var term = paletteSearch.value.trim().toLowerCase();
        document.querySelectorAll('[data-palette-item]').forEach(function (entry) {
            entry.hidden = term !== '' && entry.getAttribute('data-search').indexOf(term) === -1;
        });
        document.querySelectorAll('[data-palette-group]').forEach(function (group) {
            group.hidden = group.querySelector('[data-palette-item]:not([hidden])') === null;
        });
    });

    initial.forEach(function (slug) { canvas.appendChild(makeItem(slug)); });
    changed('');
})();
</script>
{% endblock %}
