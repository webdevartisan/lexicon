/**
 * Avatar upload and crop for the profile panel.
 *
 * Picking a file uploads it as a crop source, then the cropper opens on that
 * stored source so the coordinates sent back match the pixels the server will
 * crop. Re-crop reuses the stored source with no re-upload.
 *
 * Runs on the /account/profile page, and on the Profile panel each time the
 * modal shows it.
 * The cropper library loads on first use so the panel opens without it.
 */
(function () {
    'use strict';

    var cropperReady = null;

    function loadCropper() {
        if (cropperReady) {
            return cropperReady;
        }

        cropperReady = new Promise(function (resolve, reject) {
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = '/assets/vendor/cropper/cropper.min.css';
            document.head.appendChild(link);

            var script = document.createElement('script');
            script.src = '/assets/vendor/cropper/cropper.min.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });

        return cropperReady;
    }

    function parseCrop(str) {
        if (!str) {
            return null;
        }
        var p = str.split(',').map(Number);

        return p.length === 4 ? { x: p[0], y: p[1], width: p[2], height: p[3] } : null;
    }

    function init(root) {
        var section = root.querySelector('[data-account-profile]');
        var input = root.querySelector('#avatar-input');

        if (!section || !input || section.hasAttribute('data-ready')) {
            return;
        }
        section.setAttribute('data-ready', '');

        var form = root.querySelector('#avatar-form');
        var loading = root.querySelector('#upload-loading');
        var box = root.querySelector('#avatar-box');
        var recropBtn = root.querySelector('#recrop-avatar-btn');
        var removeBox = root.querySelector('#remove-avatar-box');
        var modal = root.querySelector('#avatar-cropper-modal');
        var modalImage = root.querySelector('#avatar-cropper-image');
        var applyBtn = root.querySelector('#avatar-cropper-apply');
        var cropUrl = form.action + '/crop';
        var failure = section.getAttribute('data-crop-error') || 'Something went wrong. Please try again.';
        var cropper = null;

        function token() {
            var el = form.querySelector('input[name="_token"]');

            return el ? el.value : '';
        }

        function setPreview(url) {
            var img = root.querySelector('#avatar-preview');
            if (img) {
                img.src = url + '?v=' + Date.now();
                return;
            }
            var placeholder = root.querySelector('#avatar-placeholder');
            img = document.createElement('img');
            img.id = 'avatar-preview';
            img.className = 'acct-avatar-img';
            img.alt = '';
            img.src = url + '?v=' + Date.now();
            if (placeholder) {
                placeholder.replaceWith(img);
            } else {
                box.appendChild(img);
            }
        }

        function openCropper(sourceUrl, crop) {
            loadCropper().then(function () {
                modalImage.onload = function () {
                    if (cropper) {
                        cropper.destroy();
                    }
                    cropper = new Cropper(modalImage, {
                        aspectRatio: 1,
                        viewMode: 1,
                        autoCropArea: 1,
                        background: false,
                        responsive: true,
                        ready: function () {
                            if (crop && crop.width) {
                                cropper.setData(crop);
                            }
                        },
                    });
                };
                modalImage.src = sourceUrl;
                if (!modal.open) {
                    modal.showModal();
                }
            }).catch(function () {
                window.alert(failure);
            });
        }

        function closeCropper() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            if (modal.open) {
                modal.close();
            }
            modalImage.removeAttribute('src');
        }

        // Reveal the re-crop button once an avatar exists, so a fresh upload can be re-framed without a reload.
        function revealRecrop(sourceUrl, crop) {
            if (recropBtn) {
                recropBtn.dataset.source = sourceUrl;
                recropBtn.dataset.crop = crop ? [crop.x, crop.y, crop.width, crop.height].join(',') : '';
                recropBtn.hidden = false;
            }
            if (removeBox) {
                removeBox.hidden = false;
            }
        }

        input.addEventListener('change', function (e) {
            var file = e.target.files[0];
            if (!file) {
                return;
            }

            if (['image/jpeg', 'image/png', 'image/webp'].indexOf(file.type) === -1) {
                window.alert('Please select a valid image (JPG, PNG or WebP).');
                input.value = '';
                return;
            }
            if (file.size > (parseInt(input.dataset.maxSize, 10) || 20971520)) {
                window.alert('File size must be less than 20MB.');
                input.value = '';
                return;
            }

            var data = new FormData();
            data.append('_token', token());
            data.append('avatar', file);
            if (loading) {
                loading.hidden = false;
            }

            fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (loading) {
                        loading.hidden = true;
                    }
                    input.value = '';
                    if (res.error) {
                        window.alert(res.error);
                        return;
                    }
                    setPreview(res.avatar_url);
                    revealRecrop(res.source_url, res.crop);
                    openCropper(res.source_url, res.crop);
                })
                .catch(function () {
                    if (loading) {
                        loading.hidden = true;
                    }
                    window.alert(failure);
                });
        });

        if (recropBtn) {
            recropBtn.addEventListener('click', function () {
                openCropper(recropBtn.dataset.source, parseCrop(recropBtn.dataset.crop));
            });
        }

        applyBtn.addEventListener('click', function () {
            if (!cropper) {
                return;
            }
            var c = cropper.getData(true);
            var data = new FormData();
            data.append('_token', token());
            data.append('crop_x', c.x);
            data.append('crop_y', c.y);
            data.append('crop_w', c.width);
            data.append('crop_h', c.height);
            applyBtn.disabled = true;

            fetch(cropUrl, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    applyBtn.disabled = false;
                    if (res.error) {
                        window.alert(res.error);
                        return;
                    }
                    setPreview(res.avatar_url);
                    if (recropBtn) {
                        recropBtn.dataset.crop = [c.x, c.y, c.width, c.height].join(',');
                    }
                    closeCropper();
                })
                .catch(function () {
                    applyBtn.disabled = false;
                    window.alert(failure);
                });
        });

        modal.querySelectorAll('[data-crop-close]').forEach(function (el) {
            el.addEventListener('click', closeCropper);
        });
        modal.addEventListener('cancel', closeCropper);
    }

    document.addEventListener('panel:loaded', function (e) {
        init(e.detail);
    });

    init(document);
})();
