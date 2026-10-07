{% extends "back.lex.php" %}

{% block title %}<?= $isNew ? 'New layout' : 'Layout: '.e($layout['name']) ?>{% endblock %}
{% block subtitle %}A whole HTML document. Each email's words go where it says content, and why it was sent where it says footer_note.{% endblock %}

{% block body %}
<?php
$activeTab = 'layouts';
$base = '/admin/email-templates/layouts';
$action = $isNew ? $base.'/store' : $base.'/'.$layout['slug'].'/update';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    {% include "areas/admin/Email/_shared.lex.php" %}

    <a href="<?= e(lurl($base)) ?>" class="inline-flex items-center gap-1 mb-4 text-sm text-slate-500 hover:text-custom-500">
        <i data-lucide="arrow-left" class="size-4"></i> All layouts
    </a>

    <div class="grid gap-5 xl:grid-cols-12">
        <div class="xl:col-span-7 space-y-5">
            <form id="editor-form" method="POST" action="<?= e(lurl($action)) ?>" class="space-y-5">
                {{ csrf_field() }}
                <input type="hidden" name="original" value="<?= e($isNew ? '' : $layout['slug']) ?>">

                <div class="card mb-0">
                    <div class="card-body grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="name" class="<?= e($labelClass) ?>">Name</label>
                            <input type="text" id="name" name="name" value="<?= e($layout['name']) ?>" maxlength="100" required class="<?= e($fieldClass) ?>">
                        </div>
                        <div>
                            <label for="slug" class="<?= e($labelClass) ?>">Slug</label>
                            <?php if ($isNew) { ?>
                            <input type="text" id="slug" name="slug" value="<?= e($layout['slug']) ?>" maxlength="64" pattern="[a-z][a-z0-9-]*[a-z0-9]" required placeholder="newsletter" class="<?= e($fieldClass) ?>">
                            <p class="<?= e($hintClass) ?>">Lowercase letters, numbers and hyphens. It cannot be changed later.</p>
                            <?php } else { ?>
                            <p class="py-2 text-sm"><code><?= e($layout['slug']) ?></code> <?= $sourceBadge($layout['source']) ?></p>
                            <?php } ?>
                        </div>
                        <?php foreach (['primary_color' => 'Primary colour', 'background_color' => 'Background colour'] as $key => $label) { ?>
                        <div>
                            <label for="<?= e($key) ?>" class="<?= e($labelClass) ?>"><?= e($label) ?> <code class="font-normal text-xs text-slate-400"><?= e($ph($key)) ?></code></label>
                            <div class="flex gap-2">
                                <input type="color" value="<?= e($layout[$key]) ?>" data-color-for="<?= e($key) ?>" aria-label="<?= e($label) ?> picker" class="h-10 w-12 p-1 rounded border border-slate-200 dark:border-zink-500">
                                <input type="text" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($layout[$key]) ?>" maxlength="7" pattern="#[0-9A-Fa-f]{6}" required class="<?= e($fieldClass) ?> font-mono">
                            </div>
                        </div>
                        <?php } ?>
                        <div>
                            <label for="support_email" class="<?= e($labelClass) ?>">Support email <code class="font-normal text-xs text-slate-400"><?= e($ph('support_email')) ?></code></label>
                            <input type="email" id="support_email" name="support_email" value="<?= e($layout['support_email']) ?>" maxlength="255" class="<?= e($fieldClass) ?>">
                            <p class="<?= e($hintClass) ?>">Empty uses the address mail is sent from.</p>
                        </div>
                        <div>
                            <label for="company_address" class="<?= e($labelClass) ?>">Company address <code class="font-normal text-xs text-slate-400"><?= e($ph('company_address')) ?></code></label>
                            <input type="text" id="company_address" name="company_address" value="<?= e($layout['company_address']) ?>" maxlength="500" class="<?= e($fieldClass) ?>">
                        </div>
                    </div>
                </div>

                <div class="card mb-0">
                    <div class="card-body space-y-3">
                        <div class="xl:sticky xl:top-24 z-10 -mx-1 px-1 py-2 bg-white dark:bg-zink-700 border-b border-slate-100 dark:border-zink-600">
                            <h2 class="mb-1.5 text-xs font-semibold text-slate-700 dark:text-zink-100">Insert <span class="font-normal text-slate-400">· click to put it where the cursor is</span></h2>
                            <div class="flex flex-wrap gap-1.5">
                                <?php foreach ($placeholders as $name) { ?>
                                <button type="button" data-insert="<?= e($name) ?>"
                                        class="px-2 py-0.5 text-xs rounded border border-slate-200 bg-slate-50 hover:bg-custom-50 hover:border-custom-500 dark:bg-zink-600 dark:border-zink-500 dark:hover:bg-zink-500">
                                    <code class="text-custom-600 dark:text-custom-300"><?= e($ph($name)) ?></code>
                                </button>
                                <?php } ?>
                            </div>
                        </div>
                        <div>
                            <label for="html" class="<?= e($labelClass) ?>">HTML</label>
                            <textarea id="html" name="html" data-code rows="34" wrap="off" spellcheck="false" class="<?= e($codeClass) ?>"><?= e($layout['html']) ?></textarea>
                            <p class="<?= e($hintClass) ?>">
                                Use <code><?= e($ph('lang')) ?></code>, <code><?= e($ph('dir')) ?></code> and <code><?= e($ph('start')) ?></code> rather than fixed values, so right-to-left languages read correctly.
                                Scripts, forms and event handlers are refused; Outlook conditional comments and VML are fine.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" class="btn bg-custom-500 border-custom-500 text-white hover:bg-custom-600 inline-flex items-center gap-2">
                        <i data-lucide="save" class="size-4"></i> <?= $isNew ? 'Create layout' : 'Save' ?>
                    </button>
                    <a href="<?= e(lurl($base)) ?>" class="<?= e($buttonClass) ?>">Cancel</a>
                </div>
            </form>

            <?php if (!$isNew) { ?>
            <div class="card mb-0">
                <div class="card-body text-sm">
                    <h2 class="mb-2 font-semibold text-slate-900 dark:text-zink-50">Used by</h2>
                    <?php if ($usedBy === []) { ?>
                    <p class="text-slate-500 dark:text-zink-300">No email uses this layout yet. Choose it in an email's editor.</p>
                    <?php } else { ?>
                    <p class="leading-7">
                        <?php foreach ($usedBy as $short => $user) { ?>
                        <a href="<?= e(lurl('/admin/email-templates/'.$short)) ?>" class="inline-block mr-1 px-2 rounded bg-slate-100 hover:text-custom-500 dark:bg-zink-600"><?= e($user['name']) ?></a>
                        <?php } ?>
                    </p>
                    <?php } ?>
                </div>
            </div>

            <?php if ($isShipped && $isStored) { ?>
            <div class="card mb-0 border-amber-200 dark:border-amber-500/30">
                <div class="card-body flex flex-wrap items-center gap-3">
                    <p class="grow text-sm text-slate-600 dark:text-zink-200">Go back to the version of this layout that ships with the site.</p>
                    <form method="POST" action="<?= e(lurl($base.'/'.$layout['slug'].'/reset')) ?>" data-confirm="Reset this layout to the shipped version? Your changes to it are lost.">
                        {{ csrf_field() }}
                        <button type="submit" class="btn bg-white border-amber-500 text-amber-600 hover:bg-amber-50 dark:bg-zink-700">Reset to default</button>
                    </form>
                </div>
            </div>
            <?php } elseif (!$isShipped) { ?>
            <div class="card mb-0 border-red-200 dark:border-red-500/30">
                <div class="card-body flex flex-wrap items-center gap-3">
                    <p class="grow text-sm text-slate-600 dark:text-zink-200"><?= $usedBy === [] ? 'Delete this layout.' : 'Move the emails above to another layout before deleting this one.' ?></p>
                    <form method="POST" action="<?= e(lurl($base.'/'.$layout['slug'].'/delete')) ?>" data-confirm="Delete this layout?">
                        {{ csrf_field() }}
                        <button type="submit" <?= $usedBy === [] ? '' : 'disabled' ?> class="btn bg-white border-red-500 text-red-600 hover:bg-red-50 disabled:opacity-50 dark:bg-zink-700">Delete</button>
                    </form>
                </div>
            </div>
            <?php } ?>
            <?php } ?>
        </div>

        <div class="xl:col-span-5">
            <div class="card mb-0 xl:sticky xl:top-24">
                <div class="card-body !p-0">
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 border-b border-slate-200 dark:border-zink-500">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-50">Preview</h2>
                        <label class="flex items-center gap-2 text-xs text-slate-500 dark:text-zink-300">With
                            <select name="preview_email" form="editor-form" class="<?= e($selectClass) ?> py-1 text-xs">
                                <?php foreach ($emails as $short => $option) { ?>
                                <option value="<?= e($short) ?>" <?= $short === $previewEmail ? 'selected' : '' ?>><?= e($option['name']) ?></option>
                                <?php } ?>
                            </select>
                        </label>
                    </div>
                    <div class="p-3 bg-slate-100 dark:bg-zink-800">
                        <iframe name="email-preview-frame" id="email-preview-frame" title="Layout preview" sandbox
                                class="w-full bg-white border-0 rounded shadow-sm" style="height: 640px;"></iframe>
                    </div>
                    <p class="px-4 py-2 text-xs text-slate-500 dark:text-zink-300" aria-live="polite">
                        Updates as you type: the email chosen above, in English with sample values. Nothing is saved.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
