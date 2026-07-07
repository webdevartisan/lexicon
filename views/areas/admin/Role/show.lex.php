{% extends "back.lex.php" %}

{% block title %}Role Permissions{% endblock %}
{% block subtitle %}Grant or revoke what this role can do.{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-4xl">
    <div class="card mb-5">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-zink-50">{{ role.role_name }}</h2>
                        <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-600 dark:text-zink-100 dark:border-zink-500">
                            {{ role.role_slug }}
                        </span>
                        <span class="text-xs text-slate-400 dark:text-zink-300">Level {{ role.level }}</span>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-zink-300">{{ role.description }}</p>
                </div>
                <a href="/admin/roles" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                    Back to list
                </a>
            </div>
        </div>
    </div>

    <form method="post" action="/admin/roles/{{ role.id }}/permissions" class="card">
        {{ csrf_field() }}
        <div class="card-body">
            <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1">Permissions</h3>
            <p class="text-sm text-slate-500 dark:text-zink-300 mb-4">Check the permissions this role should grant.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-5 gap-y-2.5">
                {% foreach ($allPermissions as $perm): %}
                <?php
                    $isChecked = in_array($perm['id'], array_column($permissions, 'id'));
                ?>
                <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="permissions[]" value="{{ perm['id'] }}"
                           class="form-checkbox rounded border-slate-300 dark:border-zink-500 text-custom-500 focus:ring-custom-500"
                        <?php if ($isChecked) { ?>checked<?php } ?>>
                    <span>
                        <?= e($perm['permission_name'] ?? $perm['name'] ?? '') ?>
                        <span class="block text-[11px] text-slate-400 dark:text-zink-300"><?= e($perm['permission_slug'] ?? $perm['slug'] ?? '') ?></span>
                    </span>
                </label>
                {% endforeach; %}
            </div>
        </div>
        <div class="card-body flex items-center justify-end gap-2 border-t border-slate-100 dark:border-zink-600">
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 transition-colors">
                <i data-lucide="shield-check" class="size-4"></i> Update Permissions
            </button>
        </div>
    </form>
</div>
{% endblock %}
