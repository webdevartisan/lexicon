<?php
$name = $category['name'] ?? '';
$slug = $category['slug'] ?? '';
$blogOptions = array_column($blogs ?? [], 'blog_name', 'id');
$selectedBlog = (string) ($category['blog_id'] ?? '');
?>
<div class="grid grid-cols-1 gap-5">
    <?php if (empty($category['id'])) { ?>
    {% cmp="searchable-select" name="blog_id" label="Blog" options="{$blogOptions}" selectedKey="{$selectedBlog}" placeholder="Choose a blog..." required underlabel="Categories are scoped to one blog and cannot be moved later." %}
    <?php } ?>

    {% cmp="input" type="text" label="Name" name="name" value="{$name}" required %}
    {% cmp="input" type="text" label="Slug" name="slug" value="{$slug}" placeholder="lowercase-with-dashes" underlabel="Used in URLs. Leave blank to keep the current value." %}
</div>
