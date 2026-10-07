{% extends "back.lex.php" %}

{% block title %}Email: <?= e($email['name']) ?>{% endblock %}
{% block subtitle %}<?= e($email['description']) ?>{% endblock %}

{% block body %}
<?php
$activeTab = 'emails';
$base = '/admin/email-templates/'.$email['short'];
$isEnglish = $locale === 'en';
$textDir = $rtl ? 'rtl' : 'ltr';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    {% include "areas/admin/Email/_shared.lex.php" %}

    <a href="<?= e(lurl('/admin/email-templates')) ?>" class="inline-flex items-center gap-1 mb-3 text-sm text-slate-500 hover:text-custom-500">
        <i data-lucide="arrow-left" class="size-4"></i> All emails
    </a>

    <!-- Languages: one tab per language the site offers -->
    <nav class="flex flex-wrap gap-1 mb-5 border-b border-slate-200 dark:border-zink-500" aria-label="Languages">
        <?php foreach ($tabs as $tab) {
            $isActive = $tab['code'] === $locale;
            $language = $tab['language']; ?>
        <a href="<?= e(lurl($base.'?locale='.$tab['code'])) ?>"
           class="inline-flex items-center gap-2 px-4 py-2 -mb-px text-sm font-medium border-b-2 <?= $isActive ? 'border-custom-500 text-custom-500' : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300 dark:text-zink-200' ?>"
           <?= $isActive ? 'aria-current="page"' : '' ?>>
            <?= e($tab['name']) ?>
            <?php if ($language === null) { ?>
            <span class="text-xs font-normal text-slate-400 dark:text-zink-300">not written</span>
            <?php } elseif ($language['outdated']) { ?>
            <span class="px-1.5 text-xs rounded bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300" title="The English was changed after this was saved">check</span>
            <?php } elseif ($language['source'] !== 'built-in') { ?>
            <span class="px-1.5 text-xs rounded bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300"><?= $tab['code'] === 'en' ? 'edited' : 'written' ?></span>
            <?php } ?>
        </a>
        <?php } ?>
    </nav>

    <?php if ($problem !== null) { ?>
    <div class="flex items-start gap-3 px-4 py-3 mb-4 text-sm text-red-700 border border-red-200 rounded-md bg-red-50 dark:bg-red-500/10 dark:border-red-500/40 dark:text-red-300" role="alert">
        <i data-lucide="alert-circle" class="size-4 mt-0.5 shrink-0"></i>
        <div><p class="font-medium">As saved, this version cannot be built, so the shipped English is sent instead.</p><p><?= e($problem) ?></p></div>
    </div>
    <?php } ?>

    <?php if (!$written) { ?>
    <div class="flex flex-wrap items-center gap-3 px-4 py-3 mb-4 text-sm border rounded-md text-sky-800 border-sky-200 bg-sky-50 dark:bg-sky-500/10 dark:border-sky-500/30 dark:text-sky-200">
        <i data-lucide="languages" class="size-4 shrink-0"></i>
        <p class="grow">This email is not written in <?= e($localeName) ?> yet. Until it is, its readers get it in the site's default language, else English.</p>
        <?php if ($english !== null) { ?>
        <button type="button" data-start-from-english class="<?= e($buttonClass) ?> py-1">Start from English</button>
        <?php } ?>
    </div>
    <?php } elseif ($outdated) { ?>
    <div class="flex items-start gap-3 px-4 py-3 mb-4 text-sm text-orange-800 border border-orange-200 rounded-md bg-orange-50 dark:bg-orange-500/10 dark:border-orange-500/30 dark:text-orange-200">
        <i data-lucide="alert-triangle" class="size-4 mt-0.5 shrink-0"></i>
        <p>The English version was changed after this one was saved. Check it still says the same, then save it to clear this note.</p>
    </div>
    <?php } ?>

    <div class="grid gap-5 xl:grid-cols-12">
        <div class="xl:col-span-7 space-y-5">
            <form id="editor-form" method="POST" action="<?= e(lurl($base.'/save')) ?>" class="space-y-5">
                {{ csrf_field() }}
                <input type="hidden" name="locale" value="<?= e($locale) ?>">
                <input type="hidden" name="preview_format" value="html">

                <div class="card mb-0">
                    <div class="card-body grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="layout" class="<?= e($labelClass) ?>">Layout</label>
                            <select id="layout" name="layout" class="<?= e($selectClass) ?>">
                                <?php foreach ($layouts as $slug => $layout) { ?>
                                <option value="<?= e($slug) ?>" <?= $fields['layout'] === $slug ? 'selected' : '' ?>><?= e($layout['name']) ?></option>
                                <?php } ?>
                            </select>
                            <p class="<?= e($hintClass) ?>">The same in every language. <a href="<?= e(lurl('/admin/email-templates/layouts')) ?>" class="text-custom-500 hover:underline">Edit layouts</a></p>
                        </div>
                        <div class="md:col-span-2">
                            <label for="subject" class="<?= e($labelClass) ?>">Subject</label>
                            <input type="text" id="subject" name="subject" data-insertable dir="<?= e($textDir) ?>" value="<?= e($fields['subject']) ?>" maxlength="255" required class="<?= e($fieldClass) ?>">
                        </div>
                        <div class="md:col-span-2">
                            <label for="preheader" class="<?= e($labelClass) ?>">Preheader <span class="font-normal text-slate-400">(optional)</span></label>
                            <input type="text" id="preheader" name="preheader" data-insertable dir="<?= e($textDir) ?>" value="<?= e($fields['preheader']) ?>" maxlength="255" class="<?= e($fieldClass) ?>">
                            <p class="<?= e($hintClass) ?>">The line inboxes show after the subject. It is hidden in the email itself.</p>
                        </div>
                    </div>
                </div>

                <div class="card mb-0">
                    <div class="card-body space-y-4">
                        <!-- What the words can use. Kept in this column: the preview beside it stays in view while scrolling and would cover anything under it. -->
                        <div class="xl:sticky xl:top-24 z-10 -mx-1 px-1 py-2 bg-white dark:bg-zink-700 border-b border-slate-100 dark:border-zink-600">
                            <h2 class="mb-1.5 text-xs font-semibold text-slate-700 dark:text-zink-100">Insert <span class="font-normal text-slate-400">· click to put it where the cursor is; hover for the sample value</span></h2>
                            <?php
                            $chipGroups = ["This email's data" => $sample['data']];
                            if ($sample['repeat'] !== []) {
                                $chipGroups['Repeated section only'] = $sample['repeat'];
                            }
                            $chipGroups['Every email'] = array_fill_keys($globals, '');
                            foreach ($chipGroups as $groupLabel => $values) { ?>
                            <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                <span class="w-full text-[11px] uppercase tracking-wide text-slate-400 dark:text-zink-300"><?= e($groupLabel) ?></span>
                                <?php foreach ($values as $key => $value) { ?>
                                <button type="button" data-insert="<?= e((string) $key) ?>" title="<?= e($value === '' ? 'Filled in for every email' : mb_strimwidth($value, 0, 160, '…')) ?>"
                                        class="px-2 py-0.5 text-xs rounded border border-slate-200 bg-slate-50 hover:bg-custom-50 hover:border-custom-500 dark:bg-zink-600 dark:border-zink-500 dark:hover:bg-zink-500">
                                    <code class="text-custom-600 dark:text-custom-300"><?= e($ph((string) $key)) ?></code>
                                </button>
                                <?php } ?>
                            </div>
                            <?php } ?>
                        </div>

                        <div>
                            <label for="body" class="<?= e($labelClass) ?>">Body <span class="font-normal text-slate-400">· HTML table rows, placed in the layout at <?= e($ph('content')) ?></span></label>
                            <textarea id="body" name="body" data-insertable data-code rows="26" wrap="off" spellcheck="false" class="<?= e($codeClass) ?>"><?= e($fields['body']) ?></textarea>
                        </div>

                        <?php if ($hasRepeat) { ?>
                        <div>
                            <label for="repeat" class="<?= e($labelClass) ?>">Repeated section <span class="font-normal text-slate-400">· filled once per item and placed in the body where its data says</span></label>
                            <textarea id="repeat" name="repeat" data-insertable data-code rows="16" wrap="off" spellcheck="false" class="<?= e($codeClass) ?>"><?= e((string) $fields['repeat']) ?></textarea>
                        </div>
                        <?php } ?>

                        <div>
                            <label for="footer_note" class="<?= e($labelClass) ?>">Footer note</label>
                            <textarea id="footer_note" name="footer_note" data-insertable rows="3" dir="<?= e($textDir) ?>" class="<?= e($fieldClass) ?>"><?= e($fields['footer_note']) ?></textarea>
                            <p class="<?= e($hintClass) ?>">Why the reader got this email. Shown in the layout's footer; HTML such as a link is allowed.</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" class="btn bg-custom-500 border-custom-500 text-white hover:bg-custom-600 inline-flex items-center gap-2">
                        <i data-lucide="save" class="size-4"></i> Save <?= e($localeName) ?>
                    </button>
                    <?php if (!$isEnglish && $english !== null && $written) { ?>
                    <button type="button" data-start-from-english class="<?= e($buttonClass) ?>">Replace with the English</button>
                    <?php } ?>
                    <a href="<?= e(lurl('/admin/email-templates')) ?>" class="<?= e($buttonClass) ?>">Cancel</a>
                </div>
            </form>

            <?php if ($stored) { ?>
            <div class="card mb-0 border-amber-200 dark:border-amber-500/30">
                <div class="card-body flex flex-wrap items-center gap-3">
                    <?php if ($isEnglish) { ?>
                    <p class="grow text-sm text-slate-600 dark:text-zink-200">Go back to the English this email ships with.</p>
                    <form method="POST" action="<?= e(lurl($base.'/reset')) ?>" data-confirm="Reset the English of this email to the shipped version? Your changes to it are lost.">
                        {{ csrf_field() }}
                        <input type="hidden" name="locale" value="en">
                        <button type="submit" class="btn bg-white border-amber-500 text-amber-600 hover:bg-amber-50 dark:bg-zink-700">Reset to default</button>
                    </form>
                    <?php } else { ?>
                    <p class="grow text-sm text-slate-600 dark:text-zink-200">Delete the <?= e($localeName) ?> version. Its readers get the site's default language, else English.</p>
                    <form method="POST" action="<?= e(lurl($base.'/reset')) ?>" data-confirm="Delete the <?= e($localeName) ?> version of this email?">
                        {{ csrf_field() }}
                        <input type="hidden" name="locale" value="<?= e($locale) ?>">
                        <button type="submit" class="btn bg-white border-amber-500 text-amber-600 hover:bg-amber-50 dark:bg-zink-700">Delete this translation</button>
                    </form>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>

        <div class="xl:col-span-5">
            <div class="card mb-0 xl:sticky xl:top-24">
                <div class="card-body !p-0">
                    <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-slate-200 dark:border-zink-500">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-50">Preview</h2>
                        <div class="inline-flex overflow-hidden text-xs border rounded-md border-slate-200 dark:border-zink-500" role="group" aria-label="Preview format">
                            <button type="button" data-preview-format="html" aria-pressed="true"
                                    class="px-3 py-1.5 font-medium aria-pressed:bg-custom-500 aria-pressed:text-white text-slate-600 dark:text-zink-200">Email</button>
                            <button type="button" data-preview-format="text" aria-pressed="false"
                                    class="px-3 py-1.5 font-medium aria-pressed:bg-custom-500 aria-pressed:text-white text-slate-600 dark:text-zink-200 border-l border-slate-200 dark:border-zink-500">Plain text</button>
                        </div>
                    </div>
                    <div class="p-3 bg-slate-100 dark:bg-zink-800">
                        <iframe name="email-preview-frame" id="email-preview-frame" title="Email preview" sandbox
                                class="w-full bg-white border-0 rounded shadow-sm" style="height: 560px;"></iframe>
                    </div>
                    <p class="px-4 py-2 text-xs text-slate-500 dark:text-zink-300" aria-live="polite">
                        Updates as you type, with sample values. Nothing is sent or saved.
                    </p>
                    <div class="px-4 py-3 border-t border-slate-200 dark:border-zink-500">
                        <label for="email-test-recipient" class="<?= e($labelClass) ?>">Send this version as a test</label>
                        <div class="flex gap-2">
                            <input type="email" id="email-test-recipient" value="<?= e($testRecipient) ?>" placeholder="you@example.com" autocomplete="email" class="<?= e($fieldClass) ?> grow">
                            <button type="button" id="email-test-send" class="<?= e($buttonClass) ?> inline-flex items-center gap-2 shrink-0">
                                <i data-lucide="send" class="size-4"></i> Send test
                            </button>
                        </div>
                        <p id="email-test-status" class="mt-1.5 text-xs text-slate-500 dark:text-zink-300" aria-live="polite">
                            Sends what is in the form now, with sample values, even if it is not saved.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
(function () {
    var form = document.getElementById('editor-form');
    var formatField = form.querySelector('[name="preview_format"]');
    var previewUrl = <?= json_encode(lurl($base.'/preview')) ?>;
    var testUrl = <?= json_encode(lurl($base.'/send-test')) ?>;
    var english = <?= json_encode($english, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var lastField = null;
    var timer = null;

    // Point the form at the preview for one submission, then put it back.
    // The browser builds the request synchronously, so restoring straight away is safe.
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

    document.querySelectorAll('[data-preview-format]').forEach(function (button) {
        button.addEventListener('click', function () {
            formatField.value = button.getAttribute('data-preview-format');
            document.querySelectorAll('[data-preview-format]').forEach(function (other) {
                other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
            });
            refreshNow();
        });
    });

    // Insert a placeholder where the cursor was.
    document.querySelectorAll('[data-insertable]').forEach(function (field) {
        field.addEventListener('focus', function () { lastField = field; });
    });

    document.querySelectorAll('[data-insert]').forEach(function (button) {
        button.addEventListener('click', function () {
            var field = lastField || document.getElementById('body');
            var text = '{' + '{ ' + button.getAttribute('data-insert') + ' }' + '}';
            var start = field.selectionStart === null ? field.value.length : field.selectionStart;
            var end = field.selectionEnd === null ? field.value.length : field.selectionEnd;
            field.value = field.value.slice(0, start) + text + field.value.slice(end);
            field.focus();
            field.setSelectionRange(start + text.length, start + text.length);
            field.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    // In the code fields Tab indents instead of leaving the field.
    document.querySelectorAll('[data-code]').forEach(function (field) {
        field.addEventListener('keydown', function (event) {
            if (event.key !== 'Tab' || event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) {
                return;
            }
            event.preventDefault();
            var start = field.selectionStart;
            field.value = field.value.slice(0, start) + '  ' + field.value.slice(field.selectionEnd);
            field.setSelectionRange(start + 2, start + 2);
            field.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    // Start a translation from the English, keeping its markup to translate the words in.
    document.querySelectorAll('[data-start-from-english]').forEach(function (button) {
        button.addEventListener('click', function () {
            var filled = ['subject', 'body', 'footer_note'].some(function (name) {
                var field = form.querySelector('[name="' + name + '"]');
                return field && field.value.trim() !== '';
            });
            if (!english || (filled && !window.confirm('Replace what is in the form with the English?'))) {
                return;
            }
            Object.keys(english).forEach(function (name) {
                var field = form.querySelector('[name="' + name + '"]');
                if (field) { field.value = english[name]; }
            });
            refreshNow();
        });
    });

    // Test send: the same draft, posted in the background so the form keeps what was typed.
    var testButton = document.getElementById('email-test-send');
    var testStatus = document.getElementById('email-test-status');

    testButton.addEventListener('click', function () {
        var body = new FormData(form);
        body.append('test_recipient', document.getElementById('email-test-recipient').value);

        testButton.disabled = true;
        testStatus.className = 'mt-1.5 text-xs text-slate-500 dark:text-zink-300';
        testStatus.textContent = 'Sending…';

        fetch(testUrl, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (response) {
                return response.json().catch(function () { return { ok: false, message: 'The test could not be sent.' }; });
            })
            .then(function (result) {
                testStatus.className = 'mt-1.5 text-xs ' + (result.ok ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400');
                testStatus.textContent = result.message || '';
            })
            .catch(function () {
                testStatus.className = 'mt-1.5 text-xs text-red-600 dark:text-red-400';
                testStatus.textContent = 'The test could not be sent. Check your connection and try again.';
            })
            .then(function () { testButton.disabled = false; });
    });

    refreshNow();
})();
</script>
{% endblock %}
