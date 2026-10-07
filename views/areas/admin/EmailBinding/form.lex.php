{% extends "back.lex.php" %}

{% block title %}Email: <?= e($email['name']) ?>{% endblock %}
{% block subtitle %}<?= e($email['description']) ?>{% endblock %}

{% block body %}
<?php
$activeTab = 'emails';
$basePath = '/admin/email-templates/emails';
$sendsCustom = !$isStored || $binding['is_active'];
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    {% include "areas/admin/EmailTemplate/_tabs.lex.php" %}

    <?php if ($problem !== null) { ?>
    <div class="flex items-start gap-3 px-4 py-3 mb-4 text-sm text-red-700 border border-red-200 rounded-md bg-red-50 dark:bg-red-500/10 dark:border-red-500/40 dark:text-red-300" role="alert">
        <i data-lucide="alert-circle" class="size-4 mt-0.5 shrink-0"></i>
        <div><p class="font-medium">As saved, this email cannot be built, so the built-in version is being sent.</p><p><?= e($problem) ?></p></div>
    </div>
    <?php } ?>

    <div class="grid gap-5 xl:grid-cols-12">
        <div class="xl:col-span-7 space-y-5">
            <form id="editor-form" method="POST" action="<?= e(lurl($basePath.'/'.$email['short'].'/update')) ?>" class="space-y-5">
                {{ csrf_field() }}
                <input type="hidden" name="preview_kind" value="email">
                <input type="hidden" name="preview_format" value="html">
                <input type="hidden" name="email" value="<?= e($email['short']) ?>">

                <div class="card mb-0">
                    <div class="card-body grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="template" class="<?= e($labelClass) ?>">Template</label>
                            <select id="template" name="template" class="<?= e($selectClass) ?>">
                                <?php foreach ($templates as $slug => $template) { ?>
                                <option value="<?= e($slug) ?>" <?= $binding['template'] === $slug ? 'selected' : '' ?>>
                                    <?= e($template['label']) ?><?= $slug === $builtInTemplate ? ' (built-in choice)' : '' ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label for="subject" class="<?= e($labelClass) ?>">Subject line <span class="font-normal text-slate-400">(optional)</span></label>
                            <input type="text" id="subject" name="subject" data-insertable value="<?= e((string) $binding['subject']) ?>" maxlength="255"
                                   placeholder="<?= e($data['subject'] ?? '') ?>" class="<?= e($fieldClass) ?>" aria-describedby="subject-hint">
                            <p id="subject-hint" class="<?= e($hintClass) ?>">
                                Leave empty to keep the subject the site writes (shown greyed out). Use <code><?= e($ph('subject')) ?></code> to add to it.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="card mb-0">
                    <div class="card-body space-y-4">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-50">Wording</h2>
                            <p class="text-xs text-slate-500 dark:text-zink-300">
                                What goes into each placeholder of the template. HTML is allowed, so use &lt;p&gt; for paragraphs and &lt;strong&gt; for bold.
                                Put this email's data in with the buttons below. Leave a field empty to leave its block out.
                            </p>
                        </div>
                        <!-- This email's data. Kept in this column: the preview beside it stays in view while scrolling and would cover anything under it. -->
                        <div class="xl:sticky xl:top-24 z-10 -mx-1 px-1 py-2 bg-white dark:bg-zink-700 border-b border-slate-100 dark:border-zink-600">
                            <h3 class="mb-1.5 text-xs font-semibold text-slate-700 dark:text-zink-100">This email's data <span class="font-normal text-slate-400">· click to put it where the cursor is; hover for the sample value</span></h3>
                            <div class="flex flex-wrap gap-1.5">
                                <?php foreach (array_merge(array_keys($data), $globals) as $key) {
                                    if (!is_string($key)) {
                                        continue;
                                    }
                                    $sample = in_array($key, $globals, true) ? 'Every email' : ($data[$key] === '' ? '(empty in this sample)' : $data[$key]); ?>
                                <button type="button" data-insert="<?= e($key) ?>" title="<?= e(mb_strimwidth($sample, 0, 160, '…')) ?>"
                                        class="px-2 py-1 text-xs rounded border border-slate-200 bg-slate-50 hover:bg-custom-50 hover:border-custom-500 dark:bg-zink-600 dark:border-zink-500 dark:hover:bg-zink-500">
                                    <code class="text-custom-600 dark:text-custom-300"><?= e($ph($key)) ?></code>
                                </button>
                                <?php } ?>
                            </div>
                        </div>
                        <?php foreach ($wording as $name => $value) { ?>
                        <div data-wording="<?= e($name) ?>">
                            <label for="mapping-<?= e($name) ?>" class="<?= e($labelClass) ?>">
                                <code><?= e($ph($name)) ?></code>
                                <span class="font-normal text-slate-400" data-feeds></span>
                            </label>
                            <textarea id="mapping-<?= e($name) ?>" name="mapping[<?= e($name) ?>]" data-insertable rows="<?= strlen($value) > 120 ? 4 : 2 ?>" spellcheck="true"
                                      class="<?= e($codeClass) ?>"><?= e($value) ?></textarea>
                        </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="card mb-0">
                    <div class="card-body">
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="is_active" value="1" <?= $sendsCustom ? 'checked' : '' ?> class="mt-1 size-4 rounded border-slate-300">
                            <span>
                                <span class="block text-sm font-medium text-slate-800 dark:text-zink-50">Send this version</span>
                                <span class="block text-xs text-slate-500 dark:text-zink-300">Untick to keep your changes saved while the built-in version is sent.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" class="btn bg-custom-500 border-custom-500 text-white hover:bg-custom-600 inline-flex items-center gap-2">
                        <i data-lucide="save" class="size-4"></i> Save
                    </button>
                    <a href="<?= e(lurl($basePath)) ?>" class="btn bg-white border-slate-300 text-slate-600 hover:bg-slate-50 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-100">Cancel</a>
                </div>
            </form>

            <?php if ($isStored) { ?>
            <div class="card mb-0 border-amber-200 dark:border-amber-500/30">
                <div class="card-body flex flex-wrap items-center gap-3">
                    <p class="grow text-sm text-slate-600 dark:text-zink-200">Go back to the built-in template and wording for this email.</p>
                    <form method="POST" action="<?= e(lurl($basePath.'/'.$email['short'].'/reset')) ?>" data-confirm="Reset this email to its built-in version? Your wording for it is lost.">
                        {{ csrf_field() }}
                        <button type="submit" class="btn bg-white border-amber-500 text-amber-600 hover:bg-amber-50 dark:bg-zink-700">Reset to default</button>
                    </form>
                </div>
            </div>
            <?php } ?>
        </div>

        <div class="xl:col-span-5 space-y-5">
            <?php $previewTestUrl = $basePath.'/'.$email['short'].'/send-test'; $previewHeight = 560; ?>
            {% include "areas/admin/EmailTemplate/_preview.lex.php" %}
        </div>
    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
(function () {
    var placeholders = <?= json_encode($templatePlaceholders, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var feeds = <?= json_encode((object) $feeds, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var templateSelect = document.getElementById('template');
    var lastField = null;

    // Only the selected template's fields are shown and posted; the others keep
    // what was typed in case the template is switched back.
    function showFields() {
        var wanted = placeholders[templateSelect.value] || [];
        var blocks = feeds[templateSelect.value] || {};

        document.querySelectorAll('[data-wording]').forEach(function (wrap) {
            var name = wrap.getAttribute('data-wording');
            var on = wanted.indexOf(name) !== -1;
            wrap.hidden = !on;
            wrap.querySelector('textarea').disabled = !on;
            wrap.querySelector('[data-feeds]').textContent = blocks[name] ? 'in the ' + blocks[name] + ' block' : '';
        });
    }

    document.querySelectorAll('[data-insertable]').forEach(function (field) {
        field.addEventListener('focus', function () { lastField = field; });
    });

    document.querySelectorAll('[data-insert]').forEach(function (button) {
        button.addEventListener('click', function () {
            var field = lastField || document.querySelector('[data-wording]:not([hidden]) textarea');
            if (!field) { return; }
            var text = '{' + '{ ' + button.getAttribute('data-insert') + ' }' + '}';
            var start = field.selectionStart === null ? field.value.length : field.selectionStart;
            var end = field.selectionEnd === null ? field.value.length : field.selectionEnd;
            field.value = field.value.slice(0, start) + text + field.value.slice(end);
            field.focus();
            field.setSelectionRange(start + text.length, start + text.length);
            field.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    templateSelect.addEventListener('change', showFields);
    showFields();
    if (window.emailPreview) { window.emailPreview.refresh(); }
})();
</script>
{% endblock %}
