<p>
    <label>Site Title<br>
        <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>">
    </label>
</p>

<p>
    <label>Site Description<br>
        <textarea name="site_description" rows="3" cols="60"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
    </label>
</p>

<p>
    <label>Posts Per Page<br>
        <input type="number" name="posts_per_page" value="<?= htmlspecialchars($settings['posts_per_page'] ?? 10) ?>">
    </label>
</p>
