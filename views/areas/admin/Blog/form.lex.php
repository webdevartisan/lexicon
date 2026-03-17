
    <p>
        <label for="blog_name">Name</label><br>
        <input class="responsive-input" type="text" name="blog_name" id="blog_name" value="{{ blog['blog_name'] }}">
        {% if (isset($errors['blog_name'])) : %}
            <p class="error">{{ errors['blog_name'] }}</p>
        {% endif; %}
    </p>

    <p>
        <label for="blog_slug">Slug</label><br>
        <input class="responsive-input" type="text" name="blog_slug" id="blog_slug" value="{{ blog['blog_slug'] }}">
        {% if (isset($errors['blog_slug'])) : %}
            <p class="error">{{ errors['blog_slug'] }}</p>
        {% endif; %}
    </p>

    <p>
        <label for="description">Description</label><br>
        <textarea class="responsive-input" name="description" id="description" rows="5" cols="60">{{ blog['description'] }}</textarea>
        {% if (isset($errors['description'])) : %}
            <p class="error">{{ errors['description'] }}</p>
        {% endif; %}
    </p>

    <p>
        <label for="is_active">
            <input type="checkbox" name="is_active" id="is_active" value="1" {% if (!isset($blog['is_active']) || $blog['is_active']): %} checked {% endif; %}>
            Active
        </label>
        {% if (isset($errors['is_active'])) : %}
            <p class="error">{{ errors['is_active'] }}</p>
        {% endif; %}
    </p>