<?php
$blogName = $blog['blog_name'] ?? '';
$blogSlug = $blog['blog_slug'] ?? '';
$description = $blog['description'] ?? '';
?>
<div class="grid grid-cols-1 gap-5">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        {% cmp="input" type="text" label="Name" name="blog_name" value="{$blogName}" required %}
        {% cmp="input" type="text" label="Slug" name="blog_slug" value="{$blogSlug}" placeholder="generated-from-name-if-blank" %}
    </div>

    {% cmp="input" type="textarea" label="Description" name="description" value="{$description}" rows="4" %}
</div>
