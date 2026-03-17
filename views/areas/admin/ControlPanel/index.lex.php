{% extends "back.lex.php" %}

{% block title %}Control Panel{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    <?php /* System Stats Grid */ ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-5">
        <?php /* Total Posts */ ?>
        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-zink-200">Total Posts</p>
                        <p class="text-3xl font-semibold text-slate-900 dark:text-zink-50 mt-2">
                            <?= number_format($stats['posts']) ?>
                        </p>
                    </div>
                    <div class="flex items-center justify-center size-12 bg-purple-100 rounded-md dark:bg-purple-500/20">
                        <i class="text-purple-500 dark:text-purple-200" data-lucide="file-text"></i>
                    </div>
                </div>
            </div>
        </div>

        <?php /* Total Comments */ ?>
        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-zink-200">Total Comments</p>
                        <p class="text-3xl font-semibold text-slate-900 dark:text-zink-50 mt-2">
                            <?= number_format($stats['comments']) ?>
                        </p>
                    </div>
                    <div class="flex items-center justify-center size-12 bg-green-100 rounded-md dark:bg-green-500/20">
                        <i class="text-green-500 dark:text-green-200" data-lucide="message-square"></i>
                    </div>
                </div>
            </div>
        </div>

        <?php /* Total Users */ ?>
        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-zink-200">Total Users</p>
                        <p class="text-3xl font-semibold text-slate-900 dark:text-zink-50 mt-2">
                            <?= number_format($stats['users']) ?>
                        </p>
                    </div>
                    <div class="flex items-center justify-center size-12 bg-sky-100 rounded-md dark:bg-sky-500/20">
                        <i class="text-sky-500 dark:text-sky-200" data-lucide="users"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php /* Cache Statistics Card */ ?>
    <?php if (isset($cacheStats)) { ?>
    <div class="mb-5">
        {% cmp="cache-stats-card" stats="{$cacheStats}" isAdmin="true" %}
    </div>
    <?php } ?>

</div>


{% endblock %}
