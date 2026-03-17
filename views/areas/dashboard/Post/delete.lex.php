{% extends "back.lex.php" %}

{% block title %}Delete Post{% endblock %}

{% block body %}
<section class="max-w-2xl mx-auto mt-10 p-6 bg-white shadow-md rounded-lg">
    <h1 class="text-2xl font-semibold text-red-600 mb-4">Delete Post</h1>

    <p class="text-gray-700 mb-6">
        Are you sure you want to delete the post 
        <strong class="text-gray-900">"{{ post.title }}"</strong>?
    </p>

    <form action="/dashboard/posts/{{ post.id }}/destroy" method="post" class="flex items-center gap-4">
        {{ csrf_field() }}
        <button 
            type="submit" 
            class="px-5 py-2 bg-red-600 text-white font-medium rounded-md hover:bg-red-700 transition"
        >
            Yes, delete
        </button>

        <a 
            href="/dashboard" 
            class="px-5 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition"
        >
            Cancel
        </a>
    </form>
</section>
{% endblock %}
