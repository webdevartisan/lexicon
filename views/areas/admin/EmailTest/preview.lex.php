{% extends "back.lex.php" %}

{% block title %}Email Preview: {{ template.name }}{% endblock %}
{% block subtitle %}{{ template.description }}{% endblock %}

{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">
    <div class="grid gap-6 lg:grid-cols-3">
        
        <!-- Email Preview (Main Column) -->
        <div class="lg:col-span-2">
            <div class="card">
                <div class="card-body !p-0">
                    <div class="px-4 py-3 border-b border-slate-200 dark:border-zink-600">
                        test
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Email Preview</h2>
                            <div class="flex items-center gap-2">
                                <button 
                                    id="decrease-height" 
                                    class="btn bg-white border-slate-300 text-slate-700 hover:bg-slate-50 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-100 !p-2"
                                    title="Decrease height"
                                >
                                    <i data-lucide="minimize-2" class="size-4"></i>
                                </button>
                                <button 
                                    id="increase-height" 
                                    class="btn bg-white border-slate-300 text-slate-700 hover:bg-slate-50 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-100 !p-2"
                                    title="Increase height"
                                >
                                    <i data-lucide="maximize-2" class="size-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Email iframe with adjustable height -->
                    <div class="bg-slate-50 dark:bg-zink-800 p-4">
                        <iframe 
                            id="email-preview-iframe"
                            src="/admin/email-test/render-html?template=<?= urlencode($templateKey) ?>"
                            class="w-full border-0 rounded bg-white dark:bg-zink-900"
                            style="min-height: 600px; height: 800px;"
                            sandbox="allow-same-origin"
                            title="Email Preview"
                        ></iframe>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar: Metadata & Actions -->
        <aside class="space-y-6">
            
            <!-- Email Metadata -->
            <div class="card">
                <div class="card-body">
                    <h3 class="mb-4 text-sm font-semibold text-slate-900 dark:text-zink-100">Email Details</h3>
                    
                    <dl class="space-y-4 text-sm">
                        <div>
                            <dt class="font-medium text-slate-500 dark:text-zink-300">Subject</dt>
                            <dd class="mt-1 text-slate-900 dark:text-zink-100">
                                <?= e($preview['subject']) ?>
                            </dd>
                        </div>
                        
                        <div>
                            <dt class="font-medium text-slate-500 dark:text-zink-300">From</dt>
                            <dd class="mt-1 text-slate-900 dark:text-zink-100">
                                <?= e($preview['from']['name']) ?><br>
                                <span class="text-xs text-slate-500 dark:text-zink-400">
                                    &lt;<?= e($preview['from']['address']) ?>&gt;
                                </span>
                            </dd>
                        </div>
                        
                        <div>
                            <dt class="font-medium text-slate-500 dark:text-zink-300">To (Sample)</dt>
                            <dd class="mt-1 text-slate-900 dark:text-zink-100">
                                <?php foreach ($preview['to'] as $email => $name) { ?>
                                    <?= e($name ?: $email) ?><br>
                                    <span class="text-xs text-slate-500 dark:text-zink-400">
                                        &lt;<?= e($email) ?>&gt;
                                    </span>
                                <?php } ?>
                            </dd>
                        </div>
                        
                        <div>
                            <dt class="font-medium text-slate-500 dark:text-zink-300">Format</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded <?= $preview['is_html'] ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' : 'bg-slate-100 text-slate-700 dark:bg-zink-600 dark:text-zink-200' ?>">
                                    <?= $preview['is_html'] ? 'HTML' : 'Plain Text' ?>
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
            
            <!-- Send Test Email -->
            <div class="card border-green-200 dark:border-green-800">
                <div class="card-body bg-green-50 dark:bg-green-900/10">
                    <div class="flex items-start gap-3 mb-4">
                        <div class="flex items-center justify-center size-10 bg-green-100 dark:bg-green-500/20 rounded-md shrink-0">
                            <i data-lucide="send" class="size-5 text-green-500"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-green-900 dark:text-green-400">Send Test Email</h3>
                            <p class="mt-1 text-sm text-green-700 dark:text-green-300">
                                Test this template by sending it to your email address
                            </p>
                        </div>
                    </div>
                    
                    <form method="POST" action="/admin/email-test/send-test">
                        {{ csrf_field() }}
                        <input type="hidden" name="template" value="<?= e($templateKey) ?>">
                        
                        <div class="mb-3">
                            <input 
                                type="email" 
                                name="recipient" 
                                placeholder="your@email.com"
                                required
                                class="form-input border-slate-300 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:focus:border-custom-500 dark:bg-zink-700 dark:text-zink-100"
                            >
                        </div>
                        
                        <button 
                            type="submit"
                            class="w-full btn bg-green-600 text-white hover:bg-green-700 border-green-600 hover:border-green-700 focus:bg-green-700 focus:border-green-700 dark:bg-green-500 dark:border-green-500 dark:hover:bg-green-600"
                        >
                            <i data-lucide="mail" class="inline-block size-4 mr-1"></i>
                            Send Test Email
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Plain Text Version -->
            <?php if ($preview['text_body']) { ?>
            <div class="card">
                <div class="card-body">
                    <h3 class="mb-3 text-sm font-semibold text-slate-900 dark:text-zink-100">Plain Text Version</h3>
                    <div class="p-3 bg-slate-100 dark:bg-zink-600 rounded-lg overflow-x-auto">
                        <pre class="text-xs text-slate-700 dark:text-zink-200 whitespace-pre-wrap font-mono"><?= e($preview['text_body']) ?></pre>
                    </div>
                </div>
            </div>
            <?php } ?>
            
        </aside>
        
    </div>
</div>

<!-- JavaScript for iframe height adjustment -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const iframe = document.getElementById('email-preview-iframe');
    const increaseBtn = document.getElementById('increase-height');
    const decreaseBtn = document.getElementById('decrease-height');
    
    if (iframe && increaseBtn && decreaseBtn) {
        increaseBtn.addEventListener('click', function() {
            const currentHeight = parseInt(iframe.style.height) || 800;
            iframe.style.height = (currentHeight + 200) + 'px';
        });
        
        decreaseBtn.addEventListener('click', function() {
            const currentHeight = parseInt(iframe.style.height) || 800;
            const newHeight = Math.max(400, currentHeight - 200);
            iframe.style.height = newHeight + 'px';
        });
    }
});
</script>

{% endblock %}
