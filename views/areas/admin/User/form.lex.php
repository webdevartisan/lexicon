<p>
    <label>Username<br>
        <input type="text" name="username" value="<?= e($user['username'] ?? '') ?>">
    </label>
</p>

<p>
    <label>First Name<br>
        <input type="text" name="first_name" value="<?= e($user['first_name'] ?? '') ?>">
    </label>
</p>

<p>
    <label>Last Name<br>
        <input type="text" name="last_name" value="<?= e($user['last_name'] ?? '') ?>">
    </label>
</p>

<p>
    <label>Email<br>
        <input type="email" name="email" value="<?= e($user['email'] ?? '') ?>">
    </label>
</p>

<p>
    <label>Password<br>
        <input type="password" name="password">
        {% if user.id|isset %}
            <small>Leave blank to keep current password</small>
        {% endif %}
    </label>
</p>

<p>
    <label>Roles</label><br>
    {% for role in roles %}
        <label>
            <input type="checkbox" name="roles[]" value="{{ role.id }}"
                <?php if (!empty($user['roles']) && in_array($role['role_slug'], $user['roles'])) { ?>checked<?php } ?>>
            {{ role.role_name }}
        </label>
    {% endfor %}
</p>
