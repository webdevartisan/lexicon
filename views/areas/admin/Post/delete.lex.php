{% extends "back.lex.php" %}

{% block title %}Delete Post{% endblock %}
{% block subtitle %}This action is permanent and cannot be undone.{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-xl">
    <form method="post" action="/admin/posts/<?= e((string) $post['id']) ?>/destroy" class="card">
        {{ csrf_field() }}
        <div class="card-body text-center py-10">
            <i data-lucide="alert-triangle" class="size-12 text-red-500 mx-auto mb-3"></i>
            <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1">
                Delete "<?= e(truncate((string) $post['title'], 60)) ?>"?
            </h3>
            <p class="text-sm text-slate-500 dark:text-zink-300">
                The post and its comments will be removed permanently.
            </p>
        </div>
        {% cmp="form-footer" cancelHref="/admin/posts" submitLabel="Yes, delete post" submitIcon="trash-2" danger %}
    </form>
</div>
{% endblock %}
