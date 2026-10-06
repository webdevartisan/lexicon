{% extends "back.lex.php" %}

{% block title %}<?= $isNew ? 'New Email Block' : 'Edit Block: '.e($component['label']) ?>{% endblock %}
{% block subtitle %}HTML with placeholders. Inline styles are the safest choice: many email clients drop stylesheets.{% endblock %}

{% block body %}
<?php
$activeTab = 'components';
$basePath = '/admin/email-templates/components';
$formAction = lurl($isNew ? $basePath.'/store' : $basePath.'/'.$component['slug'].'/update');
$textMode = $component['text'] === null ? 'auto' : ($component['text'] === '' ? 'none' : 'custom');
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    {% include "areas/admin/EmailTemplate/_tabs.lex.php" %}

    <div class="grid gap-5 xl:grid-cols-12">
        <div class="xl:col-span-7 space-y-5">
            <form id="editor-form" method="POST" action="<?= e($formAction) ?>" class="space-y-5">
                {{ csrf_field() }}
                <input type="hidden" name="preview_kind" value="component">
                <input type="hidden" name="preview_format" value="html">
                <input type="hidden" name="original" value="<?= $isNew ? '' : e($component['slug']) ?>">

                <div class="card mb-0">
                    <div class="card-body grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="label" class="<?= e($labelClass) ?>">Name</label>
                            <input type="text" id="label" name="label" value="<?= e($component['label']) ?>" required maxlength="100" class="<?= e($fieldClass) ?>">
                        </div>
                        <div>
                            <label for="slug" class="<?= e($labelClass) ?>">Slug</label>
                            <?php if ($isNew) { ?>
                            <input type="text" id="slug" name="slug" value="<?= e($component['slug']) ?>" required pattern="[a-z][a-z0-9\-]*[a-z0-9]" maxlength="64" class="<?= e($fieldClass) ?> font-mono" aria-describedby="slug-hint">
                            <p id="slug-hint" class="<?= e($hintClass) ?>">Lowercase words and hyphens. Templates refer to it, so it is fixed once created.</p>
                            <?php } else { ?>
                            <div class="flex items-center gap-2 py-2"><code class="text-sm"><?= e($component['slug']) ?></code> <?= $sourceBadge($component['source']) ?></div>
                            <?php } ?>
                        </div>
                        <div>
                            <label for="category" class="<?= e($labelClass) ?>">Category</label>
                            <select id="category" name="category" class="<?= e($selectClass) ?>">
                                <?php foreach ($categories as $key => $label) { ?>
                                <option value="<?= e($key) ?>" <?= $component['category'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div>
                            <label for="description" class="<?= e($labelClass) ?>">Description</label>
                            <input type="text" id="description" name="description" value="<?= e($component['description']) ?>" maxlength="255" class="<?= e($fieldClass) ?>">
                        </div>
                    </div>
                </div>

                <div class="card mb-0">
                    <div class="card-body space-y-4">
                        <div>
                            <label for="html" class="<?= e($labelClass) ?>">HTML</label>
                            <textarea id="html" name="html" rows="10" spellcheck="false" required class="<?= e($codeClass) ?>" aria-describedby="html-hint"><?= e($component['html']) ?></textarea>
                            <p id="html-hint" class="<?= e($hintClass) ?>">
                                Write <code><?= e($ph('name')) ?></code> where a value goes: lowercase letters, numbers and underscores.
                                In an attribute, keep it in quotes: <code>href="<?= e($ph('button_url')) ?>"</code>.
                                Values arrive escaped, and a link must start with https:, http: or mailto:.
                                Scripts, forms and event handlers are refused.
                            </p>
                        </div>

                        <div>
                            <h2 class="<?= e($labelClass) ?>">Placeholders found</h2>
                            <div id="found-placeholders" class="flex flex-wrap gap-1.5" aria-live="polite"></div>
                            <p class="<?= e($hintClass) ?>">
                                <?= e($ph('app_name')) ?>, <?= e($ph('app_url')) ?> and <?= e($ph('year')) ?> are filled in for every email.
                                When every other placeholder in a block comes out empty, the block is left out of that email.
                            </p>
                        </div>

                        <fieldset>
                            <legend class="<?= e($labelClass) ?>">Plain-text version</legend>
                            <div class="flex flex-wrap gap-4 text-sm">
                                <?php foreach (['auto' => 'Worked out from the HTML', 'custom' => 'Written here', 'none' => 'Left out'] as $mode => $modeLabel) { ?>
                                <label class="inline-flex items-center gap-2">
                                    <input type="radio" name="text_mode" value="<?= e($mode) ?>" <?= $textMode === $mode ? 'checked' : '' ?> class="size-4 border-slate-300">
                                    <?= e($modeLabel) ?>
                                </label>
                                <?php } ?>
                            </div>
                            <div id="custom-text" class="mt-2 <?= $textMode === 'custom' ? '' : 'hidden' ?>">
                                <label for="text" class="sr-only">Plain text</label>
                                <textarea id="text" name="text" rows="3" spellcheck="false" class="<?= e($codeClass) ?>"><?= e((string) $component['text']) ?></textarea>
                                <p class="<?= e($hintClass) ?>">Plain text with the same placeholders, for email clients that do not show HTML.</p>
                            </div>
                        </fieldset>

                        <div>
                            <label for="css" class="<?= e($labelClass) ?>">CSS <span class="font-normal text-slate-400">(optional)</span></label>
                            <textarea id="css" name="css" rows="4" spellcheck="false" class="<?= e($codeClass) ?>" aria-describedby="css-hint"><?= e($component['css']) ?></textarea>
                            <p id="css-hint" class="<?= e($hintClass) ?>">Added to the email's head once, whenever the block is used. Gmail and Outlook ignore some of it, so prefer inline styles.</p>
                        </div>
                    </div>
                </div>

                <div class="card mb-0">
                    <div class="card-body">
                        <h2 class="mb-1 text-sm font-semibold text-slate-900 dark:text-zink-50">Sample values</h2>
                        <p class="mb-3 text-xs text-slate-500 dark:text-zink-300">Used by the previews here and in templates. Real emails use their own values.</p>
                        <div id="sample-fields" class="grid gap-3 md:grid-cols-2"></div>
                    </div>
                </div>

                <?php if (!$isNew) { ?>
                <p class="text-sm text-slate-500 dark:text-zink-300">
                    <?php if ($usedBy === []) { ?>
                    No template uses this block yet.
                    <?php } else { ?>
                    Used in <?= e(implode(', ', $usedBy)) ?>. Saving checks that every email built from those templates still works; if any would break, nothing is saved.
                    <?php } ?>
                </p>
                <?php } ?>

                <div class="flex items-center gap-2">
                    <button type="submit" class="btn bg-custom-500 border-custom-500 text-white hover:bg-custom-600 inline-flex items-center gap-2">
                        <i data-lucide="save" class="size-4"></i> <?= $isNew ? 'Create block' : 'Save block' ?>
                    </button>
                    <a href="<?= e(lurl($basePath)) ?>" class="btn bg-white border-slate-300 text-slate-600 hover:bg-slate-50 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-100">Cancel</a>
                </div>
            </form>

            <?php if (!$isNew && $component['source'] !== 'built-in') { ?>
            <div class="card mb-0 border-red-200 dark:border-red-500/30">
                <div class="card-body flex flex-wrap items-center gap-3">
                    <?php if ($component['source'] === 'customized') { ?>
                    <p class="grow text-sm text-slate-600 dark:text-zink-200">This is a built-in block with your changes. Resetting brings back the shipped version.</p>
                    <form method="POST" action="<?= e(lurl($basePath.'/'.$component['slug'].'/reset')) ?>" data-confirm="Reset this block to its built-in version? Your changes to it are lost.">
                        {{ csrf_field() }}
                        <button type="submit" class="btn bg-white border-amber-500 text-amber-600 hover:bg-amber-50 dark:bg-zink-700">Reset to default</button>
                    </form>
                    <?php } else { ?>
                    <p class="grow text-sm text-slate-600 dark:text-zink-200">Delete this block. Only possible when no template uses it.</p>
                    <form method="POST" action="<?= e(lurl($basePath.'/'.$component['slug'].'/delete')) ?>" data-confirm="Delete this block? This cannot be undone.">
                        {{ csrf_field() }}
                        <button type="submit" class="btn bg-white border-red-500 text-red-600 hover:bg-red-50 dark:bg-zink-700" <?= $usedBy !== [] ? 'disabled title="Templates still use it"' : '' ?>>Delete block</button>
                    </form>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>

        <div class="xl:col-span-5">
            <?php $previewHeight = 420; ?>
            {% include "areas/admin/EmailTemplate/_preview.lex.php" %}
        </div>
    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
(function () {
    var globals = <?= json_encode($globals) ?>;
    var samples = <?= json_encode((object) $component['preview_data'], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var html = document.getElementById('html');
    var text = document.getElementById('text');
    var found = document.getElementById('found-placeholders');
    var fields = document.getElementById('sample-fields');
    var customText = document.getElementById('custom-text');
    var fieldClass = <?= json_encode($fieldClass) ?>;
    var pattern = /\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/g;

    function token(name) {
        return '{' + '{ ' + name + ' }' + '}';
    }

    function names() {
        var list = [];
        var source = html.value + '\n' + (document.querySelector('[name="text_mode"]:checked').value === 'custom' ? text.value : '');
        var match;
        pattern.lastIndex = 0;
        while ((match = pattern.exec(source)) !== null) {
            if (list.indexOf(match[1]) === -1) { list.push(match[1]); }
        }
        return list;
    }

    // Keep what was typed in the sample fields while placeholders come and go.
    function remember() {
        fields.querySelectorAll('input[data-sample]').forEach(function (input) {
            samples[input.getAttribute('data-sample')] = input.value;
        });
    }

    function render() {
        remember();
        var list = names();
        var own = list.filter(function (name) { return globals.indexOf(name) === -1; });

        found.innerHTML = '';
        list.forEach(function (name) {
            var chip = document.createElement('code');
            var isGlobal = globals.indexOf(name) !== -1;
            chip.className = 'px-2 py-0.5 text-xs rounded ' + (isGlobal ? 'bg-slate-100 text-slate-500 dark:bg-zink-600' : 'bg-custom-50 text-custom-700 dark:bg-custom-500/10 dark:text-custom-300');
            chip.textContent = token(name);
            chip.title = isGlobal ? 'Filled in for every email' : 'Each email provides this';
            found.appendChild(chip);
        });
        if (!list.length) {
            found.textContent = 'None yet. A block without placeholders always shows the same thing.';
        }

        fields.innerHTML = '';
        own.forEach(function (name) {
            var wrap = document.createElement('div');
            var label = document.createElement('label');
            label.className = <?= json_encode($labelClass) ?> + ' font-mono';
            label.htmlFor = 'sample-' + name;
            label.textContent = token(name);
            var input = document.createElement('input');
            input.type = 'text';
            input.id = 'sample-' + name;
            input.name = 'preview[' + name + ']';
            input.setAttribute('data-sample', name);
            input.className = fieldClass;
            input.value = samples[name] || '';
            wrap.appendChild(label);
            wrap.appendChild(input);
            fields.appendChild(wrap);
        });
        if (!own.length) {
            fields.innerHTML = '<p class="text-sm text-slate-500">This block has nothing to fill in.</p>';
        }

        // The sample fields are part of what the preview posts.
        if (window.emailPreview) { window.emailPreview.refresh(); }
    }

    var timer = null;
    html.addEventListener('input', function () { window.clearTimeout(timer); timer = window.setTimeout(render, 300); });
    text.addEventListener('input', function () { window.clearTimeout(timer); timer = window.setTimeout(render, 300); });
    document.querySelectorAll('[name="text_mode"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            customText.classList.toggle('hidden', radio.value !== 'custom' || !radio.checked);
            render();
        });
    });

    render();
})();
</script>
{% endblock %}
