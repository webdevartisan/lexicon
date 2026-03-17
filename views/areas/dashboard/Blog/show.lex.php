{% extends "back.lex.php" %}


{% block title %}{{ blog.blog_name }} · Overview{% endblock %}


{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">


  <!-- Page header: blog identity + actions -->
  <div class="flex flex-col gap-3 py-4 md:flex-row md:items-center">
    <div class="flex items-start gap-3 grow">
      {% if blog.logo_path|notempty %}
      <div class="flex items-center justify-center w-12 h-12 rounded-md bg-slate-100 dark:bg-zink-700 shrink-0">
        <img src="{{ blog.logo_path }}" alt="{{ blog.blog_name }} logo" class="object-contain w-10 h-10 rounded">
      </div>
      {% endif %}


      <div class="min-w-0">
        <h1 class="text-lg font-semibold text-slate-900 truncate dark:text-zink-100">
          {{ blog.blog_name }}
        </h1>
        <p class="text-xs text-slate-500 dark:text-zink-300">
          {{ blog.url }}
        </p>


        <div class="flex flex-wrap items-center gap-2 mt-2 text-[11px]">
          <?php if ($blog['status'] == 'published') { ?>
          <span class="inline-flex items-center px-2 py-0.5 font-medium rounded-full bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/40 dark:border-green-800">
            Published
          </span>
          <?php } elseif ($blog['status'] == 'draft') { ?>
          <span class="inline-flex items-center px-2 py-0.5 font-medium rounded-full bg-slate-100 text-slate-700 border border-slate-200 dark:bg-zink-800 dark:text-zink-100 dark:border-zink-600">
            Draft
          </span>
          <?php } elseif ($blog['status'] == 'archived') { ?>
          <span class="inline-flex items-center px-2 py-0.5 font-medium rounded-full bg-slate-800 text-slate-100 border border-slate-900">
            Archived
          </span>
          <?php } ?>


          <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-custom-50 text-custom-700 border border-custom-100">
            {{ blog.post_count }} posts
          </span>


          {% if blog.follower_count|notempty %}
          <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 border border-sky-200">
            {{ blog.follower_count }} followers
          </span>
          {% endif %}
        </div>
      </div>
    </div>


    <div class="flex flex-wrap items-center gap-2 shrink-0">
      <a href="/blog/{{ blog.blog_slug }}"
         target="_blank" rel="noopener"
         class="inline-flex items-center gap-2 px-3 py-2 text-xs font-medium transition-all duration-150 ease-linear border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring focus:ring-slate-100 dark:bg-zink-800 dark:text-zink-100 dark:border-zink-600 dark:hover:bg-zink-700">
        <i class="fas fa-external-link-alt text-[11px]"></i>
        <span>View live</span>
      </a>


      <a href="/dashboard/blogs/{{ blog.id }}/edit"
         class="inline-flex items-center gap-2 px-3 py-2 text-xs font-medium transition-all duration-150 ease-linear border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring focus:ring-slate-100 dark:bg-zink-800 dark:text-zink-100 dark:border-zink-600 dark:hover:bg-zink-700">
        <i class="fas fa-pen text-[11px]"></i>
        <span>Edit blog</span>
      </a>


      <a href="/dashboard/blogs"
         class="inline-flex items-center gap-2 px-3 py-2 text-xs font-medium transition-all duration-150 ease-linear border rounded-md text-slate-600 bg-slate-50 border-slate-200 hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring focus:ring-slate-100 dark:bg-zink-800 dark:text-zink-100 dark:border-zink-600 dark:hover:bg-zink-700">
        <i class="fas fa-arrow-left text-[11px]"></i>
        <span>Back to blogs</span>
      </a>
    </div>
  </div>


  <!-- Top content: banner + summary + stats -->
  <div class="grid gap-6 mb-6 lg:grid-cols-3">
    <!-- Left: banner + description -->
    <section class="lg:col-span-2 bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden dark:bg-zink-700 dark:border-zink-600">
      {% if blog.banner_path|notempty %}
      <div class="relative overflow-hidden aspect-[21/5] bg-slate-100 dark:bg-zink-800">
        <img src="{{ blog.banner_path }}" alt="{{ blog.blog_name }} banner" class="object-cover w-full h-full">
      </div>
      {% endif %}
      <div class="p-4 md:p-5 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <p class="text-xs text-slate-500 dark:text-zink-300">
            Created {{ blog.created_at }} · Updated {{ blog.updated_at }}
          </p>
          <?php if (!empty($blog['locale']) || !empty($blog['timezone'])) { ?>
          <div class="flex flex-wrap items-center gap-2 text-[11px] text-slate-500 dark:text-zink-300">
            {% if blog.locale|notempty %}
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-50 border border-slate-200 dark:bg-zink-800 dark:border-zink-600">
              <i class="fas fa-language text-[10px]"></i>
              <span>{{ blog.locale|upper }}</span>
            </span>
            {% endif %}
            {% if blog.timezone %}
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-50 border border-slate-200 dark:bg-zink-800 dark:border-zink-600">
              <i class="fas fa-clock text-[10px]"></i>
              <span>{{ blog.timezone }}</span>
            </span>
            {% endif %}
          </div>
          <?php } ?>
        </div>


        {% if blog.description %}
        <p class="text-sm leading-6 text-slate-700 dark:text-zink-100">
          {{ blog.description }}
        </p>
        {% else %}
        <p class="text-sm italic text-slate-400 dark:text-zink-400">
          No description has been provided for this blog yet.
        </p>
        {% endif %}
      </div>
    </section>


    <!-- Right: metrics / quick stats -->
    <aside class="space-y-4">
      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Overview</h2>
        </div>
        <div class="p-4 space-y-3 text-xs text-slate-600 dark:text-zink-200">
          <div class="flex items-center justify-between">
            <span>Posts</span>
            <span class="font-semibold text-slate-900 dark:text-zink-100">{{ blog.post_count }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span>Followers</span>
            <span class="font-semibold text-slate-900 dark:text-zink-100">{{ blog.follower_count ?? 0 }}</span>
          </div>
          {% if stats.views|notempty %}
          <div class="flex items-center justify-between">
            <span>Views (all time)</span>
            <span class="font-semibold text-slate-900 dark:text-zink-100">{{ stats.views }}</span>
          </div>
          {% endif %}
          {% if stats.views_30d|notempty %}
          <div class="flex items-center justify-between">
            <span>Views (30 days)</span>
            <span class="font-semibold text-slate-900 dark:text-zink-100">{{ stats.views_30d }}</span>
          </div>
          {% endif %}
        </div>
      </section>


      <section class="bg-slate-50 border border-dashed border-slate-200 rounded-lg p-4 text-[11px] text-slate-600 dark:bg-zink-800 dark:border-zink-600 dark:text-zink-200">
        {# Dev note: We should reuse this small helper panel across show/edit pages to keep UX consistent and DRY. #}
        <h3 class="mb-1 text-sm font-semibold text-slate-900 dark:text-zink-100">Tips</h3>
        <p>
          Use this overview to monitor your publishing activity. Keep your banner and description up to date so visitors quickly understand what this blog is about.
        </p>
      </section>
    </aside>
  </div>


  <!-- Posts list -->
  <section class="mb-8">
    <div class="flex items-center justify-between gap-2 mb-3">
      <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">
        Recent posts
      </h2>
      <a href="/dashboard/posts/new?blog_id={{ blog.id }}"
         class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-white transition-all duration-150 ease-linear bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 hover:border-custom-600 focus:outline-none focus:ring focus:ring-custom-100">
        <i class="fas fa-plus text-[11px]"></i>
        <span>Create post</span>
      </a>
    </div>


    {% if posts|empty %}
      <div class="flex flex-col items-center justify-center py-10 text-center bg-white border border-dashed rounded-lg border-slate-200 dark:bg-zink-700 dark:border-zink-600">
        <p class="mb-1 text-sm font-semibold text-slate-900 dark:text-zink-100">No posts yet</p>
        <p class="mb-3 text-xs text-slate-500 dark:text-zink-300">
          Start by creating your first post in this blog.
        </p>
        <a href="/dashboard/posts/new?blog_id={{ blog.id }}"
           class="inline-flex items-center gap-2 px-4 py-2 text-xs font-medium text-white transition-all duration-150 ease-linear bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 hover:border-custom-600 focus:outline-none focus:ring focus:ring-custom-100">
          <i class="fas fa-plus text-[11px]"></i>
          <span>Create post</span>
        </a>
      </div>
    {% else %}
      <div class="overflow-hidden bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
        <div class="overflow-x-auto">
          <table class="min-w-full text-xs divide-y divide-slate-200 dark:divide-zink-600">
            <thead class="bg-slate-50 dark:bg-zink-800">
              <tr>
                <th class="px-4 py-2 text-left font-semibold text-slate-600 dark:text-zink-200">Title</th>
                <th class="px-4 py-2 text-left font-semibold text-slate-600 dark:text-zink-200">Status</th>
                <th class="px-4 py-2 text-left font-semibold text-slate-600 dark:text-zink-200">Published / Updated</th>
                <th class="px-4 py-2 text-left font-semibold text-slate-600 dark:text-zink-200">Excerpt</th>
                <th class="px-4 py-2 text-right font-semibold text-slate-600 dark:text-zink-200">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-zink-600">
              {% foreach posts as post %}
              <tr class="hover:bg-slate-50/70 dark:hover:bg-zink-800">
                <!-- Title -->
                <td class="px-4 py-2 align-top">
                  <div class="flex flex-col gap-0.5">
                    <a href="/dashboard/posts/{{ post.id }}/edit"
                       class="text-xs font-semibold text-slate-900 hover:text-custom-600 dark:text-zink-100">
                      {{ post.title }}
                    </a>
                    {% if post.slug|notempty %}
                    <span class="text-[11px] text-slate-500 dark:text-zink-300">
                      {{ post.slug }}
                    </span>
                    {% endif %}
                  </div>
                </td>


                <!-- Status -->
                <td class="px-4 py-2 align-top">
                  <?php if ($post['status'] === 'published') { ?>
                    <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium rounded-full bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/40 dark:border-green-800">
                      Published
                    </span>
                  <?php } elseif ($post['status'] === 'scheduled') { ?>
                    <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium rounded-full bg-sky-100 text-sky-700 border border-sky-200 dark:bg-sky-900/40 dark:border-sky-800">
                      Scheduled
                    </span>
                  <?php } else { ?>
                    <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium rounded-full bg-slate-100 text-slate-700 border border-slate-200 dark:bg-zink-800 dark:text-zink-100 dark:border-zink-600">
                      Draft
                    </span>
                  <?php } ?>
                </td>


                <!-- Published / Updated -->
                <td class="px-4 py-2 align-top text-[11px] text-slate-600 dark:text-zink-300">
                  <?= htmlspecialchars($post['published_at'] ?? $post['updated_at'], ENT_QUOTES, 'UTF-8') ?>
                </td>


                <!-- Excerpt -->
                <td class="px-4 py-2 align-top max-w-xs">
                  <p class="text-[11px] text-slate-600 line-clamp-3 dark:text-zink-200">
                    <?= htmlspecialchars($post['excerpt'] ?? $post['summary'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                  </p>
                </td>


                <!-- Actions -->
                <td class="px-4 py-2 align-top text-right">
                  <div class="flex flex-wrap justify-end gap-1">
                    <a href="/dashboard/posts/{{ post.id }}/edit"
                       class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-medium transition-all duration-150 ease-linear border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring focus:ring-slate-100 dark:bg-zink-800 dark:text-zink-100 dark:border-zink-600 dark:hover:bg-zink-700">
                      <i class="fas fa-pen text-[10px]"></i>
                      <span>Edit</span>
                    </a>


                    <?php if ($post['status'] === 'published') { ?>
                    <form method="post" action="/dashboard/posts/<?= htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8') ?>/unpublish">
                      <input type="hidden" name="_method" value="POST">
                      <input type="hidden" name="_token" value="{{ csrf_token }}">
                      <button
                        type="submit"
                        class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-medium text-amber-700 transition-all duration-150 ease-linear bg-amber-50 border border-amber-200 rounded-md hover:bg-amber-100 hover:text-amber-800 focus:outline-none focus:ring focus:ring-amber-100">
                        <i class="fas fa-eye-slash text-[10px]"></i>
                        <span>Unpublish</span>
                      </button>
                    </form>
                    <?php } else { ?>
                    <form method="post" action="/dashboard/posts/<?= htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8') ?>/publish">
                      <input type="hidden" name="_method" value="POST">
                      <input type="hidden" name="_token" value="{{ csrf_token }}">
                      <button
                        type="submit"
                        class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-medium text-green-700 transition-all duration-150 ease-linear bg-green-50 border border-green-200 rounded-md hover:bg-green-100 hover:text-green-800 focus:outline-none focus:ring focus:ring-green-100">
                        <i class="fas fa-check text-[10px]"></i>
                        <span>Publish</span>
                      </button>
                    </form>
                    <?php } ?>
                  </div>
                </td>
              </tr>
              {% endforeach %}
            </tbody>
          </table>
        </div>
      </div>
    {% endif %}
  </section>
</div>
{% endblock %}
