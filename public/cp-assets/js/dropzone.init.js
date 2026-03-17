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
            
            dropzone.on("success", function(file, response) {
                if (response.success && response.data.filename) {
                    dropzone.uploadedFiles.push(response.data.filename);
                    log("✅ Uploaded:", response.data.filename);
                }
            });
            
            dropzone.on("error", function(file, errorMessage) {
                console.error("Upload failed:", errorMessage);
            });
            
            dropzone.on("queuecomplete", function() {
                completedDropzones.add(fieldName);
                log("📊", completedDropzones.size, "/", dropzones.length, "complete");
                
                if (completedDropzones.size === dropzones.length && !formSubmitted) {
                    submitForm();
                }
            });
            
            return dropzone;
        }

        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            if (formSubmitted) return false;

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
            
            log("📤 Submitting form");
            originalSubmit.call(form);
        }
    }
})();
