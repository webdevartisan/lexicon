<?php
/*
 * Live preview for the editors in this section. The page's form must have
 * id="editor-form" and contain hidden preview_kind and preview_format inputs.
 *
 * Each change re-submits that form to the preview endpoint with the frame as
 * its target, so the draft is rendered by the real email code without being
 * saved. The frame is sandboxed and the response carries a sandbox CSP, so
 * markup in a draft never runs on the control panel's origin.
 */
$previewHeight = $previewHeight ?? 640;
?>
<div class="card mb-0 lg:sticky lg:top-24">
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

    window.emailPreview = { refresh: refreshSoon, now: refreshNow };
    refreshNow();
})();
</script>
