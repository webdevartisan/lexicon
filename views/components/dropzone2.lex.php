<?php
$label = $label ?? 'Image';
$name = $name ?? '';
$settings = $settings ?? [];
$resource = $resource ?? [];
$accepts = $accepts ?? 'image/jpeg,image/png,image/webp';
$maxsize = $maxsize ?? '2'; // MB
$imageClass = $imageClass ?? '';
$type = $type ?? str_replace(' ', '_', strtolower($label));

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
                    <i data-lucide="upload-cloud" class="block mx-auto size-10 text-slate-400 mb-2"></i>
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
        </div>

        <!-- Hidden input to mark for removal -->
        <input type="hidden" 
               id="remove_{{ elementName }}" 
               name="remove_{{ elementName }}" 
               value="">
    </div>
</div>
