/**
 * The media library's image editor: rename and alt text, then crop, resize,
 * compress or convert the stored file, saved as a new image or over the original,
 * or delete it. It lives in the shared modal (components/modal, bare) and is driven
 * by Cropper.js.
 *
 * media-library.js builds it and hands in the request helper and the grid updates,
 * so the editor never reaches into the grid itself.
 *
 * @param {object} deps api, esc, humanSize, versioned, displayName, showCard, deleteItem
 * @returns {{open: function(string|number, Element=): void}}
 */
window.createMediaEditor = function (deps) {
    var api = deps.api;
    var esc = deps.esc;
    var humanSize = deps.humanSize;
    var versioned = deps.versioned;
    var displayName = deps.displayName;
    var showCard = deps.showCard;
    var deleteItem = deps.deleteItem;

    var modal = document.getElementById('media-editor');
    var form = document.getElementById('media-editor-form');
    var image = document.getElementById('media-editor-image');
    var heading = document.getElementById('media-editor-heading');
    var meta = document.getElementById('media-editor-meta');
    var usage = document.getElementById('media-editor-usage');
    var output = document.getElementById('media-output');
    var status = document.getElementById('media-editor-status');
    var nameInput = document.getElementById('media-name');
    var altInput = document.getElementById('media-alt');
    var widthInput = document.getElementById('media-width');
    var qualityInput = document.getElementById('media-quality');
    var qualityValue = document.getElementById('media-quality-value');
    var formatSelect = document.getElementById('media-format');
    var modeHint = document.getElementById('media-mode-hint');
    var detailsButton = document.getElementById('media-details-save');
    var applyButton = document.getElementById('media-editor-apply');
    var deleteButton = document.getElementById('media-editor-delete');
    var toggles = {
        aspect: form.querySelector('[data-toggle="aspect"]'),
        scale: form.querySelector('[data-toggle="scale"]'),
        mode: form.querySelector('[data-toggle="mode"]'),
    };

    var cropper = null;
    var current = null;
    var opening = false;
    var estimateTimer = null;

    function pressed(name) {
        var button = toggles[name].querySelector('[aria-pressed="true"]');
        return button ? button.getAttribute('data-value') : null;
    }

    function press(name, value) {
        toggles[name].querySelectorAll('button').forEach(function (button) {
            button.setAttribute('aria-pressed', button.getAttribute('data-value') === value ? 'true' : 'false');
        });
    }

    function setStatus(message) {
        status.textContent = message;
        status.classList.toggle('hidden', !message);
    }

    // Disables the button while the request runs and shows any failure inside the editor
    function whileSaving(button, request) {
        button.disabled = true;
        setStatus('');

        return request
            .catch(function (error) { setStatus(error.message); })
            .finally(function () { button.disabled = false; });
    }

    function extension(item) {
        var ext = String(item.extension || '').toLowerCase();
        return ext === 'jpeg' ? 'jpg' : ext;
    }

    function outputFormat() {
        var format = formatSelect.value || extension(current);
        return format === 'png' || format === 'webp' ? format : 'jpeg';
    }

    // The crop size, or the custom width with the height following the ratio.
    // This is exactly what the server will produce.
    function outputSize() {
        var crop = cropper.getData(true);
        var width = parseInt(widthInput.value, 10) || 0;
        if (width > 0 && crop.width > 0) {
            return { width: width, height: Math.max(1, Math.round(width * crop.height / crop.width)) };
        }
        return { width: crop.width, height: crop.height };
    }

    // Encodes the crop at the chosen size and quality and measures the blob, so the
    // size trade-off is visible before anything is saved.
    function updateEstimate() {
        if (!cropper) return;
        var size = outputSize();
        var label = 'Output: ' + size.width + ' × ' + size.height;
        output.textContent = label;

        var canvas = cropper.getCroppedCanvas({ width: size.width, height: size.height, imageSmoothingQuality: 'high' });
        if (!canvas) return;
        var format = outputFormat();
        canvas.toBlob(function (blob) {
            if (blob && cropper) output.textContent = label + ' · ~' + humanSize(blob.size) + ' · ' + format.toUpperCase();
        }, 'image/' + format, qualityInput.value / 100);
    }

    function scheduleEstimate() {
        clearTimeout(estimateTimer);
        estimateTimer = setTimeout(updateEstimate, 180);
    }

    // A pressed scale preset sets the width from the crop. No preset pressed means
    // the user typed a width, which is left alone.
    function applyScale() {
        var scale = parseFloat(pressed('scale'));
        if (scale) {
            widthInput.value = scale < 1 ? Math.max(1, Math.round(cropper.getData(true).width * scale)) : '';
        }
        scheduleEstimate();
    }

    // Converting changes the file's URL, so it can only be saved as a new image
    function syncMode() {
        var converting = formatSelect.value !== '' && formatSelect.value !== extension(current);
        toggles.mode.querySelector('[data-value="overwrite"]').disabled = converting;
        if (converting) {
            press('mode', 'new');
            modeHint.textContent = 'Converting the format saves a new image so existing links keep working.';
        } else if (pressed('mode') === 'overwrite') {
            modeHint.textContent = 'Replaces the file in place; posts using it update automatically.';
        } else {
            modeHint.textContent = 'Adds a new image to the library; the original stays as it is.';
        }
    }

    function renderUsage(usages) {
        if (usages.length === 0) {
            usage.innerHTML = '<p class="font-medium">Not used anywhere yet</p>';
            return;
        }
        usage.innerHTML = '<p class="font-medium mb-1">Used in ' + usages.length + ' place' + (usages.length === 1 ? '' : 's') + '</p>'
            + '<ul class="space-y-0.5">' + usages.map(function (use) {
                var label = use.link
                    ? '<a href="' + esc(use.link) + '" target="_blank" rel="noopener" class="text-custom-500 hover:underline">' + esc(use.label) + '</a>'
                    : esc(use.label);
                return '<li>' + label + ' <span class="text-slate-500 dark:text-zink-300">(' + esc(use.context) + ')</span></li>';
            }).join('') + '</ul>';
    }

    function startCropper(url) {
        stopCropper();
        image.onload = function () {
            cropper = new Cropper(image, {
                viewMode: 1,
                autoCropArea: 1,
                background: false,
                responsive: true,
                ready: updateEstimate,
                cropend: applyScale,
            });
        };
        image.src = versioned(url, Date.now());
    }

    // Also drops a pending load, so an image arriving after the editor closed starts nothing
    function stopCropper() {
        image.onload = null;
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        image.removeAttribute('src');
    }

    function fill(item) {
        current = item;
        form.reset();
        nameInput.value = displayName(item);
        altInput.value = item.alt_text || '';
        qualityValue.textContent = qualityInput.value;
        press('aspect', 'free');
        press('scale', '1');
        press('mode', 'new');
        heading.textContent = displayName(item);
        meta.textContent = [
            (item.width || '?') + ' × ' + (item.height || '?'),
            humanSize(item.size_bytes),
            extension(item).toUpperCase(),
            item.source,
        ].filter(Boolean).join(' · ');
        renderUsage(item.usages || []);
        output.textContent = '';
        setStatus('');
        syncMode();
        startCropper(item.url);
    }

    // Ignores clicks while a previous image is still loading its details
    function openEditor(id, trigger) {
        if (opening) return;
        opening = true;

        api('/' + id + '/details')
            .then(function (item) {
                fill(item);
                cpModal.open('media-editor', trigger);
            })
            .catch(function (error) { window.alert(error.message); })
            .finally(function () { opening = false; });
    }

    modal.addEventListener('modal:close', function () {
        stopCropper();
        current = null;
    });

    form.addEventListener('submit', function (e) { e.preventDefault(); });

    Object.keys(toggles).forEach(function (name) {
        toggles[name].addEventListener('click', function (e) {
            var button = e.target.closest('button');
            if (!button || button.disabled) return;
            if (name !== 'mode' && !cropper) return;
            press(name, button.getAttribute('data-value'));

            if (name === 'aspect') {
                var ratio = button.getAttribute('data-value');
                cropper.setAspectRatio(ratio === 'free' ? NaN : parseFloat(ratio));
                applyScale();
            } else if (name === 'scale') {
                applyScale();
            } else {
                syncMode();
            }
        });
    });

    widthInput.addEventListener('input', function () {
        press('scale', null);
        scheduleEstimate();
    });
    qualityInput.addEventListener('input', function () {
        qualityValue.textContent = qualityInput.value;
        scheduleEstimate();
    });
    formatSelect.addEventListener('change', function () {
        if (!current) return;
        syncMode();
        scheduleEstimate();
    });

    detailsButton.addEventListener('click', function () {
        var name = nameInput.value.trim();
        if (!name) {
            setStatus('The name cannot be empty.');
            return;
        }

        var item = current;
        whileSaving(detailsButton, api('/' + item.id + '/meta', { name: name, alt_text: altInput.value }).then(function (saved) {
            Object.assign(item, saved);
            showCard(saved);
            if (current !== item) return;
            nameInput.value = displayName(saved);
            altInput.value = saved.alt_text || '';
            heading.textContent = displayName(saved);
        }));
    });

    // A whole-image box is a plain resize or convert, which the server keeps in
    // proportion, so a crop is only sent for a real sub-region.
    function cropFields() {
        var crop = cropper.getData(true);
        var natural = cropper.getImageData();
        var whole = crop.x <= 1 && crop.y <= 1
            && crop.width >= Math.round(natural.naturalWidth) - 2
            && crop.height >= Math.round(natural.naturalHeight) - 2;
        if (whole || !crop.width || !crop.height) return {};

        return { crop_x: Math.max(0, crop.x), crop_y: Math.max(0, crop.y), crop_w: crop.width, crop_h: crop.height };
    }

    applyButton.addEventListener('click', function () {
        if (!cropper || !current) return;
        var mode = pressed('mode');
        var fields = Object.assign({
            mode: mode,
            quality: qualityInput.value,
            width: widthInput.value || null,
            format: formatSelect.value || null,
        }, cropFields());

        whileSaving(applyButton, api('/' + current.id + '/process', fields).then(function (item) {
            // An overwrite keeps the URL and the row's old timestamp, so force a fresh thumbnail
            if (mode === 'overwrite') item.updated_at = String(Date.now());
            showCard(item);
            cpModal.close('media-editor');
        }));
    });

    deleteButton.addEventListener('click', function () {
        var item = current;
        var usages = item.usages || [];
        var message = usages.length === 0
            ? 'Delete this image? This cannot be undone.'
            : 'This image is used in ' + usages.length + ' place(s):\n\n'
                + usages.map(function (use) { return '• ' + use.label + ' (' + use.context + ')'; }).join('\n')
                + '\n\nDeleting it will leave broken images there. Delete anyway?';

        cpConfirm(message).then(function (ok) {
            if (!ok) return;
            whileSaving(deleteButton, deleteItem(item.id).then(function () { cpModal.close('media-editor'); }));
        });
    });

    return { open: openEditor };
};
