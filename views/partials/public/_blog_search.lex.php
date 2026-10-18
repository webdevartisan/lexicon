<?php
$searchAction = lurl('/blog/'.rawurlencode((string) ($blog['blog_slug'] ?? '')).'/search');
?>
<form class="blog-search" method="get" action="<?= e($searchAction) ?>" role="search">
  <label class="blog-search-label" for="blog-search-q">Search this blog</label>
  <div class="blog-search-row">
    <input id="blog-search-q" class="blog-search-input" type="search" name="q" value="<?= e($searchTerm ?? '') ?>" maxlength="100" placeholder="Search posts" autocomplete="off">
    <button class="blog-search-button" type="submit">Search</button>
  </div>
</form>

<style>
  .blog-search { margin: 1.75rem 0 0; max-width: 32rem; }
  .blog-search-label { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
  .blog-search-row { display: flex; gap: 0.5rem; }
  .blog-search-input,
  .blog-search-button { font: inherit; color: inherit; background: transparent; border: 1px solid currentColor; border-radius: 0; padding: 0.6em 0.9em; }
  .blog-search-input { flex: 1; min-width: 0; }
  .blog-search-input::placeholder { color: inherit; opacity: 0.55; }
  .blog-search-button { cursor: pointer; }
  .blog-search-input:focus,
  .blog-search-button:focus-visible { outline: none; border-bottom-width: 3px; }
  .blog-search-button:hover { opacity: 0.7; }
</style>
