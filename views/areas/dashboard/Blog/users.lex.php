{% extends "back.lex.php" %}
{% block title %}{{ blog.blog_name }} · Collaborators{% endblock %}
{% block subtitle %}Assign roles to control who can write, edit, and publish for this blog.{% endblock %}
{% block body %}
<?php
$roleBadge = [
    'owner' => 'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/40 dark:border-red-800',
    'editor' => 'bg-custom-50 text-custom-700 border-custom-100 dark:bg-custom-500/20 dark:border-custom-800 dark:text-custom-300',
    'author' => 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800',
    'contributor' => 'bg-sky-100 text-sky-700 border-sky-200 dark:bg-sky-900/40 dark:border-sky-800',
    'reviewer' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:border-amber-800',
    'viewer' => 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-zink-600 dark:text-zink-200 dark:border-zink-500',
];
$initials = static function (string $name): string {
    $parts = preg_split('/[\s_.-]+/', trim($name)) ?: [];
    $a = mb_substr($parts[0] ?? $name, 0, 1);
    $b = isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '';

    return mb_strtoupper($a.$b) ?: 'U';
};
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="flex items-center justify-between gap-3 mb-5">
        <a href="/dashboard/blog/{{ blog.id }}/show"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
            <i data-lucide="arrow-left" class="size-4"></i>
            <span>Back to blog</span>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Current collaborators -->
        <section class="lg:col-span-2 card">
            <div class="card-body">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 flex items-center gap-2">
                        <i data-lucide="users" class="size-4 text-custom-500"></i>
                        Current collaborators
                    </h2>
                    <?php if (!empty($assigned)) { ?>
                    <span class="text-xs text-slate-500 dark:text-zink-300"><?= count($assigned) ?> assigned</span>
                    <?php } ?>
                </div>

                {% if assigned|empty %}
                <div class="text-center py-10">
                    <i data-lucide="user-plus" class="size-10 text-slate-400 mx-auto mb-2"></i>
                    <p class="text-sm text-slate-500 dark:text-zink-300">No collaborators yet. Add someone using the form to start working together.</p>
                </div>
                {% else %}
                <ul class="divide-y divide-slate-100 dark:divide-zink-600">
                    {% foreach ($assigned as $u): %}
                    <li class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                        <span class="flex items-center justify-center size-9 rounded-full bg-custom-100 text-custom-600 dark:bg-custom-500/20 dark:text-custom-300 text-xs font-semibold shrink-0">
                            <?= e($initials((string) ($u['username'] ?? 'U'))) ?>
                        </span>
                        <div class="min-w-0 grow">
                            <p class="text-sm font-medium text-slate-900 dark:text-zink-50 truncate"><?= e($u['username'] ?? 'Unknown') ?></p>
                            <p class="text-xs text-slate-500 dark:text-zink-300 truncate"><?= e($u['email'] ?? '') ?></p>
                        </div>
                        <?php $r = (string) ($u['role'] ?? 'viewer'); ?>
                        <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium rounded-full border capitalize <?= $roleBadge[$r] ?? $roleBadge['viewer'] ?>">
                            <?= e($r) ?>
                        </span>
                        <form method="POST" action="/dashboard/blog/{{ blog.id }}/users" class="m-0 shrink-0"
                            onsubmit="return confirm('Remove <?= e($u['username'] ?? 'this user') ?> from this blog?');">
                            {{ csrf_field() }}
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="user_id" value="<?= (int) ($u['user_id'] ?? 0) ?>">
                            <button type="submit" title="Remove collaborator"
                                class="p-2 text-slate-500 hover:text-red-600 rounded-md hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                                <i data-lucide="user-minus" class="size-4"></i>
                            </button>
                        </form>
                    </li>
                    {% endforeach %}
                </ul>
                {% endif %}
            </div>
        </section>

        <!-- Add collaborator -->
        <aside class="card self-start">
            <div class="card-body">
                <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-4 flex items-center gap-2">
                    <i data-lucide="user-plus" class="size-4 text-custom-500"></i>
                    Add collaborator
                </h2>

                <?php if (empty($availableUsers)) { ?>
                <p class="text-sm text-slate-500 dark:text-zink-300">
                    Everyone with an account is already assigned to this blog. New users must register before they can be added.
                </p>
                <?php } else { ?>
                <form method="POST" action="/dashboard/blog/{{ blog.id }}/users" class="space-y-3">
                    {{ csrf_field() }}
                    <input type="hidden" name="action" value="add">

                    <div>
                        <label for="user_id" class="block text-xs font-medium text-slate-600 dark:text-zink-200 mb-1">User</label>
                        <select id="user_id" name="user_id" required class="form-input w-full border-slate-200 dark:border-zink-500">
                            <option value="">Choose a user…</option>
                            <?php foreach ($availableUsers as $au) { ?>
                            <option value="<?= (int) $au['id'] ?>"><?= e($au['username']) ?> (<?= e($au['email']) ?>)</option>
                            <?php } ?>
                        </select>
                    </div>

                    <div>
                        <label for="role" class="block text-xs font-medium text-slate-600 dark:text-zink-200 mb-1">Role</label>
                        <select id="role" name="role" required class="form-input w-full border-slate-200 dark:border-zink-500">
                            {% foreach ($assignableRoles as $role): %}
                            <option value="{{ role }}"><?= ucfirst(e($role)) ?></option>
                            {% endforeach %}
                        </select>
                    </div>

                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 transition-colors">
                        <i data-lucide="plus" class="size-4"></i> Add collaborator
                    </button>
                </form>
                <?php } ?>
            </div>
        </aside>
    </div>

    <!-- Role reference -->
    <section class="card mt-6">
        <div class="card-body">
            <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-4 flex items-center gap-2">
                <i data-lucide="shield" class="size-4 text-custom-500"></i>
                What each role can do
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                <?php
                    $roleDocs = [
                        'owner' => 'Full control — manage collaborators, settings, all posts, and delete the blog.',
                        'editor' => 'Manage collaborators, review and publish posts, and edit blog settings.',
                        'author' => 'Create and edit their own posts; may publish depending on workflow.',
                        'contributor' => 'Submit drafts for review; cannot publish directly.',
                        'reviewer' => 'Review submissions and move them through workflow states; cannot publish.',
                        'viewer' => 'Read-only access to the blog dashboard and previews.',
                    ];
foreach ($roleDocs as $r => $desc) {
    ?>
                    <div class="p-3 rounded-lg border border-slate-100 dark:border-zink-600">
                        <span class="inline-flex items-center px-2 py-0.5 mb-1.5 text-[11px] font-medium rounded-full border capitalize <?= $roleBadge[$r] ?? $roleBadge['viewer'] ?>"><?= e($r) ?></span>
                        <p class="text-xs text-slate-500 dark:text-zink-300 leading-relaxed"><?= e($desc) ?></p>
                    </div>
                <?php } ?>
            </div>
            <p class="mt-4 text-xs text-slate-500 dark:text-zink-300 flex items-start gap-2">
                <i data-lucide="info" class="size-4 shrink-0 mt-0.5"></i>
                Users must have an account before they can be assigned. Global administrators can always access any blog regardless of per-blog roles.
            </p>
        </div>
    </section>

</div>
{% endblock %}
{% block scripts %}
<script src='/cp-assets/js/tooltip.js'></script>
{% endblock %}
