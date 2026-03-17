{% extends "base_dashboard.lex.php" %}

{% block title %}{{ blog.blog_name }} · Manage Users{% endblock %}

{% block body %}
<main class="container-fluid px-4">
  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mt-4 mb-3">
    <div>
      <h1 class="h3 mb-0">Manage Users · {{ blog.blog_name }}</h1>
      <small class="text-muted">Assign roles to control who can write, edit, and publish for this blog.</small>
    </div>
    <div class="d-flex gap-2">
      <a href="/dashboard/blogs/{{ blog.id }}/show" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Blog
      </a>
    </div>
  </div>

  <!-- Current Users -->
  <section class="card mb-4">
    <div class="card-header">
      <h2 class="h6 mb-0 text-uppercase text-muted">Current Users</h2>
    </div>
    <div class="card-body">
      {% if assigned|empty %}
        <p class="text-muted mb-0">No users assigned yet. Add users below to start collaborating.</p>
      {% else %}
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Assigned</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
            {% foreach ($assigned as $u): %}
              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    <i class="fas fa-user-circle text-muted me-2"></i>
                    <strong><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></strong>
                  </div>
                </td>
                <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <?php
                  // should use color-coded badges for visual hierarchy.
                  $badgeClass = match ($u['role']) {
                      'owner' => 'bg-danger',
                      'editor' => 'bg-primary',
                      'author' => 'bg-success',
                      'contributor' => 'bg-info',
                      'reviewer' => 'bg-warning text-dark',
                      'viewer' => 'bg-secondary',
                      default => 'bg-secondary'
                  };
                    ?>
                  <span class="badge <?= $badgeClass ?> text-capitalize"><?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?></span>
                </td>
                <td>
                  <small class="text-muted"><?= htmlspecialchars($u['assigned_at'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></small>
                </td>
                <td class="text-end">
                  <form method="post" action="/dashboard/blogs/{{ blog.id }}/users" class="d-inline" 
                        onsubmit="return confirm('Remove <?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?> from this blog?');">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                      <i class="fas fa-times me-1"></i>Remove
                    </button>
                  </form>
                </td>
              </tr>
            {% endforeach %}
            </tbody>
          </table>
        </div>
      {% endif %}
    </div>
  </section>

  <!-- Add New User -->
  <section class="card">
    <div class="card-header">
      <h2 class="h6 mb-0 text-uppercase text-muted">Add User</h2>
    </div>
    <div class="card-body">
      <form method="post" action="/dashboard/blogs/{{ blog.id }}/users" class="row g-3" id="addUserForm">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="add">

        <div class="col-12 col-md-6">
          <label for="user_id" class="form-label">Select User</label>
          <select id="user_id" name="user_id" class="form-select" required>
            <option value="">-- Choose a user --</option>
            <?php
            // should fetch available users from controller and pass them to view.
            // For now, assume $availableUsers is passed from controller.
            if (!empty($availableUsers)) {
                foreach ($availableUsers as $au) {
                    ?>
              <option value="<?= (int) $au['id'] ?>">
                <?= htmlspecialchars($au['username'], ENT_QUOTES, 'UTF-8') ?> 
                (<?= htmlspecialchars($au['email'], ENT_QUOTES, 'UTF-8') ?>)
              </option>
            <?php
                }
            }
                    ?>
          </select>
          <small class="form-text text-muted">Only users not currently assigned to this blog appear here.</small>
        </div>

        <div class="col-12 col-md-4">
          <label for="role" class="form-label">Role</label>
          <select id="role" name="role" class="form-select" required>
            {% foreach ($assignableRoles as $role): %}
              <option value="{{ role }}">
                <?= ucfirst(htmlspecialchars($role, ENT_QUOTES, 'UTF-8')) ?>
              </option>
            {% endforeach %}
          </select>
          <small class="form-text text-muted">
            <a href="#roleHelp" data-bs-toggle="collapse" class="text-decoration-none">What do these roles mean?</a>
          </small>
        </div>

        <div class="col-12 col-md-2 d-grid align-self-end">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>Add
          </button>
        </div>

        <!-- Role help (collapsible) -->
        <div class="col-12 collapse" id="roleHelp">
          <div class="alert alert-info mb-0">
            <strong>Role Capabilities:</strong>
            <ul class="mb-0 mt-2">
              <li><strong>Owner:</strong> Full control—manage users, settings, posts, and delete blog.</li>
              <li><strong>Editor:</strong> Manage users, review/publish posts, edit settings.</li>
              <li><strong>Author:</strong> Create and edit own posts; may publish depending on workflow.</li>
              <li><strong>Contributor:</strong> Submit drafts; requires review before publishing.</li>
              <li><strong>Reviewer:</strong> Review submissions and change workflow states; cannot publish.</li>
              <li><strong>Viewer:</strong> Read-only access to blog dashboard and preview.</li>
            </ul>
          </div>
        </div>
      </form>
    </div>
  </section>

  <!-- Info tip -->
  <div class="alert alert-info mt-4" role="alert">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Tip:</strong> Users must have an account on your site before you can assign them to a blog. 
    Global administrators can always access any blog regardless of per-blog roles.
  </div>
</main>
{% endblock %}
