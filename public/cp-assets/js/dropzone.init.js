/**
 * Generic Dropzone Handler
 * Usage: Add data-dropzone-form attribute to forms
 * Requires: Dropzone.js library
 */
(function() {
    var DEBUG = false;
    var DEFAULT_UPLOAD_URL = '/dashboard/upload';
    
    function log() {
        if (DEBUG) console.log.apply(console, arguments);
    }

    function csrfToken() {
        var input = document.querySelector('input[name="_token"]');
        if (input && input.value) return input.value;
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function refreshToken() {
        return fetch('/csrf-token', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.ok ? r.json() : null; })
            .then(function(j) {
                if (!j || !j.token) return;
                document.querySelectorAll('input[name="_token"]').forEach(function(input) { input.value = j.token; });
            })
            .catch(function(err) { console.error('Could not refresh the security token:', err); });
    }

    function describeFailure(label, errorMessage, xhr) {
        var what = 'Failed to upload ' + label.toLowerCase() + ': ';
        var status = xhr ? xhr.status : 0;
        var serverSays = errorMessage && typeof errorMessage === 'object' ? errorMessage.error : null;

        if (status === 419) {
            return what + 'the security token had expired. It has been renewed, so please save again to retry.';
        }
        if (status === 0 && xhr) {
            return what + 'the connection dropped before the upload finished. Please try again.';
        }
        if (status >= 500) {
            return what + 'the server could not store it. Please try again.';
        }
        if (serverSays) {
            return what + serverSays;
        }

        return what + (typeof errorMessage === 'string' ? errorMessage : 'unknown error.');
    }

    function showCardError(dz, message) {
        var card = dz.element.closest('[data-dropzone-card]');
        var box = card && card.querySelector('[data-dropzone-error]');
        if (!box) return;
        box.textContent = message;
        box.hidden = message === '';
    }

    function requeueFailedFiles(dz) {
        dz.files.forEach(function(file) {
            if (file.status !== Dropzone.ERROR) return;
            file.status = Dropzone.ADDED;
            if (file.previewElement) {
                file.previewElement.classList.remove('dz-error');
                var msg = file.previewElement.querySelector('[data-dz-errormessage]');
                if (msg) msg.textContent = '';
            }
            dz.enqueueFile(file);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        Dropzone.autoDiscover = false;

        // Initialize forms with dropzones
        var formsWithDropzones = document.querySelectorAll('[data-dropzone-form]');
        formsWithDropzones.forEach(function(form) {
            initDropzoneForm(form);
        });

        // Setup image management buttons (Change/Remove/Cancel)
        setupImageManagement();

    });

    function setupImageManagement() {
        
        document.addEventListener('click', function(e) {
            var target = e.target.closest('[data-action]');
            if (!target) return;

            var action = target.getAttribute('data-action');
            var elementName = target.getAttribute('data-target');

            if (action === 'change-image') {
                changeImage(elementName);
            } else if (action === 'remove-image') {
                removeImage(elementName);
            } else if (action === 'cancel-change') {
                cancelChange(elementName);
            }
        });
    }

    function changeImage(elementName) {
        var currentSection = document.getElementById('current-' + elementName);
        var dropzoneSection = document.getElementById('dropzone-section-' + elementName);
        var removeInput = document.getElementById('remove_' + elementName);
        
        if (currentSection) currentSection.style.display = 'none';
        if (dropzoneSection) dropzoneSection.style.display = 'block';
        if (removeInput) removeInput.value = '0'; // Not removing, just replacing
        
        log('Change image:', elementName);
    }

    function removeImage(elementName) {
        var currentSection = document.getElementById('current-' + elementName);
        var dropzoneSection = document.getElementById('dropzone-section-' + elementName);
        var removeInput = document.getElementById('remove_' + elementName);
        
        if (currentSection) currentSection.style.display = 'none';
        if (dropzoneSection) dropzoneSection.style.display = 'block';
        if (removeInput) removeInput.value = '1'; // Mark for removal

        log('Remove image:', elementName);
    }

    function cancelChange(elementName) {
        var currentSection = document.getElementById('current-' + elementName);
        var dropzoneSection = document.getElementById('dropzone-section-' + elementName);
        var removeInput = document.getElementById('remove_' + elementName);
        
        if (currentSection) currentSection.style.display = 'block';
        if (dropzoneSection) dropzoneSection.style.display = 'none';
        if (removeInput) removeInput.value = '0';
        
        // Clear dropzone if any files were added
        var dzCard = document.querySelector('[data-dropzone-card="' + elementName + '"]');
        if (dzCard) {
            var dzElement = dzCard.querySelector('[data-dropzone]');
            if (dzElement && dzElement.dropzone) {
                dzElement.dropzone.removeAllFiles();
            }
        }
        
        log('Cancel change:', elementName);
    }

    function initDropzoneForm(form) {
        var formSubmitted = false;
        var originalSubmit = form.submit;
        var completedDropzones = new Set();
        var dropzones = [];

        var dropzoneElements = form.querySelectorAll('[data-dropzone]');
        
        dropzoneElements.forEach(function(element) {
            var dz = initDropzone(element);
            if (dz) dropzones.push(dz);
        });

        if (dropzones.length === 0) {
            log("No dropzones found in form");
            return;
        }

        log("✅ Initialized", dropzones.length, "dropzones");

        // Reset form state when page is restored from bfcache
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                // Page was restored from bfcache (back/forward button)
                formSubmitted = false;
                completedDropzones.clear();
                log("🔄 Page restored from cache - reset form state");
            }
        });
        
        function initDropzone(element) {
            var fieldName = element.getAttribute('data-dropzone');
            var previewId = element.getAttribute('data-preview') || (fieldName + '-preview');
            var uploadUrl = element.getAttribute('data-upload-url') || DEFAULT_UPLOAD_URL;
            var maxFiles = parseInt(element.getAttribute('data-max-files')) || 1;
            var acceptedFiles = element.getAttribute('data-accept') || 'image/*';
            var maxSize = parseFloat(element.getAttribute('data-max-size')) || 2;
            
            var previewNode = document.querySelector("#" + previewId + "-list");
            if (!previewNode) {
                console.error("Preview template not found for:", fieldName);
                return null;
            }
            
            previewNode.id = "";
            var previewTemplate = previewNode.parentNode.innerHTML;
            previewNode.parentNode.removeChild(previewNode);
            
            var dropzone = new Dropzone(element, {
                url: uploadUrl,
                method: "post",
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                previewTemplate: previewTemplate,
                previewsContainer: "#" + previewId,
                autoProcessQueue: false,
                paramName: "file",
                maxFiles: maxFiles,
                maxFilesize: maxSize,
                acceptedFiles: acceptedFiles,
            });
            
            dropzone.uploadedFiles = [];
            dropzone.fieldName = fieldName;
            dropzone.failure = null;

            var card = element.closest('[data-dropzone-card]');
            var label = card && card.querySelector('h3') ? card.querySelector('h3').textContent.trim() : 'image';

            dropzone.on("sending", function(file, xhr) {
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());
            });

            dropzone.on("success", function(file, response) {
                if (response && response.success && response.data && response.data.filename) {
                    dropzone.uploadedFiles.push(response.data.filename);
                    return;
                }
                dropzone.failure = 'Failed to upload ' + label.toLowerCase() + ': the server sent an unexpected reply. Please try again.';
            });

            dropzone.on("error", function(file, errorMessage, xhr) {
                dropzone.failure = describeFailure(label, errorMessage, xhr);
                if (xhr && xhr.status === 419) {
                    refreshToken();
                }
            });

            dropzone.on("queuecomplete", function() {
                completedDropzones.add(fieldName);

                if (completedDropzones.size !== dropzones.length || formSubmitted) {
                    return;
                }

                var failed = dropzones.filter(function(dz) { return dz.failure; });
                if (failed.length > 0) {
                    stopSubmission(failed);
                    return;
                }

                submitForm();
            });
            
            return dropzone;
        }

        // form.submit() drops the clicked button's name/value, so anything the
        // server reads from the submitter has to be carried over by hand.
        var pendingSubmitter = null;

        form.addEventListener("submit", function(e) {
            e.preventDefault();

            var submitter = e.submitter || document.activeElement;
            if (submitter && submitter.name && form.contains(submitter)) {
                pendingSubmitter = { name: submitter.name, value: submitter.value };
            }

            if (formSubmitted) return false;

            completedDropzones.clear();
            dropzones.forEach(function(dz) {
                dz.failure = null;
                showCardError(dz, '');
                requeueFailedFiles(dz);
            });

            var dropzonesToProcess = dropzones.filter(function(dz) {
                return dz.getQueuedFiles().length > 0;
            });

            log("🚨 Form submit:", dropzonesToProcess.length, "files to upload");

            if (dropzonesToProcess.length > 0) {
                dropzones.forEach(function(dz) {
                    if (dz.getQueuedFiles().length === 0) {
                        completedDropzones.add(dz.fieldName);
                    }
                });
                
                dropzonesToProcess.forEach(function(dz) {
                    dz.processQueue();
                });
            } else {
                submitForm();
            }
            
            return false;
        }, true);

        function stopSubmission(failed) {
            failed.forEach(function(dz) { showCardError(dz, dz.failure); });

            var first = failed[0].element.closest('[data-dropzone-card]');
            if (first) {
                first.scrollIntoView({ block: 'center' });
            }
        }

        function submitForm() {
            if (formSubmitted) return;
            formSubmitted = true;
            
            dropzones.forEach(function(dz) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = dz.fieldName;
                input.value = JSON.stringify(dz.uploadedFiles);
                form.appendChild(input);
            });

            if (pendingSubmitter) {
                var submitterInput = document.createElement('input');
                submitterInput.type = 'hidden';
                submitterInput.name = pendingSubmitter.name;
                submitterInput.value = pendingSubmitter.value;
                form.appendChild(submitterInput);
                log("📎 Carrying submitter:", pendingSubmitter.name, "=", pendingSubmitter.value);
            }

            log("📤 Submitting form");
            originalSubmit.call(form);
        }
    }
})();
