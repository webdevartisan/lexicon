{% extends "back.lex.php" %}
{% block title %}{{ t('dashboard.pageTitle') }}{% endblock %}
{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    {% if hasNoBlogs|notempty %}
    <!-- Empty State: No Blogs Yet -->
    <div class="flex items-center justify-center min-h-[60vh]">
        <div class="text-center max-w-md px-6">
            <div class="mb-6">
                <i class="inline-flex items-center justify-center size-16 text-custom-500 bg-custom-100 dark:bg-custom-500/20 rounded-full"
                    data-lucide="book-open"></i>
            </div>

            <h2 class="text-2xl font-semibold text-slate-900 dark:text-zink-50 mb-3">
                {{ t('dashboard.emptyState.title') }}
            </h2>

            <p class="text-slate-600 dark:text-zink-300 mb-6">
                {{ t('dashboard.emptyState.description') }}
            </p>

            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                {% set createFirstBlogLabel = t('dashboard.emptyState.actions.createFirstBlog') %}
                {% cmp="btn" href="/dashboard/blog/new" variant="blue" icon="plus" label="tset" %}


                <a href="/help/getting-started"
                    class="inline-flex items-center justify-center px-6 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                    <i class="size-4 mr-2" data-lucide="help-circle"></i>
                    {{ t('dashboard.emptyState.actions.gettingStarted') }}
                </a>
            </div>

            <!-- Optional: Quick Tips -->
            <div class="mt-8 p-4 bg-slate-50 dark:bg-zink-700 rounded-lg text-left">
                <h3 class="font-semibold text-slate-900 dark:text-zink-50 mb-3 flex items-center gap-2">
                    <i class="size-4" data-lucide="lightbulb"></i>
                    {{ t('dashboard.emptyState.quickTips.title') }}
                </h3>
                <ul class="space-y-2 text-sm text-slate-600 dark:text-zink-300">
                    <li class="flex items-start gap-2">
                        <i class="size-4 mt-0.5 text-custom-500" data-lucide="check"></i>
                        <span>{{ t('dashboard.emptyState.quickTips.tip1') }}</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="size-4 mt-0.5 text-custom-500" data-lucide="check"></i>
                        <span>{{ t('dashboard.emptyState.quickTips.tip2') }}</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="size-4 mt-0.5 text-custom-500" data-lucide="check"></i>
                        <span>{{ t('dashboard.emptyState.quickTips.tip3') }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {% else %}

    <!-- Normal Dashboard Content -->
    <div class="grid gap-6 lg:grid-cols-3">
        <form action="/dashboard/setDefaultBlog" method="POST">
            {{ csrf_field() }}
            {% set blogLabel = t('dashboard.blogSelector.label') %}
            {% cmp="select" options="{$blogIds}" selectedKey="{$selectedBlogId}" label="{$blogLabel}"
            onchange="this.form.submit()" %}
        </form>

        <!-- Tabs Navigation -->
        <div class="lg:col-span-2 flex items-end justify-end">
            <div class="inline-flex rounded-lg border border-slate-200 dark:border-zink-500 p-1" role="tablist">
                <button type="button"
                    class="tab-btn px-4 py-2 text-sm font-medium rounded-md transition-colors text-slate-600 hover:text-slate-900 dark:text-zink-200 dark:hover:text-zink-50"
                    data-tab="draft" aria-selected="false">
                    {{ t('dashboard.tabs.draft') }} <span class="ml-1 text-xs">({{ draftPagination.total_records }})</span>
                </button>
                <button type="button"
                    class="tab-btn px-4 py-2 text-sm font-medium rounded-md transition-colors text-slate-600 hover:text-slate-900 dark:text-zink-200 dark:hover:text-zink-50"
                    data-tab="pending" aria-selected="false">
                    {{ t('dashboard.tabs.pending') }} <span class="ml-1 text-xs">({{ pendingPagination.total_records }})</span>
                </button>
                <button type="button"
                    class="tab-btn px-4 py-2 text-sm font-medium rounded-md transition-colors text-slate-600 hover:text-slate-900 dark:text-zink-200 dark:hover:text-zink-50"
                    data-tab="published" aria-selected="true">
                    {{ t('dashboard.tabs.published') }} <span class="ml-1 text-xs">({{ publishedPagination.total_records }})</span>
                </button>
                <button type="button"
                    class="tab-btn px-4 py-2 text-sm font-medium rounded-md transition-colors text-slate-600 hover:text-slate-900 dark:text-zink-200 dark:hover:text-zink-50"
                    data-tab="archived" aria-selected="false">
                    {{ t('dashboard.tabs.archived') }} <span class="ml-1 text-xs">({{ archivedPagination.total_records }})</span>
                </button>
            </div>
        </div>
        
        {% cache 'dashboard:newpost-button' ttl=3600 %}
        <div class="">
            {% set newPostLabel = t('dashboard.actions.newPost') %}
            {% cmp="btn" href="/dashboard/post/new" variant="blue" icon="plus" label="{$newPostLabel}" %}
        </div>
        {% endcache %}
    </div>

    <h1 class="text-lg mt-6 font-semibold text-slate-900 truncate dark:text-zink-100">
        {{ t('dashboard.sections.posts') }}
    </h1>

    <!-- Tab Content: Drafts -->
    <div class="tab-content hidden" data-content="draft">
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4 mt-5">
            {% if posts.draft|notempty %}
            {% foreach ($posts['draft'] as $post): %}
            {% cmp="post-card" post="{$post}" blogSlug="{$blogSlug}" %}
            {% endforeach %}
            {% endif %}
        </div>

        <!-- Pagination for Draft Tab -->
        <div class="mt-5 mb-5">
            {% cmp="paginator" pagination="{$draftPagination}" pageParam="draftPage" query="{$searchQuery}" %}
        </div>
    </div>

    <!-- Tab Content: Pending -->
    <div class="tab-content hidden" data-content="pending">
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4 mt-5">
            {% if posts.pending|notempty %}
            {% foreach ($posts['pending'] as $post): %}
            {% cmp="post-card" post="{$post}" blogSlug="{$blogSlug}" %}
            {% endforeach %}
            {% endif %}
        </div>

        <!-- Pagination for Pending Tab -->
        <div class="mt-5 mb-5">
            {% cmp="paginator" pagination="{$pendingPagination}" pageParam="pendingPage" query="{$searchQuery}" %}
        </div>
    </div>

    <!-- Tab Content: Published -->
    <div class="tab-content" data-content="published">
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4 mt-5">
            {% if posts.published|notempty %}
            {% foreach ($posts['published'] as $post): %}
            {% cmp="post-card" post="{$post}" blogSlug="{$blogSlug}" %}
            {% endforeach %}
            {% endif %}
        </div>

        <!-- Pagination for Published Tab -->
        <div class="mt-5 mb-5">
            {% cmp="paginator" pagination="{$publishedPagination}" pageParam="publishedPage" query="{$searchQuery}" %}
        </div>
    </div>

    <!-- Tab Content: Archived -->
    <div class="tab-content hidden" data-content="archived">
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4 mt-5">
            {% if posts.archived|notempty %}
            {% foreach ($posts['archived'] as $post): %}
            {% cmp="post-card" post="{$post}" blogSlug="{$blogSlug}" %}
            {% endforeach %}
            {% endif %}
        </div>

        <!-- Pagination for Archived Tab -->
        <div class="mt-5 mb-5">
            {% cmp="paginator" pagination="{$archivedPagination}" pageParam="archivedPage" query="{$searchQuery}" %}
        </div>
    </div>
</div>
{% endif %}
{% endblock %}
{% block scripts %}
<script src='/cp-assets/js/tooltip.js'></script>

<script>
    (function () {
        var savedTab = sessionStorage.getItem('activeTab');

        if(!savedTab) {
            sessionStorage.setItem('activeTab', 'published');
            savedTab = 'published';
        }

        // restore tab UI immediately to prevent flicker
        if (savedTab) {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');

            // find and activate saved tab
            tabButtons.forEach(btn => {
                const isActive = btn.getAttribute('data-tab') === savedTab;

                if (isActive) {
                    btn.classList.add('bg-white', 'text-slate-900', 'dark:bg-zink-600', 'dark:text-zink-50');
                    btn.classList.remove('text-slate-600', 'hover:text-slate-900', 'dark:text-zink-200', 'dark:hover:text-zink-50');
                    btn.setAttribute('aria-selected', 'true');
                } else {
                    btn.classList.remove('bg-white', 'text-slate-900', 'dark:bg-zink-600', 'dark:text-zink-50');
                    btn.classList.add('text-slate-600', 'hover:text-slate-900', 'dark:text-zink-200', 'dark:hover:text-zink-50');
                    btn.setAttribute('aria-selected', 'false');
                }
            });

            // show/hide content based on saved tab
            tabContents.forEach(content => {
                const contentTab = content.getAttribute('data-content');
                if (contentTab === savedTab) {
                    content.classList.remove('hidden');
                } else {
                    content.classList.add('hidden');
                }
            });
        }

        // setup click handlers and form field after DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            const statusField = document.querySelector('input[name="searchPostStatus"]');

            // update form field with saved state
            if (savedTab && statusField) {
                statusField.value = savedTab;
            }

            // handle tab click events
            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const targetTab = button.getAttribute('data-tab');

                    sessionStorage.setItem('activeTab', targetTab);


                    // update form field on tab switch
                    if (statusField) {
                        statusField.value = targetTab;
                    }

                    tabButtons.forEach(btn => {
                        btn.classList.remove('bg-white', 'text-slate-900', 'dark:bg-zink-600', 'dark:text-zink-50');
                        btn.classList.add('text-slate-600', 'hover:text-slate-900', 'dark:text-zink-200', 'dark:hover:text-zink-50');
                        btn.setAttribute('aria-selected', 'false');
                    });

                    button.classList.add('bg-white', 'text-slate-900', 'dark:bg-zink-600', 'dark:text-zink-50');
                    button.classList.remove('text-slate-600', 'hover:text-slate-900', 'dark:text-zink-200', 'dark:hover:text-zink-50');
                    button.setAttribute('aria-selected', 'true');

                    tabContents.forEach(content => {
                        content.classList.add('hidden');
                    });

                    const targetContent = document.querySelector(`[data-content="${targetTab}"]`);
                    if (targetContent) {
                        targetContent.classList.remove('hidden');
                    }
                });
            });
        });
    })();

    
</script>
{% endblock %}