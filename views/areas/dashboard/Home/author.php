<?php
// app/Views/dashboard/author.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Author Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../app/Views/partials/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../app/Views/partials/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Author Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if (!empty($assignedBlogs)) { ?>
                        <a href="/posts/create" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Create New Post
                        </a>
                        <?php } ?>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title text-muted">Assigned Blogs</h5>
                                <h2><?= $stats['assigned_blogs'] ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title text-muted">Total Posts</h5>
                                <h2><?= $stats['total_posts'] ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title text-muted">Drafts</h5>
                                <h2><?= $stats['draft_posts'] ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title text-muted">Published</h5>
                                <h2><?= $stats['published_posts'] ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (empty($assignedBlogs)) { ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    You are not assigned to any blogs yet. Please contact a Blog Owner to get access.
                </div>
                <?php } else { ?>
                <!-- Assigned Blogs -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>Blogs I Can Write For</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <?php foreach ($assignedBlogs as $blog) { ?>
                                    <a href="/posts?blog=<?= $blog['id'] ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?= htmlspecialchars($blog['blog_name']) ?></h6>
                                            <small>Assigned: <?= date('M d, Y', strtotime($blog['assigned_at'])) ?></small>
                                        </div>
                                        <p class="mb-1 text-muted"><?= htmlspecialchars($blog['description']) ?></p>
                                    </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My Recent Posts -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>My Recent Posts</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Blog</th>
                                                <th>Status</th>
                                                <th>Updated</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($myPosts as $post) { ?>
                                            <tr>
                                                <td><?= htmlspecialchars($post['title']) ?></td>
                                                <td><?= htmlspecialchars($post['blog_name']) ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = [
                                                        'draft' => 'secondary',
                                                        'pending_review' => 'warning',
                                                        'published' => 'success',
                                                    ];
                                                $class = $statusClass[$post['status']] ?? 'light';
                                                ?>
                                                    <span class="badge bg-<?= $class ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $post['status'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M d, Y', strtotime($post['updated_at'])) ?></td>
                                                <td>
                                                    <a href="/posts/<?= $post['id'] ?>" class="btn btn-sm btn-primary">
                                                        View
                                                    </a>
                                                    <a href="/posts/<?= $post['id'] ?>/edit" class="btn btn-sm btn-secondary">
                                                        Edit
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>