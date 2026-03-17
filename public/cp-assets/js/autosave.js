(function() {
    'use strict';
    
    const DEBUG = false;
    
    function log(...args) {
        if (DEBUG) console.log(...args);
    }
    
    let autosaveTimer;
    const AUTOSAVE_DELAY = 30000;
    
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('[data-autosave-form]');
        if (!form) {
            log('⚠️ No autosave form found');
            return;
        }
        
        if (form.action.includes('/delete') || form.action.includes('/destroy')) {
            log('⏭️ Skipping autosave on delete page');
            return;
        }
        
        setupAutosave(form);
    });
    
    function setupAutosave(form) {
        let isSaving = false;
        
        const autosaveFields = [
            'title',
            'slug',
            'excerpt',
            'published_at',
            'timezone'
        ];
        
        autosaveFields.forEach(fieldName => {
            const field = form.querySelector(`[name="${fieldName}"]`);
            if (!field) {
                log(`⚠️ Field "${fieldName}" not found`);
                return;
            }
            
            const eventType = field.readOnly ? 'change' : 'input';
            
            field.addEventListener(eventType, () => {
                log(`📝 Field "${fieldName}" changed`);
                clearFieldError(fieldName);
                clearTimeout(autosaveTimer);
                autosaveTimer = setTimeout(() => {
                    if (!isSaving) {
                        isSaving = true;
                        autosavePost(form).finally(() => {
                            isSaving = false;
                        });
                    } else {
                        log('⏸️ Save already in progress, skipping');
                    }
                }, AUTOSAVE_DELAY);
            });
        });
        
        // Setup status radio buttons
        const statusRadios = form.querySelectorAll('[name="status"]');
        statusRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                log('📝 Status changed to:', radio.value);
                clearTimeout(autosaveTimer);
                autosaveTimer = setTimeout(() => {
                    if (!isSaving) {
                        isSaving = true;
                        autosavePost(form).finally(() => {
                            isSaving = false;
                        });
                    }
                }, AUTOSAVE_DELAY);
            });
        });
        
        setupTinyMCEAutosave(form, () => {
            if (!isSaving) {
                isSaving = true;
                autosavePost(form).finally(() => {
                    isSaving = false;
                });
            } else {
                log('⏸️ Save already in progress, skipping');
            }
        });
        
        log('✅ Autosave enabled');
    }

    function setupTinyMCEAutosave(form, saveCallback) {
        log('🔍 Looking for TinyMCE...');
        
        const checkTinyMCE = setInterval(() => {
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                const editor = tinymce.get('content');
                
                editor.on('KeyUp', function() {
                    log('⌨️ TinyMCE KeyUp event');
                    clearTimeout(autosaveTimer);
                    autosaveTimer = setTimeout(() => {
                        editor.save();
                        log('💾 TinyMCE content synced to textarea');
                        saveCallback();
                    }, AUTOSAVE_DELAY);
                });
                
                log('✅ TinyMCE autosave enabled');
                clearInterval(checkTinyMCE);
            }
        }, 100);
        
        setTimeout(() => {
            clearInterval(checkTinyMCE);
            if (typeof tinymce === 'undefined' || !tinymce.get('content')) {
                log('⚠️ TinyMCE not found after 5 seconds');
            }
        }, 5000);
    }

    function autosavePost(form) {
        const formData = new FormData();
        
        const actionUrl = form.action;
        const urlMatch = actionUrl.match(/\/post\/(\d+)\//);
        const postId = urlMatch ? urlMatch[1] : null;
        
        log('🔍 Form action:', actionUrl);
        log('🔍 Extracted post ID:', postId);
        
        if (postId) {
            formData.append('id', postId);
            log('📤 Sending id:', postId);
        }
        
        // Regular text/textarea fields
        const fieldsToSave = ['title', 'slug', 'content', 'excerpt', 'published_at', 'timezone'];
        
        fieldsToSave.forEach(fieldName => {
            const field = form.querySelector(`[name="${fieldName}"]`);
            if (field && field.value) {
                formData.append(fieldName, field.value);
                log(`📤 Sending ${fieldName}:`, field.value.substring(0, 50));
            }
        });

        // Handle status radio buttons
        const statusRadio = form.querySelector('[name="status"]:checked');
        if (statusRadio) {
            formData.append('status', statusRadio.value);
            log('📤 Sending status:', statusRadio.value);
        }

        if (!formData.get('title') && !formData.get('content')) {
            log('⏭️ Skipping autosave - no title or content');
            return Promise.resolve();
        }
        
        log('🚀 Starting autosave request...');
        showSaveIndicator('Saving...', 'pending');
        
        return fetch('/dashboard/post/autosave', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => {
            log('📥 Response status:', res.status);
            log('📥 Response URL:', res.url);
            
            const clonedRes = res.clone();
            
            if (!res.ok && res.status !== 422) {
                return clonedRes.text().then(html => {
                    console.error('❌ Server returned error:');
                    console.error('Status:', res.status);
                    console.error('HTML:', html.substring(0, 500));
                    throw new Error('HTTP ' + res.status);
                });
            }
            
            return clonedRes.json().catch(jsonErr => {
                return clonedRes.text().then(text => {
                    console.error('❌ Failed to parse JSON response:');
                    console.error('Content:', text.substring(0, 500));
                    throw new Error('Invalid JSON response');
                });
            });
        })
        .then(data => {
            log('✅ Response data:', data);
            
            if (data.success) {
                showSaveIndicator('Auto-saved at ' + data.saved_at, 'success');
                clearFieldErrors();
                
                if (data.id && !postId) {
                    const newAction = form.action.replace('/create', '/' + data.id + '/update');
                    form.action = newAction;
                    log('🔄 Form action updated to:', newAction);
                }
            } else {
                console.error('❌ Not Saved:', data.error);
                
                if (data.errors) {
                    log('📝 Showing field errors:', data.errors);
                    showFieldErrors(data.errors);
                }
                
                showSaveIndicator('Not Saved: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(err => {
            console.error('❌ Autosave error:', err);
            showSaveIndicator('Save failed', 'error');
        });
    }

    function showSaveIndicator(message, status) {
        const indicator = document.getElementById('autosave-indicator');
        const messageSpan = document.getElementById('autosave-message');
        const svg = indicator?.querySelector('svg');
        
        if (!indicator || !messageSpan || !svg) return;
        
        messageSpan.textContent = message;
        
        if (status === 'success') {
            svg.setAttribute('class', 'w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400');
            svg.innerHTML = '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>';
            indicator.className = 'flex items-center gap-2 text-xs text-slate-500 dark:text-zink-400';
        } else if (status === 'error') {
            svg.setAttribute('class', 'w-3.5 h-3.5 text-red-600 dark:text-red-400');
            svg.innerHTML = '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>';
            indicator.className = 'flex items-center gap-2 text-xs text-red-500 dark:text-red-400';
        } else {
            svg.setAttribute('class', 'w-3.5 h-3.5 text-blue-600 dark:text-blue-400 animate-spin');
            svg.innerHTML = '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>';
            indicator.className = 'flex items-center gap-2 text-xs text-slate-500 dark:text-zink-400';
        }
        
        indicator.style.display = 'flex';
    }

    function showFieldErrors(errors) {
        clearFieldErrors();
        
        log('📝 Showing validation errors:', errors);
        
        Object.keys(errors).forEach(fieldName => {
            const field = document.querySelector(`[name="${fieldName}"]`);
            if (!field) {
                log(`⚠️ Field "${fieldName}" not found for error display`);
                return;
            }
            
            const errorMessages = errors[fieldName];
            const firstError = Array.isArray(errorMessages) ? errorMessages[0] : errorMessages;
            
            field.classList.add('border-red-500', 'focus:border-red-500');
            field.classList.remove('border-slate-200', 'focus:border-custom-500');
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'autosave-error mt-1 text-sm text-red-600 dark:text-red-400';
            errorDiv.textContent = firstError;
            errorDiv.setAttribute('data-error-for', fieldName);
            
            field.parentNode.insertBefore(errorDiv, field.nextSibling);
            
            log(`✅ Error shown for field "${fieldName}":`, firstError);
        });
    }

    function clearFieldErrors() {
        document.querySelectorAll('.autosave-error').forEach(el => el.remove());
        
        const fields = ['title', 'slug', 'content', 'excerpt', 'published_at', 'timezone', 'status'];
        fields.forEach(fieldName => {
            const field = document.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.classList.remove('border-red-500', 'focus:border-red-500');
                field.classList.add('border-slate-200', 'dark:border-zink-500', 'focus:border-custom-500');
            }
        });
    }

    function clearFieldError(fieldName) {
        const field = document.querySelector(`[name="${fieldName}"]`);
        if (!field) return;
        
        const errorDiv = document.querySelector(`[data-error-for="${fieldName}"]`);
        if (errorDiv) {
            errorDiv.remove();
        }
        
        field.classList.remove('border-red-500', 'focus:border-red-500');
        field.classList.add('border-slate-200', 'dark:border-zink-500', 'focus:border-custom-500');
    }

    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            const indicator = document.getElementById('autosave-indicator');
            if (indicator) {
                indicator.style.display = 'none';
            }
            log('🔄 Page restored - autosave reset');
        }
    });
})();
