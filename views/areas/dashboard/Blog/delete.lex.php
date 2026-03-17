{% extends "back.lex.php" %}

{% block title %}Delete Blog - {{ blog.blog_name }}{% endblock %}

{% block body %}

<div class="container-fluid group-data-[content=boxed]:max-w-boxed mx-auto">
    <!-- Warning Card -->
    <div class="card">
        <div class="card-body">
            
            <!-- Critical Warning Alert -->
            <div class="px-4 py-3 mb-6 text-sm border border-red-200 rounded-md bg-red-50 dark:bg-red-400/20 dark:border-red-500/50">
                <div class="flex items-start gap-3">
                    <i data-lucide="alert-triangle" class="size-5 text-red-500 dark:text-red-400 shrink-0 mt-0.5"></i>
                    <div>
                        <p class="mb-1 font-semibold text-red-800 dark:text-red-300">
                            You are about to permanently delete "{{ blog.blog_name ?? 'this blog' }}"
                        </p>
                        <p class="mb-0 text-red-700 dark:text-red-400">
                            This action cannot be undone. All content will be permanently removed from our servers.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-3">
                <div class="p-4 border border-slate-200 rounded-md dark:border-zink-500">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center size-12 rounded-md bg-red-100 dark:bg-red-500/20">
                            <i data-lucide="file-text" class="size-6 text-red-500"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 text-15">{{ stats.postCount ?? 0 }}</h6>
                            <p class="text-slate-500 dark:text-zink-200">Posts to Delete</p>
                        </div>
                    </div>
                </div>
                
                <div class="p-4 border border-slate-200 rounded-md dark:border-zink-500">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center size-12 rounded-md bg-red-100 dark:bg-red-500/20">
                            <i data-lucide="message-square" class="size-6 text-red-500"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 text-15">{{ stats.commentCount ?? 0 }}</h6>
                            <p class="text-slate-500 dark:text-zink-200">Comments to Delete</p>
                        </div>
                    </div>
                </div>
                
                <div class="p-4 border border-slate-200 rounded-md dark:border-zink-500">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center size-12 rounded-md bg-red-100 dark:bg-red-500/20">
                            <i data-lucide="users" class="size-6 text-red-500"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 text-15">{{ stats.collaboratorCount ?? 0 }}</h6>
                            <p class="text-slate-500 dark:text-zink-200">Collaborators Affected</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- What will be deleted list -->
            <h6 class="mb-3 text-15">What will be permanently deleted:</h6>
            <ul class="mb-6 space-y-2 text-slate-600 dark:text-zink-200">
                <li class="flex items-start gap-2">
                    <i data-lucide="x" class="size-4 text-red-500 mt-1 shrink-0"></i>
                    <span>All {{ stats.postCount ?? 0 }} posts and their content</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="x" class="size-4 text-red-500 mt-1 shrink-0"></i>
                    <span>All {{ stats.commentCount ?? 0 }} comments from readers</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="x" class="size-4 text-red-500 mt-1 shrink-0"></i>
                    <span>Blog settings, theme, and customizations</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="x" class="size-4 text-red-500 mt-1 shrink-0"></i>
                    <span>All uploaded files (images, banners, logos)</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="x" class="size-4 text-red-500 mt-1 shrink-0"></i>
                    <span>Collaborator access for {{ stats.collaboratorCount ?? 0 }} users</span>
                </li>
            </ul>

            <!-- Deletion Form -->
            <form method="POST" action="/dashboard/blogs/{{ blog.id }}/delete" class="max-w-md">
                {{ csrf_field() }}

                <div class="mb-4">
                    <label for="password" class="inline-block mb-2 text-base font-medium">
                        Confirm Your Password
                    </label>
                    <input type="password" 
                        id="password" 
                        name="password" 
                        class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 disabled:bg-slate-100 dark:disabled:bg-zink-600 disabled:border-slate-300 dark:disabled:border-zink-500 dark:disabled:text-zink-200 disabled:text-slate-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password">
                    <p class="mt-1 text-sm text-slate-400 dark:text-zink-200">
                        Password confirmation is required for security.
                    </p>
                </div>

                <div class="flex items-start gap-2 mb-6">
                    <input type="checkbox"
                        class="size-4 border rounded-sm appearance-none cursor-pointer bg-slate-100 border-slate-200 dark:bg-zink-600 dark:border-zink-500 checked:bg-red-500 checked:border-red-500 dark:checked:bg-red-500 dark:checked:border-red-500 mt-0.5"
                        id="confirmDelete" 
                        required>
                    <label for="confirmDelete" class="inline-block text-sm cursor-pointer text-slate-500 dark:text-zink-200">
                        I understand that this action is permanent and "<strong>{{ blog.blog_name ?? 'this blog' }}</strong>" 
                        cannot be recovered after deletion
                    </label>
                </div>

                <div class="flex gap-2">
                    <a href="/dashboard/blog/{{ blog.id }}/edit" 
                        class="text-slate-500 btn bg-slate-200 border-slate-200 hover:text-slate-600 hover:bg-slate-300">
                        <i data-lucide="arrow-left" class="inline-block size-4 mr-1"></i>
                        Cancel
                    </a>
                    <button type="submit"
                        class="text-white btn bg-red-500 border-red-500 hover:text-white hover:bg-red-600 hover:border-red-600">
                        <i data-lucide="trash-2" class="inline-block size-4 mr-1"></i>
                        Yes, Permanently Delete This Blog
                    </button>
                </div>
            </form>

        </div>
    </div>

</div>

{% endblock %}
