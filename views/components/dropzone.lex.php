<?php
$label = $label ?? 'Image';
$name = $name ?? '';
$settings = $settings ?? [];
$resource = $resource ?? [];
$accepts = $accepts ?? 'image/jpeg,image/png,image/webp';
$maxsize = $maxsize ?? '2'; // MB
$imageClass = $imageClass ?? '';
$type = $type ?? str_replace(' ', '_', strtolower($label));
// `library` is the blog id; when set, a "Pick from Library" button appears
// next to the dropzone and the picked URL flows in via {name}_library_url.
$library = $library ?? '';
// Optional: id of a field to receive the picked image's alt text (only filled
// when that field is empty, so it never clobbers what the user typed).
$altTarget = $altTarget ?? '';

if (empty($imageClass)) {
    $imageClass = match ($type) {
        'banner' => 'object-cover w-full rounded-md h-24',
        'logo' => 'object-contain w-16 h-16 rounded-md',
        'favicon' => 'w-6 h-6 rounded',
        'profile_photo' => 'w-32 h-32 rounded-full object-cover',
        default => 'object-cover w-full rounded-md h-24'
    };
}

if (!empty($name)) {
    $elementName = str_replace(' ', '_', strtolower($name));
} else {
    $elementName = str_replace(' ', '_', strtolower($label));
}

$elementPathName = $elementName.'_path';
$path = $resource[$elementPathName] ?? '';
if (empty($path)) {
    $path = $resource[$elementName] ?? '';
}

$acceptedTypesText = formatAcceptedTypes($accepts);
$maxSizeText = $maxsize >= 1
    ? number_format($maxsize, 0).'MB'
    : number_format($maxsize * 1024, 0).'KB';
?>

{% set changeBtnLabel = t('components.dropzone.actions.change') %}
{% set removeBtnLabel = t('components.dropzone.actions.remove') %}
{% set cancelBtnLabel = t('components.dropzone.actions.cancel') %}

