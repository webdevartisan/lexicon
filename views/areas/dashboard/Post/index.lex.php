{% extends "base_dashboard.lex.php" %}

{% block title %}{{ user.username }} · Posts{% endblock %}

{% block body %}
<main class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mt-4 mb-3">
    <div>
      <h1 class="h3 mb-0">Your Posts</h1>
      <small class="text-muted">Manage, preview, and publish your posts.</small>
    </div>
    <div class="d-flex gap-2">
      {% if blog_id|isset %}
      <a href="/dashboard/blogs/{{ blog_id }}/show" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Blog
      </a>
      <a href="/dashboard/posts/new?blog_id={{ blog_id }}" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>New Post
      </a>
      {% else %}
      <a href="/dashboard/blogs" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Blogs
      </a>
      <a href="/dashboard/posts/new" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>New Post
      </a>
      {% endif %}

    </div>
  </div>

  <!-- Filters -->
  <section class="card mb-4">
    <div class="card-body">
      <form method="get" action="" class="row g-2 align-items-end">
        <div class="col-12 col-md-6 col-lg-4">
          <label for="q" class="form-label">Search</label>
          <input id="q" name="q" type="text" value="{{ q|default('') }}" class="form-control" placeholder="Search by title or content">
        </div>
        <div class="col-6 col-md-3 col-lg-2">
          <label for="blog" class="form-label">Blog</label>
          <select id="blog" name="blog_id" class="form-select">
            <option value="">All Blogs</option>
            {% foreach ($blogs as $blog): %}
              <option value="{{ blog.id }}" <?php if ($blog['id'] == $blog_id) { ?>selected<?php } ?>>{{ blog.blog_name }}</option>
            {% endforeach %}
          </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
          <label for="status" class="form-label">Status</label>
          <select id="status" name="status" class="form-select">
            <option value="">Any</option>
            <option value="draft" {% if ($status == 'draft'): %}selected{% endif %}>Draft</option>
            <option value="published" {% if ($status == 'published'): %}selected{% endif %}>Published</option>
            <option value="archived" {% if ($status == 'archived'): %}selected{% endif %}>Archived</option>
          </select>
        </div>
        <div class="col-12 col-lg-3 d-grid">
          <button type="submit" class="btn btn-outline-secondary">Apply</button>
        </div>
      </form>
    </div>
  </section>

  {% if posts|empty %}
    <section class="text-center py-5">
      <div class="mb-3">
        <span class="display-6 d-block">No posts yet</span>
        <p class="text-muted mb-0">Create your first post to start publishing.</p>
      </div>
      <a href="/dashboard/posts/new" class="btn btn-primary btn-lg">
        <i class="fas fa-plus me-2"></i>New Post
      </a>
    </section>

  {% else %}
    <!-- Results -->
    <section class="mb-4">
      <div class="row g-4">
        {% foreach ($posts as $post): %}
          <div class="col-12 col-md-6 col-xl-4">
            <article class="card h-100">
                {% if post.featured_image|empty %}
                  <?php $post['featured_image'] = '/images/pic08.jpg' ?>
                {% endif %}
              <a href="/dashboard/posts/{{ post.id }}/edit" class="card-img-top d-block ratio ratio-21x9">
                <img src="{{ post.featured_image }}" alt="{{ post.title }} cover" class="w-100 h-100 object-fit-cover">
              </a>
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between gap-2">
                  <div>
                    <h2 class="h5 mb-1">
                      <a href="/dashboard/posts/{{ post.id }}/edit" class="text-decoration-none">{{ post.title }}</a>
                    </h2>
                    <div class="small text-muted">In 
                      <a href="/dashboard/blogs/{{ post.blog_id }}/show">
                        {{ post.blog_name|default('Uncategorized') }}
                      </a>
                    </div>
                  </div>
                  <div>
                    <?php if ($post['status'] == 'published') { ?>
                      <span class="badge bg-success">Published</span>
                    <?php } elseif ($post['status'] == 'draft') { ?>
                      <span class="badge bg-secondary">Draft</span>
                    <?php } elseif ($post['status'] == 'archived') { ?>
                      <span class="badge bg-dark">Archived</span>
                    <?php } ?>
                  </div>
                </div>

                <div class="mt-2">
                  <small class="text-muted">Published on {{ post.created_at }}</small>
                </div>
                <p class="mt-2 text-muted"><?= truncate(strip_tags($post['content']), 100) ?></p>
                <div class="d-flex flex-wrap gap-2 mt-3">
                  <a class="btn btn-outline-secondary btn-sm" href="/dashboard/posts/{{ post.id }}/edit">
                    <i class="fas fa-pen me-1"></i>Edit
                  </a>
                  <a class="btn btn-outline-primary btn-sm" target="_blank" href="/blog/<?= $blog_slug[$post['blog_id']]; ?>/{{ post.slug }}">
                    <i class="fas fa-external-link-alt me-2"></i></i>Open Public View
                  </a>
                  <a class="btn btn-outline-danger btn-sm" href="/dashboard/posts/{{ post.id }}/delete" onclick="return confirm('Delete this post?');">
                    <i class="fas fa-trash me-1"></i>Delete
                  </a>
                </div>
              </div>
              <div class="card-footer d-flex justify-content-between align-items-center">
                <div class="small text-muted">Updated {{ post.updated_at }}</div>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-primary">{{ post.comment_count }} comments</span>
                  {% if post.follower_count|isset %}
                    <span class="badge bg-info text-dark">{{ post.follower_count }} followers</span>
                  {% endif %}
                </div>
              </div>
            </article>
          </div>
        {% endforeach %}
      </div>
    </section>
    <!-- Pagination (placeholder) -->
  {% endif %}
</main>
{% endblock %}
