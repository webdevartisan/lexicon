<?php
$postCount = !empty($postCount) ? $postCount : 0;
$commentCount = !empty($commentCount) ? $commentCount : 0;
?>

<div id="deleteAccountModal" modal-center
    class="fixed flex flex-col hidden transition-all duration-300 ease-in-out left-2/4 z-drawer -translate-x-2/4 -translate-y-2/4 show">
    <div class="w-screen md:w-[30rem] bg-white shadow rounded-md dark:bg-zink-600 flex flex-col h-full">
        
        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b border-red-200 bg-red-500 dark:bg-red-600 dark:border-red-700">
            <h5 class="text-16 text-white flex items-center gap-2">
                <i data-lucide="alert-triangle" class="size-5"></i>
                Delete Account Permanently?
            </h5>
            <button data-modal-close="deleteAccountModal"
                class="transition-all duration-200 ease-linear text-white hover:text-red-100">
                <i data-lucide="x" class="size-5"></i>
            </button>
        </div>

        <!-- Content -->
        <div class="max-h-[calc(theme('height.screen')_-_180px)] p-4 overflow-y-auto">
            
            <!-- Warning Alert -->
            <div class="px-4 py-3 mb-3 text-sm text-yellow-800 border border-yellow-200 rounded-md bg-yellow-50 dark:bg-yellow-400/20 dark:border-yellow-500/50 dark:text-yellow-500">
                <span class="font-bold">Warning:</span> This action cannot be undone and is permanent.
            </div>

            <!-- What will be deleted -->
            <h6 class="mb-2 text-15 text-slate-700 dark:text-zink-200">What will be deleted:</h6>
            <ul class="mb-4 space-y-1 text-sm text-slate-500 dark:text-zink-300">
                <li class="flex items-start gap-2">
                    <i data-lucide="x" class="size-4 text-red-500 mt-0.5 shrink-0"></i>
                    <span>Your profile information (name, email, bio, location)</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="x" class="size-4 text-red-500 mt-0.5 shrink-0"></i>
                    <span>Your avatar and uploaded files</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="x" class="size-4 text-red-500 mt-0.5 shrink-0"></i>
                    <span>Your account preferences and settings</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="x" class="size-4 text-red-500 mt-0.5 shrink-0"></i>
                    <span>Your social media links</span>
                </li>
            </ul>

            <!-- What will be preserved -->
            <h6 class="mb-2 text-15 text-slate-700 dark:text-zink-200">What will be preserved:</h6>
            <ul class="mb-4 space-y-1 text-sm text-slate-500 dark:text-zink-300">
                <li class="flex items-start gap-2">
                    <i data-lucide="check" class="size-4 text-green-500 mt-0.5 shrink-0"></i>
                    <span>Your posts (<?= $postCount ?> posts) - attributed to "Deleted User"</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="check" class="size-4 text-green-500 mt-0.5 shrink-0"></i>
                    <span>Comments on your posts (<?= $commentCount ?> comments)</span>
                </li>
            </ul>

            <!-- GDPR Notice -->
            <div class="p-3 mb-4 bg-slate-100 dark:bg-zink-500 border border-slate-200 dark:border-zink-400 rounded-md">
                <p class="text-xs text-slate-600 dark:text-zink-300 mb-0">
                    <strong>GDPR Compliance:</strong> Your personal data will be permanently deleted
                    in accordance with data protection regulations. Content remains for platform integrity
                    without identifying information.
                </p>
            </div>

            <!-- Delete Form -->
            <form method="POST" action="/dashboard/delete-account" id="deleteAccountForm">
                {{ csrf_field() }}

                {% cmp="input" type="password" label="Password" required="true" placeholder="Enter your current password" underlabel="Password confirmation is required for security." %}

                <!-- Confirmation Checkbox -->
                <div class="flex items-start gap-2 mb-4">
                    <input type="checkbox"
                        class="size-4 border rounded-sm appearance-none bg-slate-100 border-slate-200 dark:bg-zink-600 dark:border-zink-500 checked:bg-custom-500 checked:border-custom-500 dark:checked:bg-custom-500 dark:checked:border-custom-500 cursor-pointer mt-0.5"
                        id="confirmDeleteCheck" required>
                    <label for="confirmDeleteCheck"
                        class="inline-block text-sm font-medium align-middle cursor-pointer text-slate-500 dark:text-zink-300">
                        I understand that this action is permanent and cannot be undone
                    </label>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-2 p-4 mt-auto border-t border-slate-200 dark:border-zink-500">
            <button type="button" data-modal-close="deleteAccountModal"
                class="text-slate-500 btn bg-slate-200 border-slate-200 hover:text-slate-600 hover:bg-slate-300 hover:border-slate-300 focus:text-slate-600 focus:bg-slate-300 focus:border-slate-300 focus:ring focus:ring-slate-100 active:text-slate-600 active:bg-slate-300 active:border-slate-300 active:ring active:ring-slate-100 dark:bg-zink-600 dark:hover:bg-zink-500 dark:border-zink-600 dark:hover:border-zink-500 dark:text-zink-200 dark:ring-zink-400/50">
                Cancel
            </button>
            <button type="submit" form="deleteAccountForm"
                class="text-white btn bg-red-500 border-red-500 hover:text-white hover:bg-red-600 hover:border-red-600 focus:text-white focus:bg-red-600 focus:border-red-600 focus:ring focus:ring-red-100 active:text-white active:bg-red-600 active:border-red-600 active:ring active:ring-red-100 dark:ring-red-400/20">
                <i data-lucide="trash-2" class="inline-block size-4 mr-1"></i>
                Yes, Delete My Account
            </button>
        </div>

    </div>
</div>
