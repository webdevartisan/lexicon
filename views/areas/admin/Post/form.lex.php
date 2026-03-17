<p>
    <label>Title<br>
        <input class="responsive-input" type="text" name="title" value="{{ post.title }}">
    </label>
</p>

<p>
    <label>Slug<br>
        <input class="responsive-input" type="text" name="slug" value="{{ post.slug }}">
    </label>
</p>

<p>
    <label>Content<br>
        <textarea class="responsive-input" name="content" rows="10" cols="60">{{ post.content }}</textarea>
    </label>
</p>

<p>
    <label>Excerpt<br>
        <textarea class="responsive-input" name="excerpt" rows="2" cols="60">{{ post.excerpt }}</textarea>
    </label>
</p>

<p>
    <label>Featured Image URL<br>
        <input class="responsive-input" type="text" name="featured_image" value="{{ post.featured_image }}">
    </label>
</p>

<p>
    <label>Status<br>
        <select name="status">
            <option value="draft" <?= ($post['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="pending_review" <?= ($post['status'] ?? '') === 'pending_review' ? 'selected' : '' ?>>Pending Review</option>
            <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
        </select>
    </label>
</p>

<p>
    <label>Blog<br>
        <select name="blog_id">
            {% for blog in blogs %}
                <option value="<?= $blog['id'] ?>" <?= ($post['blog_id'] ?? '') == $blog['id'] ? 'selected' : '' ?>>
                    {{ blog.blog_name }}
                </option>
            {% endfor %}
        </select>
    </label>
</p>
