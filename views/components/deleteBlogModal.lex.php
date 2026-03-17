<?php
// remove the stats from the component - they'll be fetched dynamically
$blogName = !empty($blog['blog_name']) ? e($blog['blog_name']) : 'this blog';
$blogId = !empty($blog['id']) ? (int) $blog['id'] : 0;
$blogSlug = !empty($blog['blog_slug']) ? e($blog['blog_slug']) : '';
?>

{% set passwordLabel = t('modals.deleteBlog.form.passwordLabel') %}
{% set passwordPlaceholder = t('modals.deleteBlog.form.passwordPlaceholder') %}
{% set passwordUnderlabel = t('modals.deleteBlog.form.passwordUnderlabel') %}

<div id="deleteBlogModal" modal-center
    class="fixed flex flex-col hidden transition-all duration-300 ease-in-out left-2/4 z-drawer -translate-x-2/4 -translate-y-2/4 show">
    <div class="w-screen md:w-[32rem] bg-white shadow rounded-md dark:bg-zink-600 flex flex-col h-full">
        
        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b border-red-200 bg-red-500 dark:bg-red-600 dark:border-red-700">
            <h5 class="text-16 text-white flex items-center gap-2">
                <i data-lucide="alert-triangle" class="size-5"></i>
                {{ t('modals.deleteBlog.title') }}
            </h5>
            <button data-modal-close="deleteBlogModal"
                class="transition-all duration-200 ease-linear text-white hover:text-red-100">
                <i data-lucide="x" class="size-5"></i>
            </button>
        </div>

        <!-- Content -->
        <div class="max-h-[calc(theme('height.screen')_-_180px)] p-4 overflow-y-auto">
            
            <!-- Warning Alert -->
            <div class="px-4 py-3 mb-3 text-sm text-red-800 border border-red-200 rounded-md bg-red-50 dark:bg-red-400/20 dark:border-red-500/50 dark:text-red-400">
                <span class="font-bold">{{ t('modals.deleteBlog.warning.title') }}</span> You are about to permanently delete "<strong><?= $blogName ?></strong>". {{ t('modals.deleteBlog.warning.irreversible') }}
            </div>

            <!-- Impact Summary with Loading State -->
            <div class="p-3 mb-4 border border-orange-200 rounded-md bg-orange-50 dark:bg-orange-400/10 dark:border-orange-500/30">
                <h6 class="mb-2 text-sm font-semibold text-orange-900 dark:text-orange-400">
                    {{ t('modals.deleteBlog.impact.title') }}
                </h6>
                
                <!-- Loading State -->
                <div id="statsLoading" class="text-center py-4">
                    <i data-lucide="loader-2" class="inline-block size-6 text-orange-500 animate-spin"></i>
                    <p class="text-xs text-slate-500 dark:text-zink-300 mt-2">{{ t('modals.deleteBlog.impact.loading') }}</p>
                </div>

                <!-- Stats Grid (hidden initially) -->
                <div id="statsGrid" class="grid grid-cols-3 gap-2 text-center hidden">
                    <div class="p-2 bg-white rounded dark:bg-zink-700">
                        <div id="postCount" class="text-xl font-bold text-red-600 dark:text-red-400">0</div>
                        <div class="text-xs text-slate-500 dark:text-zink-300">{{ t('modals.deleteBlog.impact.stats.posts') }}</div>
                    </div>
                    <div class="p-2 bg-white rounded dark:bg-zink-700">
                        <div id="commentCount" class="text-xl font-bold text-red-600 dark:text-red-400">0</div>
                        <div class="text-xs text-slate-500 dark:text-zink-300">{{ t('modals.deleteBlog.impact.stats.comments') }}</div>
                    </div>
                    <div class="p-2 bg-white rounded dark:bg-zink-700">
                        <div id="collaboratorCount" class="text-xl font-bold text-red-600 dark:text-red-400">0</div>
                        <div class="text-xs text-slate-500 dark:text-zink-300">{{ t('modals.deleteBlog.impact.stats.collaborators') }}</div>
                    </div>
                </div>

                <!-- Error State -->
                <div id="statsError" class="text-center py-4 hidden">
                    <i data-lucide="alert-circle" class="inline-block size-6 text-red-500"></i>
                    <p class="text-xs text-red-600 dark:text-red-400 mt-2">{{ t('modals.deleteBlog.impact.error') }}</p>
                </div>
            </div>

            <!-- What will be permanently deleted -->
            <h6 class="mb-2 text-15 text-slate-700 dark:text-zink-200">{{ t('modals.deleteBlog.deletionList.title') }}</h6>
            <ul class="mb-4 space-y-1 text-sm text-slate-500 dark:text-zink-300">
                <li class="flex items-start gap-2">
                    <i data-lucide="x" class="size-4 text-red-500 mt-0.5 shrink-0"></i>
                    <span><strong id="postCountText">All posts</strong> and their content</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="x" class="size-4 text-red-500 mt-0.5 shrink-0"></i>
                    <span><strong id="commentCountText">All comments</strong> from readers</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="x" class="size-4 text-red-500 mt-0.5 shrink-0"></i>
                    <span>{{ t('modals.deleteBlog.deletionList.items.settings') }}</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="x" class="size-4 text-red-500 mt-0.5 shrink-0"></i>
                    <span>{{ t('modals.deleteBlog.deletionList.items.images') }}</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="x" class="size-4 text-red-500 mt-0.5 shrink-0"></i>
                    <span id="collaboratorCountText">Collaborator access</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="x" class="size-4 text-red-500 mt-0.5 shrink-0"></i>
                    <span>{{ t('modals.deleteBlog.deletionList.items.url') }} (<?= $blogSlug ?>)</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="x" class="size-4 text-red-500 mt-0.5 shrink-0"></i>
                    <span>{{ t('modals.deleteBlog.deletionList.items.seo') }}</span>
                </li>
            </ul>

            <!-- Data Protection Notice -->
            <div class="p-3 mb-4 bg-slate-100 dark:bg-zink-500 border border-slate-200 dark:border-zink-400 rounded-md">
                <p class="text-xs text-slate-600 dark:text-zink-300 mb-0">
                    <strong>{{ t('modals.deleteBlog.dataProtection.title') }}</strong> {{ t('modals.deleteBlog.dataProtection.message') }}
                </p>
            </div>

            <!-- Delete Form -->
            <form method="POST" action="/dashboard/blog/<?= $blogId ?>/destroy" id="deleteBlogForm">
                {{ csrf_field() }}

                {% cmp="input" 
                    type="password" 
                    name="password"
                    label="{$passwordLabel}" 
                    required="true" 
                    placeholder="{$passwordPlaceholder}" 
                    underlabel="{$passwordUnderlabel}" 
                    autocomplete="current-password"
                %}

                <!-- Confirmation Checkbox -->
                <div class="flex items-start gap-2 mb-4">
                    <input type="checkbox"
                        class="size-4 border rounded-sm appearance-none bg-slate-100 border-slate-200 dark:bg-zink-600 dark:border-zink-500 checked:bg-red-500 checked:border-red-500 dark:checked:bg-red-500 dark:checked:border-red-500 cursor-pointer mt-0.5"
                        id="confirmDeleteBlogCheck" required>
                    <label for="confirmDeleteBlogCheck"
                        class="inline-block text-sm font-medium align-middle cursor-pointer text-slate-500 dark:text-zink-300">
                        {{ t('modals.deleteBlog.form.confirmationText.part1') }} "<strong><?= $blogName ?></strong>" {{ t('modals.deleteBlog.form.confirmationText.part2') }} <strong class="text-red-600 dark:text-red-400">{{ t('modals.deleteBlog.form.confirmationText.permanentlyDeleted') }}</strong> {{ t('modals.deleteBlog.form.confirmationText.part3') }}
                    </label>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-2 p-4 mt-auto border-t border-slate-200 dark:border-zink-500">
            <button type="button" data-modal-close="deleteBlogModal"
                class="text-slate-500 btn bg-slate-200 border-slate-200 hover:text-slate-600 hover:bg-slate-300 hover:border-slate-300 focus:text-slate-600 focus:bg-slate-300 focus:border-slate-300 focus:ring focus:ring-slate-100 active:text-slate-600 active:bg-slate-300 active:border-slate-300 active:ring active:ring-slate-100 dark:bg-zink-600 dark:hover:bg-zink-500 dark:border-zink-600 dark:hover:border-zink-500 dark:text-zink-200 dark:ring-zink-400/50">
                {{ t('modals.deleteBlog.actions.cancel') }}
            </button>
            <button type="submit" form="deleteBlogForm"
                class="text-white btn bg-red-500 border-red-500 hover:text-white hover:bg-red-600 hover:border-red-600 focus:text-white focus:bg-red-600 focus:border-red-600 focus:ring focus:ring-red-100 active:text-white active:bg-red-600 active:border-red-600 active:ring active:ring-red-100 dark:ring-red-400/20">
                <i data-lucide="trash-2" class="inline-block size-4 mr-1"></i>
                {{ t('modals.deleteBlog.actions.confirmDelete') }} "<?= $blogName ?>"
            </button>
        </div>

    </div>
</div>