(function () {
    var form = document.getElementById('editor-form');
    var previewUrl = <?= json_encode(lurl($base.'/preview')) ?>;
    var html = document.getElementById('html');
    var lastField = html;
    var timer = null;

    function refreshNow() {
        var action = form.getAttribute('action');

        form.setAttribute('action', previewUrl);
        form.setAttribute('target', 'email-preview-frame');
        HTMLFormElement.prototype.submit.call(form);

        form.setAttribute('action', action);
        form.removeAttribute('target');
    }

    function refreshSoon() {
        window.clearTimeout(timer);
        timer = window.setTimeout(refreshNow, 400);
    }

    form.addEventListener('input', refreshSoon);
    form.addEventListener('change', refreshSoon);
    document.querySelector('[name="preview_email"]').addEventListener('change', refreshNow);

    // The colour pickers and their text fields stay in step.
    document.querySelectorAll('[data-color-for]').forEach(function (picker) {
        var text = document.getElementById(picker.getAttribute('data-color-for'));
        picker.addEventListener('input', function () { text.value = picker.value.toUpperCase(); refreshSoon(); });
        text.addEventListener('input', function () {
            if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) { picker.value = text.value; }
        });
    });

    html.addEventListener('focus', function () { lastField = html; });

    document.querySelectorAll('[data-insert]').forEach(function (button) {
        button.addEventListener('click', function () {
            var text = '{' + '{ ' + button.getAttribute('data-insert') + ' }' + '}';
            var start = lastField.selectionStart;
            lastField.value = lastField.value.slice(0, start) + text + lastField.value.slice(lastField.selectionEnd);
            lastField.focus();
            lastField.setSelectionRange(start + text.length, start + text.length);
            lastField.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    // In the HTML field Tab indents instead of leaving the field.
    html.addEventListener('keydown', function (event) {
        if (event.key !== 'Tab' || event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) {
            return;
        }
        event.preventDefault();
        var start = html.selectionStart;
        html.value = html.value.slice(0, start) + '  ' + html.value.slice(html.selectionEnd);
        html.setSelectionRange(start + 2, start + 2);
        html.dispatchEvent(new Event('input', { bubbles: true }));
    });

    refreshNow();
})();
</script>
{% endblock %}
