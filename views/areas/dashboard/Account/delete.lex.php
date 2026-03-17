{% extends "back.lex.php" %}

{% block title %}Delete Account{% endblock %}

{% block body %}

<div class="container-fluid group-data-[content=boxed]:max-w-boxed mx-auto">
    
    <div class="flex flex-col gap-2 py-4 md:flex-row md:items-center print:hidden">
        <div class="grow">
            <h5 class="text-16">Delete Account</h5>
        </div>
        <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
            <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                <a href="/dashboard" class="text-slate-400 dark:text-zink-200">Dashboard</a>
            </li>
            <li class="text-slate-700 dark:text-zink-100">
                Delete Account
            </li>
        </ul>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-x-5">
        <div class="xl:col-span-8 xl:col-start-3">
            
            <!-- Main Delete Account Card -->
            <div class="card border-red-200 dark:border-red-800">
                
                <!-- Card Header with Red Background -->
                <div class="card-body !bg-red-500 !p-5 border-b border-red-600">
                    <div class="flex items-center gap-3 text-white">
                        <div class="flex items-center justify-center size-12 bg-white/20 rounded-md shrink-0">
                            <i data-lucide="alert-triangle" class="size-6"></i>
                        </div>
                        <div>
                            <h4 class="mb-1 text-white text-17">Delete Account</h4>
                            <p class="mb-0 text-red-100 text-sm">This action is permanent and cannot be undone</p>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    
                    {% if canDelete|empty %}
                        <!-- Cannot Delete Alert -->
                        <div class="px-4 py-3 mb-4 text-sm text-red-800 border border-red-300 rounded-md bg-red-50 dark:bg-red-400/20 dark:border-red-500/50 dark:text-red-500">
                            <div class="flex items-center gap-2">
                                <i data-lucide="x-circle" class="size-5 shrink-0"></i>
                                <div>
                                    <strong class="font-semibold">Account cannot be deleted:</strong>
                                    <span>{{ $deleteReason }}</span>
                                </div>
                            </div>
                        </div>
                        
                        <a href="/dashboard/profile" class="text-white btn bg-slate-500 border-slate-500 hover:text-white hover:bg-slate-600 hover:border-slate-600 focus:text-white focus:bg-slate-600 focus:border-slate-600">
                            <i data-lucide="arrow-left" class="inline-block size-4 mr-1"></i>
                            Back to Profile
                        </a>
                        
                    {% else %}
                        
                        <!-- Warning Alert -->
                        <div class="px-4 py-3 mb-4 text-sm text-yellow-800 border border-yellow-300 rounded-md bg-yellow-50 dark:bg-yellow-400/20 dark:border-yellow-500/50 dark:text-yellow-500">
                            <div class="flex items-center gap-2">
                                <i data-lucide="alert-triangle" class="size-5 shrink-0"></i>
                                <div>
                                    <strong class="font-semibold">Warning:</strong>
                                    <span>This action is permanent and cannot be undone.</span>
                                </div>
                            </div>
                        </div>

                        <!-- What Will Be Deleted -->
                        <div class="mb-5">
                            <h5 class="mb-3 text-16 text-slate-800 dark:text-zink-50 flex items-center gap-2">
                                <i data-lucide="x-circle" class="size-5 text-red-500"></i>
                                What will be deleted:
                            </h5>
                            <ul class="space-y-2 text-slate-600 dark:text-zink-300">
                                <li class="flex items-start gap-2">
                                    <i data-lucide="x" class="size-4 text-red-500 mt-1 shrink-0"></i>
                                    <span>Your profile information (name, email, bio, location)</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i data-lucide="x" class="size-4 text-red-500 mt-1 shrink-0"></i>
                                    <span>Your avatar and uploaded files</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i data-lucide="x" class="size-4 text-red-500 mt-1 shrink-0"></i>
                                    <span>Your account preferences and settings</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i data-lucide="x" class="size-4 text-red-500 mt-1 shrink-0"></i>
                                    <span>Your social media links</span>
                                </li>
                            </ul>
                        </div>

                        <!-- What Will Be Preserved -->
                        <div class="mb-5">
                            <h5 class="mb-3 text-16 text-slate-800 dark:text-zink-50 flex items-center gap-2">
                                <i data-lucide="check-circle" class="size-5 text-green-500"></i>
                                What will be preserved:
                            </h5>
                            <ul class="space-y-2 text-slate-600 dark:text-zink-300">
                                <li class="flex items-start gap-2">
                                    <i data-lucide="check" class="size-4 text-green-500 mt-1 shrink-0"></i>
                                    <span>Your posts ({{ $postCount }} posts) - attributed to "Deleted User"</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i data-lucide="check" class="size-4 text-green-500 mt-1 shrink-0"></i>
                                    <span>Comments on your posts ({{ $commentCount }} comments received)</span>
                                </li>
                            </ul>
                        </div>

                        <!-- GDPR Notice -->
                        <div class="p-4 mb-5 bg-slate-100 dark:bg-zink-600 border border-slate-200 dark:border-zink-500 rounded-md">
                            <div class="flex items-start gap-3">
                                <i data-lucide="info" class="size-5 text-custom-500 mt-0.5 shrink-0"></i>
                                <p class="mb-0 text-sm text-slate-700 dark:text-zink-200">
                                    <strong class="font-semibold">GDPR Compliance:</strong> Your personal data will be permanently 
                                    deleted in accordance with data protection regulations. 
                                    Content you created will remain for platform integrity but 
                                    without any identifying information.
                                </p>
                            </div>
                        </div>

                        <!-- Delete Form -->
                        <form method="POST" action="/dashboard/delete-account" id="deleteForm" class="space-y-4">
                            {{ csrf_field() }}
                            
                            <!-- Password Input -->
                            <div>
                                <label for="password" class="inline-block mb-2 text-base font-medium">
                                    Confirm your password to proceed <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="password" 
                                    name="password" 
                                    id="password" 
                                    required 
                                    class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 disabled:bg-slate-100 dark:disabled:bg-zink-600 disabled:border-slate-300 dark:disabled:border-zink-500 dark:disabled:text-zink-200 disabled:text-slate-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200"
                                    placeholder="Enter your current password"
                                    autocomplete="current-password"
                                >
                                <p class="mt-1 text-sm text-slate-500 dark:text-zink-300">
                                    Password confirmation is required for security.
                                </p>
                            </div>

                            <!-- Confirmation Checkbox -->
                            <div class="flex items-start gap-2">
                                <input 
                                    type="checkbox" 
                                    class="size-4 border rounded-sm appearance-none bg-slate-100 border-slate-200 dark:bg-zink-600 dark:border-zink-500 checked:bg-custom-500 checked:border-custom-500 dark:checked:bg-custom-500 dark:checked:border-custom-500 cursor-pointer mt-0.5" 
                                    id="confirmCheck" 
                                    required
                                >
                                <label for="confirmCheck" class="inline-block align-middle cursor-pointer text-slate-600 dark:text-zink-200">
                                    <span class="font-semibold">I understand that this action is permanent and cannot be undone</span>
                                </label>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-wrap items-center gap-2 mt-5">
                                <button 
                                    type="submit" 
                                    class="text-white btn bg-red-500 border-red-500 hover:text-white hover:bg-red-600 hover:border-red-600 focus:text-white focus:bg-red-600 focus:border-red-600 focus:ring focus:ring-red-100 active:text-white active:bg-red-600 active:border-red-600 active:ring active:ring-red-100 dark:ring-red-400/20"
                                >
                                    <i data-lucide="trash-2" class="inline-block size-4 mr-1"></i>
                                    Delete My Account Permanently
                                </button>
                                <a 
                                    href="/dashboard/profile" 
                                    class="text-slate-500 btn bg-slate-200 border-slate-200 hover:text-slate-600 hover:bg-slate-300 hover:border-slate-300 focus:text-slate-600 focus:bg-slate-300 focus:border-slate-300 focus:ring focus:ring-slate-100 active:text-slate-600 active:bg-slate-300 active:border-slate-300 active:ring active:ring-slate-100 dark:bg-zink-600 dark:hover:bg-zink-500 dark:border-zink-600 dark:hover:border-zink-500 dark:text-zink-200 dark:ring-zink-400/50"
                                >
                                    <i data-lucide="x" class="inline-block size-4 mr-1"></i>
                                    Cancel
                                </a>
                            </div>
                        </form>

                    {% endif %}
                    
                </div>
            </div>

        </div>
    </div>

</div>
{% endblock %}

{% block scripts %}
<script>
// add JavaScript confirmation for extra safety
document.getElementById('deleteForm')?.addEventListener('submit', function(e) {
    const checkbox = document.getElementById('confirmCheck');
    const password = document.getElementById('password');
    
    if (!checkbox?.checked) {
        e.preventDefault();
        alert('Please confirm you understand this action is permanent.');
        return false;
    }
    
    if (!password?.value) {
        e.preventDefault();
        alert('Please enter your password to confirm deletion.');
        return false;
    }
    
    // Final confirmation dialog
    const confirmed = confirm(
        'FINAL WARNING:\n\n' +
        'This will permanently delete your account and all personal data.\n\n' +
        'Are you absolutely sure you want to continue?'
    );
    
    if (!confirmed) {
        e.preventDefault();
        return false;
    }
});
</script>
{% endblock %}