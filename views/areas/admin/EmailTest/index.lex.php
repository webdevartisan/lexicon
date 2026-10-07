{% extends "back.lex.php" %}

{% block title %}Email Delivery{% endblock %}
{% block subtitle %}Whether the site can send email: the mail server settings and a connection test{% endblock %}

{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">

    <p class="mb-5 text-sm text-slate-500 dark:text-zink-300">
        To see what a particular email looks like or send yourself a test of it, open that email under
        <a href="<?= e(lurl('/admin/email-templates/emails')) ?>" class="text-custom-500 hover:underline">Email Templates &rsaquo; Emails</a>.
    </p>

    <!-- Mail configuration -->
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
