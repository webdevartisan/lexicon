<?php /* themes/minimal/views/layouts/front.lex.php */ ?>
<!doctype html>
<html lang="<?= $head['lang'] ?? 'en' ?>">
  <head>
    <meta charset="utf-8">
    <title><?= $meta['title'] ?? 'Blog' ?></title>
    <link rel="preload" as="style" href="<?= $asset('css/theme.css') ?>">
    <link rel="stylesheet" href="<?= $asset('css/theme.css') ?>">
  </head>
  <body class="theme-minimal">
    <?php /* include themed nav */ ?>
    {% include "partials/nav.lex.php" %}
    <main class="container">
      {% yield content %}
    </main>
    <footer class="footer">© <?= date('Y') ?> Lexicon 1</footer>
  </body>
</html>
