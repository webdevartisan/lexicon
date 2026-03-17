{% extends "base_dashboard.lex.php" %}

{% block title %}View Role{% endblock %}

{% block body %}
<style>
.permission-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 8px 16px;
    margin-bottom: 1em;
}
.permission-grid label {
    display: flex;
    align-items: center;
}
</style>

<h1>{{ role.role_name }}</h1>

<p><strong>Slug:</strong> {{ role.role_slug }}</p>
<p><strong>Level:</strong> {{ role.level }}</p>
<p><strong>Description:</strong></p>
<div>{{ role.description }}</div>

<hr>

<h2>Permissions</h2>

<form method="post" action="/admin/roles/{{ role.id }}/permissions">
    <p>Select permissions to grant to this role.</p>

<div class="permission-grid">
    {% foreach ($allPermissions as $perm): %}
        <?php
            $isChecked = in_array($perm['id'], array_column($permissions, 'id') ?? $permissions ?? []);
        ?>
        <label>
            <input
                type="checkbox"
                name="permissions[]"
                value="{{ perm['id'] }}"
                <?php if ($isChecked) { ?>checked<?php } ?>
            >
            {{ perm['permission_name'] ?? perm['name'] }} ({{ perm['permission_slug'] ?? perm['slug'] }})
        </label>
    {% endforeach; %}
</div>


    <p style="margin-top:12px;">
        <button type="submit">Update Permissions</button>
        <!-- <a href="/admin/roles/{{ role.id }}/edit" style="margin-left:8px;">Edit Role</a> -->
        <!-- <a href="/admin/roles/{{ role.id }}/delete" style="margin-left:8px;">Delete</a>  -->
        <a href="/admin/roles" style="margin-left:8px;">Back to list</a>
    </p>
</form>
{% endblock %}
