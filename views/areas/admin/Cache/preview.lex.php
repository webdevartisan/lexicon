{% extends "back.lex.php" %}

{% block title %}Cache preview{% endblock %}
{% block subtitle %}The first part of what is stored{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    <div class="card">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <code class="text-xs font-mono break-all"><?= e($title) ?></code>
                <a href="<?= buildLocalizedUrl($backUrl) ?>" class="text-sm text-custom-500 hover:underline">Back to the list</a>
            </div>
            <?php if ($content === null) { ?>
                <p class="text-sm text-slate-500 dark:text-zink-300">This entry has expired or no longer exists.</p>
            <?php } else { ?>
                <pre class="p-4 text-xs overflow-x-auto whitespace-pre-wrap break-all rounded-md bg-slate-50 dark:bg-zink-600 border border-slate-200 dark:border-zink-500"><?= e($content) ?></pre>
                <p class="mt-3 text-xs text-slate-500 dark:text-zink-300">Shows at most the first 4,000 characters.</p>
            <?php } ?>
        </div>
    </div>
</div>
{% endblock %}
