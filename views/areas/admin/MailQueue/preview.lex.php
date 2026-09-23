{% extends "back.lex.php" %}

{% block title %}Email #{{ entry.id }}{% endblock %}
{% block subtitle %}{{ entry.subject }}{% endblock %}

{% block body %}
<?php
$status = (string) $entry['status'];
$tier = (string) ($entry['tier'] ?? 'standard');
$tierBadges = ['critical' => ['pending', 'Critical'], 'standard' => ['draft', 'Standard'], 'bulk' => ['sending', 'Bulk']];
[$tierBadge, $tierLabel] = $tierBadges[$tier] ?? ['draft', ucfirst($tier)];
$basePath = '/admin/mail-queue';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    <div class="mb-4">
        <a href="<?= e(lurl($basePath)) ?>" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-custom-500 dark:text-zink-300">
            {% cache 'lucide:arrow-left:mq-preview' ttl=31536000 %}<i data-lucide="arrow-left" class="size-4"></i>{% endcache %}
            Back to mail queue
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Email Preview (Main Column) -->
        <div class="lg:col-span-2">
            <div class="card">
                <div class="card-body !p-0">
                    <div class="px-4 py-3 border-b border-slate-200 dark:border-zink-600">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Email Preview</h2>
                            <div class="flex items-center gap-2">
                                <button id="decrease-height" type="button" title="Decrease height"
                                        class="btn bg-white border-slate-300 text-slate-700 hover:bg-slate-50 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-100 !p-2">
                                    {% cache 'lucide:minimize-2' ttl=31536000 %}<i data-lucide="minimize-2" class="size-4"></i>{% endcache %}
                                </button>
                                <button id="increase-height" type="button" title="Increase height"
                                        class="btn bg-white border-slate-300 text-slate-700 hover:bg-slate-50 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-100 !p-2">
                                    {% cache 'lucide:maximize-2' ttl=31536000 %}<i data-lucide="maximize-2" class="size-4"></i>{% endcache %}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 dark:bg-zink-800 p-4">
                        <iframe id="email-preview-iframe"
                                src="<?= e(buildLocalizedUrl($basePath.'/'.(int) $entry['id'].'/preview/render')) ?>"
                                class="w-full border-0 rounded bg-white dark:bg-zink-900"
                                style="min-height: 600px; height: 800px;"
                                sandbox="allow-same-origin"
                                title="Email Preview"></iframe>
                    </div>
                </div>
            </div>

            <?php if ((string) ($entry['body_text'] ?? '') !== '') { ?>
            <div class="card mt-6">
                <div class="card-body">
                    <h3 class="mb-3 text-sm font-semibold text-slate-900 dark:text-zink-100">Plain Text Version</h3>
                    <div class="p-3 bg-slate-100 dark:bg-zink-600 rounded-lg overflow-x-auto">
                        <pre class="text-xs text-slate-700 dark:text-zink-200 whitespace-pre-wrap font-mono"><?= e((string) $entry['body_text']) ?></pre>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>

        <!-- Sidebar: Metadata & Actions -->
        <aside class="space-y-6">
            <div class="card">
                <div class="card-body">
                    <h3 class="mb-4 text-sm font-semibold text-slate-900 dark:text-zink-100">Email Details</h3>

                    <dl class="space-y-4 text-sm">
                        <div>
                            <dt class="font-medium text-slate-500 dark:text-zink-300">Status</dt>
                            <dd class="mt-1 flex items-center gap-2">
                                {% cmp="status-badge" status="{$status}" %}
                                {% cmp="status-badge" status="{$tierBadge}" label="{$tierLabel}" %}
                            </dd>
                        </div>

                        <div>
                            <dt class="font-medium text-slate-500 dark:text-zink-300">Subject</dt>
                            <dd class="mt-1 text-slate-900 dark:text-zink-100"><?= e((string) $entry['subject']) ?></dd>
                        </div>

                        <div>
                            <dt class="font-medium text-slate-500 dark:text-zink-300">To</dt>
                            <dd class="mt-1 text-slate-900 dark:text-zink-100">
                                <?= e((string) ($entry['to_name'] ?? '') ?: (string) $entry['to_email']) ?><br>
                                <span class="text-xs text-slate-500 dark:text-zink-400">&lt;<?= e((string) $entry['to_email']) ?>&gt;</span>
                            </dd>
                        </div>

                        <?php if ((string) ($entry['related_type'] ?? '') !== '') { ?>
                        <div>
                            <dt class="font-medium text-slate-500 dark:text-zink-300">Related to</dt>
                            <dd class="mt-1 text-slate-900 dark:text-zink-100">
                                <?= e((string) $entry['related_type']) ?> #<?= (int) $entry['related_id'] ?>
                            </dd>
                        </div>
                        <?php } ?>

                        <div>
                            <dt class="font-medium text-slate-500 dark:text-zink-300">Attempts</dt>
                            <dd class="mt-1 text-slate-900 dark:text-zink-100"><?= (int) $entry['attempts'] ?> / <?= (int) $entry['max_attempts'] ?></dd>
                        </div>

                        <?php if ($status === 'failed' && (string) ($entry['last_error'] ?? '') !== '') { ?>
                        <div>
                            <dt class="font-medium text-slate-500 dark:text-zink-300">Last error</dt>
                            <dd class="mt-1 text-xs text-red-700 dark:text-red-300 whitespace-normal break-words"><?= e((string) $entry['last_error']) ?></dd>
                        </div>
                        <?php } ?>

                        <div>
                            <dt class="font-medium text-slate-500 dark:text-zink-300">Queued</dt>
                            <dd class="mt-1 text-slate-900 dark:text-zink-100"><?= e(local_datetime($entry['created_at'] ?? null, 'M j, Y g:i a')) ?></dd>
                        </div>

                        <?php if ($status === 'sent') { ?>
                        <div>
                            <dt class="font-medium text-slate-500 dark:text-zink-300">Sent</dt>
                            <dd class="mt-1 text-slate-900 dark:text-zink-100"><?= e(local_datetime($entry['sent_at'] ?? null, 'M j, Y g:i a')) ?></dd>
                        </div>
                        <?php } ?>

                        <?php if ($status === 'cancelled' && (int) ($entry['cancelled_by'] ?? 0) > 0) { ?>
                        <div>
                            <dt class="font-medium text-slate-500 dark:text-zink-300">Cancelled</dt>
                            <dd class="mt-1 text-slate-900 dark:text-zink-100"><?= e(local_datetime($entry['cancelled_at'] ?? null, 'M j, Y g:i a')) ?></dd>
                        </div>
                        <?php } ?>

                        <?php if ((int) ($entry['resent_from_id'] ?? 0) > 0) { ?>
                        <div>
                            <dt class="font-medium text-slate-500 dark:text-zink-300">Resent from</dt>
                            <dd class="mt-1 text-slate-900 dark:text-zink-100">
                                <a href="<?= e(buildLocalizedUrl($basePath.'/'.(int) $entry['resent_from_id'].'/preview')) ?>" class="text-custom-500 hover:underline">
                                    Email #<?= (int) $entry['resent_from_id'] ?>
                                </a>
                            </dd>
                        </div>
                        <?php } ?>
                    </dl>
                </div>
            </div>

            <?php if ($status === 'failed' || $status === 'pending' || $status === 'sent') { ?>
            <div class="card">
                <div class="card-body space-y-2">
                    <h3 class="mb-2 text-sm font-semibold text-slate-900 dark:text-zink-100">Actions</h3>

                    <?php if ($status === 'failed') { ?>
                    <form method="POST" action="<?= e(buildLocalizedUrl($basePath.'/'.(int) $entry['id'].'/retry')) ?>">
                        {{ csrf_field() }}
                        {% cmp="btn" type="submit" variant="yellow" icon="refresh-cw" label="Retry" addClass="w-full" %}
                    </form>
                    <?php } ?>

                    <?php if ($status === 'pending') { ?>
                    <form method="POST" action="<?= e(buildLocalizedUrl($basePath.'/'.(int) $entry['id'].'/cancel')) ?>"
                          data-confirm="Cancel this email? It will not be sent.">
                        {{ csrf_field() }}
                        {% cmp="btn" type="submit" variant="slate" icon="ban" label="Cancel" addClass="w-full" %}
                    </form>
                    <?php } ?>

                    <?php if ($status === 'sent') { ?>
                    <form method="POST" action="<?= e(buildLocalizedUrl($basePath.'/'.(int) $entry['id'].'/resend')) ?>"
                          data-confirm="Send a fresh copy of this email?">
                        {{ csrf_field() }}
                        {% cmp="btn" type="submit" variant="blue" icon="send" label="Resend" addClass="w-full" %}
                    </form>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </aside>
    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
document.addEventListener('DOMContentLoaded', function () {
    const iframe = document.getElementById('email-preview-iframe');
    const increaseBtn = document.getElementById('increase-height');
    const decreaseBtn = document.getElementById('decrease-height');

    if (!iframe || !increaseBtn || !decreaseBtn) return;

    increaseBtn.addEventListener('click', function () {
        iframe.style.height = ((parseInt(iframe.style.height) || 800) + 200) + 'px';
    });

    decreaseBtn.addEventListener('click', function () {
        iframe.style.height = Math.max(400, (parseInt(iframe.style.height) || 800) - 200) + 'px';
    });
});
</script>
{% endblock %}
