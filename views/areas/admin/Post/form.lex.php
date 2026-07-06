<?php
$title = $post['title'] ?? '';
$slug = $post['slug'] ?? '';
$content = $post['content'] ?? '';
$excerpt = $post['excerpt'] ?? '';
$featuredImage = $post['featured_image'] ?? '';
$statusOptions = ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'];
$selectedStatus = $post['status'] ?? 'draft';
$blogOptions = array_column($blogs ?? [], 'blog_name', 'id');
$selectedBlog = (string) ($post['blog_id'] ?? '');
?>
<div class="grid grid-cols-1 gap-5">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        {% cmp="input" type="text" label="Title" name="title" value="{$title}" required %}
        {% cmp="input" type="text" label="Slug" name="slug" value="{$slug}" placeholder="lowercase-with-dashes" %}
    </div>

    {% cmp="input" type="textarea" label="Content" name="content" value="{$content}" rows="12" %}
    {% cmp="input" type="textarea" label="Excerpt" name="excerpt" value="{$excerpt}" rows="2" %}

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 items-end">
        {% cmp="input" type="text" label="Featured Image URL" name="featured_image" value="{$featuredImage}" %}
        {% cmp="select" label="Status" name="status" options="{$statusOptions}" selectedKey="{$selectedStatus}" %}
        {% cmp="searchable-select" name="blog_id" label="Blog" options="{$blogOptions}" selectedKey="{$selectedBlog}" placeholder="Choose a blog..." required %}
    </div>
</div>
