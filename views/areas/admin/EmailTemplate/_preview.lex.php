<?php
/*
 * Live preview for the editors in this section. The page's form must have
 * id="editor-form" and contain hidden preview_kind and preview_format inputs.
 *
 * Each change re-submits that form to the preview endpoint with the frame as
 * its target, so the draft is rendered by the real email code without being
 * saved. The frame is sandboxed and the response carries a sandbox CSP, so
 * markup in a draft never runs on the control panel's origin.
 *
 * Set $previewTestUrl to also offer sending the draft as a test email, to the
 * address in $testRecipient by default. The draft is posted the same way as
 * for the preview, and the answer is shown under the frame.
 */
$previewHeight = $previewHeight ?? 640;
$previewTestUrl = $previewTestUrl ?? null;
?>
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
                    class="w-full bg-white border-0 rounded shadow-sm" style="height: <?= (int) $previewHeight ?>px;"></iframe>
        </div>
        <p class="px-4 py-2 text-xs text-slate-500 dark:text-zink-300" aria-live="polite" id="email-preview-status">
            Updates as you type, with sample values. Nothing is sent or saved.
        </p>
        <?php if ($previewTestUrl !== null) { ?>
        <div class="px-4 py-3 border-t border-slate-200 dark:border-zink-500">
            <label for="email-test-recipient" class="<?= e($labelClass) ?>">Send this version as a test</label>
            <div class="flex gap-2">
                <input type="email" id="email-test-recipient" value="<?= e($testRecipient ?? '') ?>" placeholder="you@example.com" autocomplete="email"
                       class="<?= e($fieldClass) ?> grow">
                <button type="button" id="email-test-send" class="btn bg-white border-slate-300 text-slate-700 hover:bg-slate-50 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-100 inline-flex items-center gap-2 shrink-0">
                    <i data-lucide="send" class="size-4"></i> Send test
                </button>
            </div>
            <p id="email-test-status" class="mt-1.5 text-xs text-slate-500 dark:text-zink-300" aria-live="polite">
                Sends what is in the form now, with sample values, even if it is not saved.
            </p>
        </div>
        <?php } ?>
    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
(function () {
    var form = document.getElementById('editor-form');
    var formatField = form ? form.querySelector('[name="preview_format"]') : null;
    var previewUrl = <?= json_encode(lurl('/admin/email-templates/preview')) ?>;
    var timer = null;

    if (!form || !formatField) {
        return;
    }

    // Point the editor form at the preview for one submission, then put it back.
    // The browser builds the request synchronously, so restoring straight away is safe.
    function refreshNow() {
        var action = form.getAttribute('action');
        var target = form.getAttribute('target');

        form.setAttribute('action', previewUrl);
        form.setAttribute('target', 'email-preview-frame');
        HTMLFormElement.prototype.submit.call(form);

        form.setAttribute('action', action);
        target === null ? form.removeAttribute('target') : form.setAttribute('target', target);
    }

    function refreshSoon() {
        window.clearTimeout(timer);
        timer = window.setTimeout(refreshNow, 350);
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

    // Test send: the same draft, posted in the background so the form keeps what was typed.
    var testUrl = <?= json_encode($previewTestUrl === null ? null : lurl($previewTestUrl)) ?>;
    var testButton = document.getElementById('email-test-send');
    var testStatus = document.getElementById('email-test-status');

    if (testUrl && testButton && testStatus) {
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
    }

    window.emailPreview = { refresh: refreshSoon, now: refreshNow };
    refreshNow();
})();
</script>
