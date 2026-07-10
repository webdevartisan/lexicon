{% extends "back.lex.php" %}

{% block title %}Delete Category{% endblock %}
{% block subtitle %}This action is permanent and cannot be undone.{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-xl">
    <form method="post" action="/admin/categories/<?= e((string) $category['id']) ?>/destroy" class="card">
        {{ csrf_field() }}
        <div class="card-body text-center py-10">
            {% cache 'lucide:alert-triangle' ttl=3600 %}<i data-lucide="alert-triangle" class="size-12 text-red-500 mx-auto mb-3"></i>{% endcache %}
            <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1">
                Delete "<?= e($category['name']) ?>"?
            </h3>
            <p class="text-sm text-slate-500 dark:text-zink-300">
                Posts in this category will not be deleted, but they will lose this categorization.
            </p>
        </div>
        {% cmp="form-footer" cancelHref="/admin/categories" submitLabel="Yes, delete category" submitIcon="trash-2" danger %}
    </form>
</div>
{% endblock %}
