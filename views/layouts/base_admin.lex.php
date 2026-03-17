<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/water.css">
    <link rel="stylesheet" href="/admin.css">
    <title>{% yield title %} - Admin</title>
</head>
<body>
    <nav>
        <a href="/admin/dashboard">Dashboard</a> |
        <a href="/admin/blogs">Blogs</a> |
        <a href="/admin/posts">Posts</a> |
        <a href="/admin/categories">Categories</a> |
        <a href="/admin/tags">Tags</a> |
        <a href="/admin/settings">Settings</a> |
        <a href="/admin/users">Users</a> |
        <a href="/admin/roles">Roles</a> |
        <a href="/login/logout">Logout</a>
    </nav>
    <hr>
    {% yield body %}
</body>
</html>