<div class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600" 
     data-dropzone-card="{{ elementName }}">
    <div class="p-3 border-b border-slate-200 dark:border-zink-600">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-zink-100">{{ label }}</h3>
    </div>

    <div class="p-3">
        <!-- Current Image Section -->
        <div class="current-image-section" 
             id="current-{{ elementName }}" 
             style="display: {% if path|notempty %}block{% else %}none{% endif %}">
            {% if path|notempty %}
            <div class="mb-3">
                <img src="{{ path }}" 
                     alt="{{ t('components.dropzone.altText.current') }} {{ elementName }}" 
                     class="{{ imageClass }}">
            </div>
            {% endif %}
            
            <div class="flex gap-2">
                <?php $dataAction = 'change-image'; ?>
                {% cmp="btn" type="button" variant="slate" icon="refresh-cw" label="{$changeBtnLabel}" dataAction="{$dataAction}" dataTarget="{$elementName}" %}
                
                <?php $dataAction = 'remove-image'; ?>
                {% cmp="btn" type="button" variant="slate" icon="trash-2" label="{$removeBtnLabel}" dataAction="{$dataAction}" dataTarget="{$elementName}" %}

                <?php if (!empty($library) && !empty($path)) { ?>
                <a href="<?= e(lurl('/dashboard/blog/'.$library.'/media').'?editUrl='.rawurlencode($path)) ?>"
                   class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-md hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                    {% cache 'lucide:sliders-horizontal' ttl=31536000 %}<i data-lucide="sliders-horizontal" class="size-3.5"></i>{% endcache %}
                    Edit / optimize
                </a>
                <?php } ?>
            </div>
        </div>

        <!-- Dropzone Section -->
        <div class="dropzone-section" 
             id="dropzone-section-{{ elementName }}"
             style="display: {% if path|notempty %}none{% else %}block{% endif %}">
            
            <div class="dropzone-{{ elementName }} cursor-pointer border-2 border-dashed rounded-md border-slate-200 dark:border-zink-500 hover:border-custom-400 transition-colors"
                 data-dropzone="uploaded_{{ elementName }}_files"
                 data-preview="dropzone-{{ elementName }}-preview"
                 data-max-files="1"
                 data-accept="{{ accepts }}"
                 data-max-size="{{ maxsize }}">
                
                <div class="fallback">
                    <input name="{{ elementName }}" type="file">
                </div>
                <div class="py-8 text-center dz-message needsclick">
                    {% cache 'lucide:upload-cloud:dz2' ttl=31536000 %}<i data-lucide="upload-cloud" class="block mx-auto size-10 text-slate-400 mb-2"></i>{% endcache %}
                    <p class="text-xs text-slate-500 dark:text-zink-300">
                        {{ t('components.dropzone.messages.drop') }} {{ t('components.dropzone.messages.or') }} <a href="#!" class="text-custom-500">{{ t('components.dropzone.messages.browse') }}</a>
                    </p>
                    <p class="text-[10px] text-slate-400 mt-1">
                        {{ acceptedTypesText }}, {{ t('components.dropzone.messages.upTo') }} {{ maxSizeText }}
                    </p>
                </div>
            </div>
            
            <ul class="mt-2" id="dropzone-{{ elementName }}-preview">
                <li id="dropzone-{{ elementName }}-preview-list">
                    <div class="flex gap-2 p-2 text-xs border rounded border-slate-200 dark:border-zink-500">
                        <img data-dz-thumbnail="" class="object-cover w-10 h-10 rounded" alt="{{ t('components.dropzone.altText.preview') }}">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium truncate text-slate-700 dark:text-zink-100" data-dz-name=""></p>
                            <p class="text-[10px] text-slate-500" data-dz-size=""></p>
                            <strong class="text-red-500 text-[10px]" data-dz-errormessage=""></strong>
                        </div>
                        <button data-dz-remove="" class="px-2 py-1 text-[10px] text-red-600 hover:text-red-700">×</button>
                    </div>
                </li>
            </ul>

            {% if path|notempty %}
                <?php $dataAction = 'cancel-change'; ?>
                {% cmp="btn" type="button" variant="slate" icon="x" label="{$cancelBtnLabel}" dataAction="{$dataAction}" dataTarget="{$elementName}" %}
            {% endif %}

            <?php if (!empty($library)) { ?>
            <button type="button"
                    class="mt-2 w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-md hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors"
                    data-media-picker="<?= e((string) $library) ?>"
                    data-media-target="<?= e($elementName) ?>_library_url"
                    data-media-preview="<?= e($elementName) ?>_library_preview"
                    <?php if (!empty($altTarget)) { ?>data-media-alt-target="<?= e($altTarget) ?>"<?php } ?>>
                {% cache 'lucide:image-plus' ttl=31536000 %}<i data-lucide="image-plus" class="size-3.5"></i>{% endcache %}
                Pick from Media Library
            </button>
            <?php } ?>
        </div>

        <!-- Hidden input to mark for removal -->
        <input type="hidden"
               id="remove_{{ elementName }}"
               name="remove_{{ elementName }}"
               value="">

        <?php if (!empty($library)) { ?>
        <!-- Filled in by media-picker.js when the user picks from the library -->
        <input type="hidden"
               id="<?= e($elementName) ?>_library_url"
               name="<?= e($elementName) ?>_library_url"
               value="">
        <script nonce="<?= csp_nonce() ?>">
        (function () {
            var input = document.getElementById('<?= e($elementName) ?>_library_url');
            if (!input) return;
            input.addEventListener('change', function () {
                if (!input.value) return;
                var current = document.getElementById('current-<?= e($elementName) ?>');
                var dropzoneSection = document.getElementById('dropzone-section-<?= e($elementName) ?>');
                var removeInput = document.getElementById('remove_<?= e($elementName) ?>');
                if (current) {
                    var img = current.querySelector('img');
                    if (!img) {
                        img = document.createElement('img');
                        img.className = '<?= e($imageClass) ?>';
                        var wrap = document.createElement('div');
                        wrap.className = 'mb-3';
                        wrap.appendChild(img);
                        current.insertBefore(wrap, current.firstChild);
                    }
                    img.src = input.value;
                    current.style.display = 'block';
                }
                if (dropzoneSection) dropzoneSection.style.display = 'none';
                if (removeInput) removeInput.value = '0';
            });
        })();
        </script>
        <?php } ?>
    </div>
</div>
