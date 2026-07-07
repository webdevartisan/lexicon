<?php
$name = $tag['name'] ?? '';
$slug = $tag['slug'] ?? '';
$blogOptions = array_column($blogs ?? [], 'blog_name', 'id');
$selectedBlog = (string) ($tag['blog_id'] ?? '');
?>
<div class="grid grid-cols-1 gap-5">
    <?php if (empty($tag['id'])) { ?>
    {% cmp="searchable-select" name="blog_id" label="Blog" options="{$blogOptions}" selectedKey="{$selectedBlog}" placeholder="Choose a blog..." required underlabel="Tags are scoped to one blog and cannot be moved later." %}
    <?php } ?>

    {% cmp="input" type="text" label="Name" name="name" value="{$name}" required %}
    {% cmp="input" type="text" label="Slug" name="slug" value="{$slug}" placeholder="lowercase-with-dashes" underlabel="Used in URLs. Leave blank to keep the current value." %}
</div>
