{% extends "back.lex.php" %}

{% block title %}New Post{% endblock %}
{% block subtitle %}Write a post and assign it to a blog.{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/choices.css">
{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-4xl">
    {% include "areas/admin/_errors.lex.php" %}

    <form method="post" action="/admin/posts/create" class="card">
        {{ csrf_field() }}
        <div class="card-body">
            {% include "areas/admin/Post/form.lex.php" %}
        </div>
        {% cmp="form-footer" cancelHref="/admin/posts" submitLabel="Create Post" submitIcon="plus" %}
    </form>
</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/libs/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="/cp-assets/js/searchable-select.init.js"></script>
{% endblock %}
