<label for="name">Name</label>
<input type="text" name="name" id="name" value="{{ tag['name'] ?? '' }}">

{% if (isset($errors['name'])) : %}
    <p>{{ errors['name'] }}</p>
{% endif; %}

<label for="slug">Slug</label>
<input type="text" name="slug" id="slug" value="{{ tag['slug'] ?? '' }}">

{% if (isset($errors['slug'])) : %}
    <p>{{ errors['slug'] }}</p>
{% endif; %}

<button>Save</button>
