{% extends "back.lex.php" %}

{% block title %}My Blogs{% endblock %}
{% block subtitle %}Manage, preview, and publish your blogs.{% endblock %}

{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">
  <!-- Filters -->
  <section class="mb-4">
    <div class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
      <div class="p-4 md:p-5">
        <form method="get" action="" class="grid gap-3 md:grid-cols-4 md:items-end">
          <div class="md:col-span-2">
            <label for="q" class="block mb-1 text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300">
              Search
            </label>
            <input
              id="q"
              name="q"
              type="text"
              value="{{ q }}"
              class="block w-full px-3 py-2 text-sm border rounded-md outline-none border-slate-300/80 text-slate-900 placeholder:text-slate-400 bg-white focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:bg-zink-800 dark:border-zink-600 dark:text-zink-100"
              placeholder="Search by name or URL">
          </div>

          <div>
            <label for="status" class="block mb-1 text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300">
              Status
            </label>
            <select
              id="status"
              name="status"
              class="block w-full px-3 py-2 text-sm border rounded-md outline-none border-slate-300/80 bg-white text-slate-900 focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:bg-zink-800 dark:border-zink-600 dark:text-zink-100">
              <option value="">Any</option>
              <option value="draft" {% if ($status === 'draft'): %}selected{% endif %}>Draft</option>
              <option value="published" {% if ($status === 'published'): %}selected{% endif %}>Published</option>
              <option value="archived" {% if ($status === 'archived'): %}selected{% endif %}>Archived</option>
            </select>
          </div>

          <div>
            <label for="sort" class="block mb-1 text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300">
              Sort
            </label>
            <select
              id="sort"
              name="sort"
              class="block w-full px-3 py-2 text-sm border rounded-md outline-none border-slate-300/80 bg-white text-slate-900 focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:bg-zink-800 dark:border-zink-600 dark:text-zink-100">
              <option value="updated" {% if ($sort === 'updated'): %}selected{% endif %}>Last updated</option>
              <option value="created" {% if ($sort === 'created'): %}selected{% endif %}>Date created</option>
              <option value="posts" {% if ($sort === 'posts'): %}selected{% endif %}>Post count</option>
            </select>
          </div>

          <div class="md:col-span-4 flex justify-end">
            <button
              type="submit"
              class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium transition-all duration-150 ease-linear border rounded-md md:w-auto text-slate-700 bg-slate-100 border-slate-200 hover:bg-slate-200 hover:text-slate-800 focus:outline-none focus:ring focus:ring-slate-100 dark:bg-zink-800 dark:text-zink-100 dark:border-zink-600 dark:hover:bg-zink-700">
              Apply
            </button>
          </div>
        </form>
      </div>
    </div>
  </section>

  <!-- Empty state -->
  {% if blogs|empty %}
    <section class="flex flex-col items-center justify-center py-16 text-center bg-white border border-dashed rounded-lg border-slate-200 dark:bg-zink-700 dark:border-zink-600">
      <div class="mb-4">
        <p class="mb-1 text-lg font-semibold text-slate-900 dark:text-zink-100">No blogs yet</p>
        <p class="text-sm text-slate-500 dark:text-zink-300">
          Create your first blog to start publishing.
        </p>
      </div>
      <a href="/dashboard/blogs/new"
         class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white transition-all duration-150 ease-linear bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 hover:border-custom-600 focus:outline-none focus:ring focus:ring-custom-100">
        <i class="fas fa-plus text-xs"></i>
        <span>Create Blog</span>
      </a>
    </section>

  {% else %}
    <!-- Results -->
    <section class="mb-6">
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {% foreach ($blogs as $blog): %}
        <div>
          <article class="flex flex-col h-full overflow-hidden bg-white border rounded-lg shadow-sm border-slate-200 dark:bg-zink-700 dark:border-zink-600">
            {% if blog.banner_path|isset %}
            <a href="/dashboard/blogs/{{ blog.id }}/show" class="relative block overflow-hidden aspect-[21/9]">
              <img
                src="{{ blog.banner_path }}"
                alt="{{ blog.blog_name }} cover"
                class="object-cover w-full h-full">
            </a>
            {% endif %}

            <div class="flex-1 p-4 md:p-5">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <h2 class="mb-1 text-sm font-semibold leading-5 text-slate-900 dark:text-zink-100">
                    <a href="/dashboard/blogs/{{ blog.id }}/show"
                       class="transition-colors duration-150 hover:text-custom-500">
                      {{ blog.blog_name }}
                    </a>
                  </h2>
                  <p class="text-xs text-slate-500 truncate dark:text-zink-300">
                    {{ blog.url }}
                  </p>
                </div>

                <div class="shrink-0">
                  <?php if ($blog['status'] === 'published') { ?>
                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/40 dark:border-green-800">
                      Published
                    </span>
                  <?php } elseif ($blog['status'] === 'draft') { ?>
                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-slate-100 text-slate-700 border border-slate-200 dark:bg-zink-800 dark:text-zink-100 dark:border-zink-600">
                      Draft
                    </span>
                  <?php } elseif ($blog['status'] === 'archived') { ?>
                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-slate-800 text-slate-100 border border-slate-900">
                      Archived
                    </span>
                  <?php } ?>
                </div>
              </div>

              {% if blog.description|isset %}
              <p class="mt-2 text-sm leading-5 text-slate-600 line-clamp-3 dark:text-zink-200">
                <?= htmlspecialchars(truncate(strip_tags($blog['description']), 160), ENT_QUOTES, 'UTF-8'); ?>
              </p>
              {% endif %}

              <div class="flex flex-wrap gap-2 mt-4">
                <a href="/dashboard/blogs/{{ blog.id }}/edit"
                   class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium transition-all duration-150 ease-linear border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring focus:ring-slate-100 dark:bg-zink-800 dark:text-zink-100 dark:border-zink-600 dark:hover:bg-zink-700">
                  <i class="fas fa-pen text-[11px]"></i>
                  <span>Edit</span>
                </a>

                <a href="/dashboard/blogs/{{ blog.id }}/show"
                   class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium transition-all duration-150 ease-linear border rounded-md text-custom-600 bg-custom-50 border-custom-100 hover:bg-custom-100 hover:text-custom-700 focus:outline-none focus:ring focus:ring-custom-100">
                  <i class="fas fa-eye text-[11px]"></i>
                  <span>View</span>
                </a>

                <a href="/dashboard/blogs/{{ blog.id }}/users"
                   class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium transition-all duration-150 ease-linear border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring focus:ring-slate-100 dark:bg-zink-800 dark:text-zink-100 dark:border-zink-600 dark:hover:bg-zink-700">
                  <i class="fas fa-users text-[11px]"></i>
                  <span>Manage Team</span>
                </a>

                <form
                  method="post"
                  action="/dashboard/blogs/{{ blog.id }}/delete"
                  onsubmit="return confirm('Delete this blog?');">
                  <input type="hidden" name="_method" value="DELETE">
                  <input type="hidden" name="_token" value="{{ csrf_token }}">
                  <button
                    type="submit"
                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-red-600 transition-all duration-150 ease-linear bg-red-50 border border-red-200 rounded-md hover:bg-red-100 hover:text-red-700 focus:outline-none focus:ring focus:ring-red-100">
                    <i class="fas fa-trash text-[11px]"></i>
                    <span>Delete</span>
                  </button>
                </form>
              </div>
            </div>

            <div class="flex items-center justify-between px-4 py-3 text-xs border-t bg-slate-50 border-slate-200 dark:bg-zink-800 dark:border-zink-600">
              <span class="text-slate-500 dark:text-zink-300">
                Updated {{ blog.updated_at }}
              </span>
              <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-custom-50 text-custom-700 border border-custom-100">
                  {{ blog.post_count }} posts
                </span>
                {% if blog.follower_count|isset %}
                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-sky-50 text-sky-700 border border-sky-200">
                  {{ blog.follower_count }} followers
                </span>
                {% endif %}
              </div>
            </div>
          </article>
        </div>
        {% endforeach %}
      </div>

      <!-- Pagination placeholder -->
      <!-- Dev note: We should replace this with a shared pagination partial so we do not duplicate pagination markup across views. -->
    </section>
  {% endif %}

  <div class="flex flex-col gap-2 py-4 md:flex-row md:items-center">
    <div class="flex items-center gap-2 shrink-0">
      <a href="/dashboard/blogs/new"
         class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white transition-all duration-150 ease-linear bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 hover:border-custom-600 focus:outline-none focus:ring focus:ring-custom-100">
        <i data-lucide="book-plus"></i>
        <span>Create New Blog</span>
      </a>
    </div>
  </div>

</div>
{% endblock %}
