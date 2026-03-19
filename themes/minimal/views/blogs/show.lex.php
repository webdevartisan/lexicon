{% extends "layouts/front.lex.php" %}

{% block "content" %}
  <header class="page-header">
    <h1><?= e($blog['title'] ?? ($username."'s Blog")) ?></h1>
    <?php if (!empty($blog['subtitle'])) { ?>
      <p class="subtitle"><?= e($blog['subtitle']) ?></p>
    <?php } ?>
  </header>

  <?php if (!empty($posts)) { ?>
    <ul class="post-list">
      <?php foreach ($posts as $post) { ?>
        <li>
          <a href="/<?= urlencode($username) ?>/<?= urlencode($post['slug']) ?>">
            <?= e($post['title']) ?>
          </a>
          <time datetime="<?= e($post['published_at']) ?>">
            <?= e($post['published_at']) ?>
          </time>
        </li>
      <?php } ?>
    </ul>
  <?php } else { ?>
    <p>No posts yet.</p>
  <?php } ?>
{% endblock %}
