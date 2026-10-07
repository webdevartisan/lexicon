{% extends "back.lex.php" %}

{% block title %}Email: <?= e($email['name']) ?>{% endblock %}
{% block subtitle %}<?= e($email['description']) ?>{% endblock %}

{% block body %}
<?php
/*
 * Never write two opening braces in a row in these views: the template
 * compiler treats them as its own variable tag, even inside PHP or script.
 * $ph() writes a placeholder for display instead.
 */
$ph = static fn (string $name): string => '{'.'{ '.$name.' }'.'}';
$base = '/admin/email-templates/'.$email['short'];
$fieldClass = 'form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200';
$labelClass = 'inline-block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-100';
$dataList = static function (array $values, callable $ph): string {
    $html = '';
    foreach ($values as $key => $value) {
        $html .= '<li class="py-1"><code class="text-custom-600 dark:text-custom-300">'.e($ph((string) $key)).'</code>'
            .'<span class="block text-xs truncate text-slate-500 dark:text-zink-300">'.e($value === '' ? '(empty in this sample)' : $value).'</span></li>';
    }

    return $html;
};
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    <a href="<?= e(lurl('/admin/email-templates')) ?>" class="inline-flex items-center gap-1 mb-4 text-sm text-slate-500 hover:text-custom-500">
        <i data-lucide="arrow-left" class="size-4"></i> All emails
    </a>

    <!-- Languages: one tab per language the site offers -->
    <nav class="flex flex-wrap gap-1 mb-5 border-b border-slate-200 dark:border-zink-500" aria-label="Languages">
        <?php foreach ($languages as $language) {
            $isActive = $language['code'] === $locale; ?>
            <?php if ($language['written']) { ?>
            <a href="<?= e(lurl($base.'?locale='.$language['code'])) ?>"
               class="inline-flex items-center gap-2 px-4 py-2 -mb-px text-sm font-medium border-b-2 <?= $isActive ? 'border-custom-500 text-custom-500' : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300 dark:text-zink-200' ?>"
               <?= $isActive ? 'aria-current="page"' : '' ?>>
                <?= e($language['name']) ?>
                <?php if ($language['source'] !== 'built-in') { ?><span class="px-1.5 text-xs rounded bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">edited</span><?php } ?>
            </a>
            <?php } else { ?>
            <span class="inline-flex items-center gap-2 px-4 py-2 -mb-px text-sm border-b-2 border-transparent text-slate-400 dark:text-zink-300"
                  title="Not written in this language yet; its readers get the site's default language, else English">
                <?= e($language['name']) ?> <span class="text-xs">(not written)</span>
            </span>
            <?php } ?>
        <?php } ?>
    </nav>

    <?php if ($problem !== null) { ?>
    <div class="flex items-start gap-3 px-4 py-3 mb-4 text-sm text-red-700 border border-red-200 rounded-md bg-red-50 dark:bg-red-500/10 dark:border-red-500/40 dark:text-red-300" role="alert">
        <i data-lucide="alert-circle" class="size-4 mt-0.5 shrink-0"></i>
        <div><p class="font-medium">As saved, this version cannot be built, so the shipped English version is sent instead.</p><p><?= e($problem) ?></p></div>
    </div>
    <?php } ?>

    <div class="grid gap-5 xl:grid-cols-12">
        <div class="xl:col-span-4 space-y-5">
            <div class="card mb-0">
                <div class="card-body text-sm">
                    <dl class="space-y-2">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-300">Layout</dt>
                            <dd class="text-slate-800 dark:text-zink-50"><?= e($layout['name']) ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-300">Class</dt>
                            <dd><code class="text-xs"><?= e($email['class']) ?></code></dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="card mb-0">
                <div class="card-body">
                    <h2 class="mb-1 text-sm font-semibold text-slate-900 dark:text-zink-50">This email's data</h2>
                    <p class="mb-2 text-xs text-slate-500 dark:text-zink-300">What its words can use. Values shown are samples.</p>
                    <ul class="text-sm divide-y divide-slate-100 dark:divide-zink-600"><?= $dataList($sample['data'], $ph) ?></ul>

                    <?php if ($sample['repeat'] !== []) { ?>
                    <h3 class="mt-4 mb-1 text-sm font-semibold text-slate-900 dark:text-zink-50">In the repeated section</h3>
                    <p class="mb-2 text-xs text-slate-500 dark:text-zink-300">Filled once per item, e.g. once per blog.</p>
                    <ul class="text-sm divide-y divide-slate-100 dark:divide-zink-600"><?= $dataList($sample['repeat'], $ph) ?></ul>
                    <?php } ?>

                    <h3 class="mt-4 mb-1 text-sm font-semibold text-slate-900 dark:text-zink-50">In every email</h3>
                    <p class="text-xs leading-6">
                        <?php foreach ($globals as $global) { ?>
                        <code class="mr-1 text-custom-600 dark:text-custom-300"><?= e($ph($global)) ?></code>
                        <?php } ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="xl:col-span-8">
            <div class="card mb-0">
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
                        <iframe id="email-preview-frame" title="Email preview" sandbox
                                src="<?= e(lurl($base.'/render?locale='.$locale)) ?>"
                                class="w-full bg-white border-0 rounded shadow-sm" style="height: 720px;"></iframe>
                    </div>
                    <div class="px-4 py-3 border-t border-slate-200 dark:border-zink-500">
                        <label for="email-test-recipient" class="<?= e($labelClass) ?>">Send this version as a test</label>
                        <div class="flex gap-2">
                            <input type="email" id="email-test-recipient" value="<?= e($testRecipient) ?>" placeholder="you@example.com" autocomplete="email"
                                   class="<?= e($fieldClass) ?> grow">
                            <button type="button" id="email-test-send" class="btn bg-white border-slate-300 text-slate-700 hover:bg-slate-50 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-100 inline-flex items-center gap-2 shrink-0">
                                <i data-lucide="send" class="size-4"></i> Send test
                            </button>
                        </div>
                        <p id="email-test-status" class="mt-1.5 text-xs text-slate-500 dark:text-zink-300" aria-live="polite">
                            Sends this language as saved, with sample values.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
(function () {
    var frame = document.getElementById('email-preview-frame');
    var renderUrl = <?= json_encode(lurl($base.'/render?locale='.$locale)) ?>;

    document.querySelectorAll('[data-preview-format]').forEach(function (button) {
        button.addEventListener('click', function () {
            frame.src = renderUrl + '&format=' + button.getAttribute('data-preview-format');
            document.querySelectorAll('[data-preview-format]').forEach(function (other) {
                other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
            });
        });
    });

    // Test send, in the background so the page stays as it is.
    var testUrl = <?= json_encode(lurl($base.'/send-test')) ?>;
    var testButton = document.getElementById('email-test-send');
    var testStatus = document.getElementById('email-test-status');

    testButton.addEventListener('click', function () {
        var body = new FormData();
        body.append('_token', <?= json_encode(csrf()->getToken()) ?>);
        body.append('locale', <?= json_encode($locale) ?>);
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
})();
</script>
{% endblock %}
