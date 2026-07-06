{% extends "back.lex.php" %}

{% block title %}Email Templates{% endblock %}
{% block subtitle %}Preview and test every notification email the platform sends{% endblock %}

{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">

    <?php if (!empty($unregistered)) { ?>
    <!-- A Mailable exists in the codebase but is not registered, so it can't be previewed -->
    <div class="mb-5 px-4 py-3 rounded-md border border-amber-200 bg-amber-50 text-amber-800 dark:bg-amber-900/40 dark:border-amber-800 dark:text-amber-100 flex items-start gap-3">
        <i data-lucide="alert-triangle" class="size-4 mt-0.5 shrink-0"></i>
        <div class="text-sm">
            <p class="font-medium">Some email classes are not registered for preview:</p>
            <ul class="mt-1 list-disc list-inside">
                <?php foreach ($unregistered as $class) { ?>
                <li><code class="text-xs"><?= e($class) ?></code></li>
                <?php } ?>
            </ul>
            <p class="mt-1">Add them to <code class="text-xs">EmailTemplateRegistry</code> with sample data to make them testable here.</p>
        </div>
    </div>
    <?php } ?>

    <!-- Email templates, grouped by purpose -->
    <?php foreach ($groupedTemplates as $group => $templates) { ?>
    <div class="mb-6">
        <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-3"><?= e($group) ?>
            <span class="text-xs font-normal text-slate-400 dark:text-zink-300 ml-1"><?= count($templates) ?> template<?= count($templates) === 1 ? '' : 's' ?></span>
        </h2>
        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($templates as $key => $template) { ?>
            <div class="card hover:shadow-md transition-shadow mb-0">
                <div class="card-body">
                    <div class="flex items-start gap-3 mb-4">
                        <div class="flex items-center justify-center size-12 bg-custom-100 dark:bg-custom-500/20 rounded-lg shrink-0">
                            <i data-lucide="mail" class="size-6 text-custom-500"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-base font-semibold text-slate-900 dark:text-zink-100 truncate">
                                <?= e($template['name']) ?>
                            </h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-zink-300">
                                <?= e($template['description']) ?>
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <a
                            href="<?= e(lurl('/admin/email-test/preview?template='.urlencode($key))) ?>"
                            class="flex-1 btn bg-custom-500 text-white hover:bg-custom-600 border-custom-500 hover:border-custom-600 text-center"
                        >
                            <i data-lucide="eye" class="inline-block size-4 mr-1"></i>
                            Preview
                        </a>
                    </div>
                </div>

                <!-- Sample Data Accordion -->
                <div class="border-t border-slate-200 dark:border-zink-600">
                    <details class="group">
                        <summary class="px-4 py-3 cursor-pointer text-sm font-medium text-slate-600 dark:text-zink-300 hover:bg-slate-50 dark:hover:bg-zink-600 transition-colors">
                            <div class="flex items-center justify-between">
                                <span>Sample Data</span>
                                <i data-lucide="chevron-down" class="size-4 transition-transform group-open:rotate-180"></i>
                            </div>
                        </summary>
                        <div class="px-4 py-3 bg-slate-50 dark:bg-zink-800">
                            <pre class="p-3 bg-slate-100 dark:bg-zink-700 rounded text-xs overflow-x-auto text-slate-700 dark:text-zink-200"><?= e(json_encode($template['sample_data'], JSON_PRETTY_PRINT)) ?></pre>
                        </div>
                    </details>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
    <?php } ?>

    <!-- Mail configuration: kept separate from template previews on purpose -->
    <div class="card border-blue-200 dark:border-blue-800">
        <div class="card-body bg-blue-50 dark:bg-blue-900/10">
            <div class="flex items-start gap-3 mb-4">
                <div class="flex items-center justify-center size-10 bg-blue-100 dark:bg-blue-500/20 rounded-md shrink-0">
                    <i data-lucide="settings" class="size-5 text-blue-500"></i>
                </div>
                <div class="flex-1">
                    <h2 class="text-sm font-semibold text-blue-900 dark:text-blue-400">Mail Configuration</h2>
                    <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                        Current transport settings from the environment, and a plain test message to verify them.
                    </p>
                </div>
            </div>

            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-blue-700/70 dark:text-blue-300/70 mb-1">Sending</dt>
                    <dd class="text-blue-900 dark:text-blue-100"><?= $mailConfig['enabled'] ? 'Enabled' : 'Disabled (MAIL_ENABLED=false)' ?></dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-blue-700/70 dark:text-blue-300/70 mb-1">Driver</dt>
                    <dd class="text-blue-900 dark:text-blue-100"><?= e($mailConfig['driver']) ?></dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-blue-700/70 dark:text-blue-300/70 mb-1">Host</dt>
                    <dd class="text-blue-900 dark:text-blue-100"><?= e($mailConfig['host']) ?>:<?= e($mailConfig['port']) ?> (<?= e($mailConfig['encryption']) ?>)</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-blue-700/70 dark:text-blue-300/70 mb-1">From</dt>
                    <dd class="text-blue-900 dark:text-blue-100"><?= e($mailConfig['from_name']) ?> &lt;<?= e($mailConfig['from_address']) ?>&gt;</dd>
                </div>
            </dl>

            <form method="POST" action="<?= e(lurl('/admin/email-test/test-config')) ?>" class="flex gap-3">
                {{ csrf_field() }}
                <input
                    type="email"
                    name="recipient"
                    placeholder="test@example.com"
                    required
                    class="flex-1 form-input border-slate-300 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:focus:border-custom-500 dark:bg-zink-700 dark:text-zink-100"
                >
                <button
                    type="submit"
                    class="btn bg-blue-600 text-white hover:bg-blue-700 border-blue-600 hover:border-blue-700 dark:bg-blue-500 dark:border-blue-500"
                >
                    <i data-lucide="send" class="inline-block size-4 mr-1"></i>
                    Send Test
                </button>
            </form>
        </div>
    </div>

</div>

{% endblock %}
