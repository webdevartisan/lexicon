{% extends "layouts/front.lex.php" %}

{% block "content" %}
  <header class="page-header">
    <h1><?= htmlspecialchars($blog['title'] ?? ($username."'s Blog")) ?></h1>
    <?php if (!empty($blog['subtitle'])) { ?>
      <p class="subtitle"><?= htmlspecialchars($blog['subtitle']) ?></p>
    <?php } ?>
  </header>

  <?php if (!empty($posts)) { ?>
    <ul class="post-list">
      <?php foreach ($posts as $post) { ?>
        <li>
          <a href="/<?= urlencode($username) ?>/<?= urlencode($post['slug']) ?>">
            <?= htmlspecialchars($post['title']) ?>
          </a>
          <time datetime="<?= htmlspecialchars($post['published_at']) ?>">
            <?= htmlspecialchars($post['published_at']) ?>
          </time>
        </li>
      <?php } ?>
    </ul>
  <?php } else { ?>
    <p>No posts yet.</p>
  <?php } ?>
{% endblock %}
