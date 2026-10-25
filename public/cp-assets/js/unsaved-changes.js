(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('[data-unsaved-warning]');
        if (!form) {
            return;
        }

        let reading = false;
        let sending = false;

        function fieldValues() {
            reading = true;
            const data = new FormData(form);
            reading = false;
            // The token is swapped in the background when it expires, which is not an edit.
            data.delete('_token');

            return new URLSearchParams(data).toString();
        }

        const loaded = fieldValues();

        function hasChanges() {
            const editor = window.tinymce && window.tinymce.get('content');
            const imageWaiting = window.Dropzone && Dropzone.instances.some(function (dz) {
                return dz.getQueuedFiles().length > 0;
            });

            return fieldValues() !== loaded || Boolean(editor && editor.isDirty()) || Boolean(imageWaiting);
        }

        // formdata fires only when the browser really sends the form, including the upload
        // script's own form.submit(), so a save stopped by a failed upload still warns.
        form.addEventListener('formdata', function () {
            if (!reading) {
                sending = true;
            }
        });

        window.addEventListener('beforeunload', function (event) {
            if (sending || !hasChanges()) {
                return;
            }

            event.preventDefault();
            event.returnValue = true;
        });
    });
})();
