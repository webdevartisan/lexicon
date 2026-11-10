/**
 * Upload queue for the media library: a row per file with progress, cancel and retry.
 *
 * @param {object} options container, url, csrfToken, extensions, maxBytes, typeNames, sizeLabel, t, esc, onUploaded
 * @returns {{add: function(FileList|File[]): void}}
 */
window.createMediaUploadQueue = function (options) {
    var container = options.container;
    var t = options.t;
    var esc = options.esc;
    var announcer = container.parentNode.querySelector('[data-upload-announce]');

    var PARALLEL = 3;
    var DONE_ROW_MS = 5000;
    var waiting = [];
    var running = 0;

    var ICONS = {
        image: '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
        x: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        retry: '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
        alert: '<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>',
        check: '<path d="M20 6 9 17l-5-5"/>',
    };

    function icon(name) {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
            + ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0">' + ICONS[name] + '</svg>';
    }

    var BUTTON_CLASS = 'inline-flex items-center justify-center gap-1 h-7 px-2 rounded-md text-xs font-medium border border-slate-200'
        + ' text-slate-600 bg-white hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:bg-zink-600'
        + ' focus:outline-none focus-visible:ring-2 focus-visible:ring-custom-500';
    var ICON_BUTTON_CLASS = 'inline-flex items-center justify-center size-7 rounded-md text-slate-500 hover:text-slate-800 hover:bg-slate-100'
        + ' dark:text-zink-300 dark:hover:text-zink-50 dark:hover:bg-zink-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-custom-500';

    function announce(message) {
        if (announcer) announcer.textContent = message;
    }

    function refusal(file) {
        var ext = file.name.lastIndexOf('.') === -1 ? '' : file.name.slice(file.name.lastIndexOf('.') + 1).toLowerCase();
        if (options.extensions.indexOf(ext) === -1) return t('wrongType', { name: file.name, types: options.typeNames });
        if (file.size > options.maxBytes) return t('tooBig', { name: file.name, size: options.sizeLabel });
        return '';
    }

    function makeRow(job) {
        var row = document.createElement('li');
        row.className = 'flex items-center gap-3 py-1.5 text-xs';
        container.appendChild(row);
        job.row = row;
    }

    function drawProgress(job) {
        var name = esc(job.file.name);
        job.row.className = 'flex items-center gap-3 py-1.5 text-xs text-slate-700 dark:text-zink-100';
        job.row.innerHTML = icon('image')
            + '<span class="min-w-0 truncate grow" title="' + name + '">' + name + '</span>'
            + '<span class="w-24 h-1.5 overflow-hidden rounded shrink-0 sm:w-40 bg-slate-100 dark:bg-zink-600" role="progressbar"'
            + ' aria-label="' + name + '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">'
            + '<span data-bar class="block h-full rounded bg-custom-500 transition-[width]" style="width:0%"></span></span>'
            + '<span data-percent class="w-9 text-end tabular-nums text-slate-500 dark:text-zink-300">0%</span>'
            + '<button type="button" data-cancel class="' + ICON_BUTTON_CLASS + '" aria-label="' + esc(t('cancelUpload', { name: job.file.name })) + '">'
            + icon('x') + '</button>';
        job.row.querySelector('[data-cancel]').addEventListener('click', function () { cancel(job); });
    }

    function setProgress(job, percent) {
        var bar = job.row.querySelector('[role="progressbar"]');
        if (!bar) return;
        bar.setAttribute('aria-valuenow', percent);
        job.row.querySelector('[data-bar]').style.width = percent + '%';
        job.row.querySelector('[data-percent]').textContent = percent + '%';
    }

    function drawDone(job) {
        var message = t('uploaded', { name: job.file.name });
        job.row.className = 'flex items-center gap-3 py-1.5 text-xs text-emerald-600 dark:text-emerald-400';
        job.row.innerHTML = icon('check') + '<span class="min-w-0 truncate grow">' + esc(message) + '</span>';
        announce(message);
        setTimeout(function () { job.row.remove(); }, DONE_ROW_MS);
    }

    function drawFailed(job, message, canRetry) {
        job.row.className = 'flex items-center gap-3 py-1.5 text-xs text-red-600 dark:text-red-400';
        job.row.innerHTML = icon('alert')
            + '<span class="min-w-0 grow">' + esc(message) + '</span>'
            + (canRetry ? '<button type="button" data-retry class="' + BUTTON_CLASS + '">' + icon('retry') + esc(t('retry')) + '</button>' : '')
            + '<button type="button" data-dismiss class="' + ICON_BUTTON_CLASS + '" aria-label="' + esc(t('dismiss')) + '">' + icon('x') + '</button>';
        announce(message);

        job.row.querySelector('[data-dismiss]').addEventListener('click', function () { job.row.remove(); });
        if (canRetry) {
            job.row.querySelector('[data-retry]').addEventListener('click', function () {
                drawProgress(job);
                enqueue(job);
            });
        }
    }

    function cancel(job) {
        if (job.xhr) {
            job.cancelled = true;
            job.xhr.abort();
            return;
        }
        waiting.splice(waiting.indexOf(job), 1);
        job.row.remove();
    }

    function send(job) {
        var body = new FormData();
        body.append('_token', options.csrfToken);
        body.append('file', job.file);

        var xhr = new XMLHttpRequest();
        job.xhr = xhr;
        xhr.open('POST', options.url);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', options.csrfToken);
        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) setProgress(job, Math.min(99, Math.round(e.loaded / e.total * 100)));
        });
        // From here the server may already be saving the file, so cancelling would only hide it
        xhr.upload.addEventListener('load', function () {
            var cancelButton = job.row.querySelector('[data-cancel]');
            if (cancelButton) cancelButton.remove();
        });

        xhr.addEventListener('loadend', function () {
            job.xhr = null;
            running -= 1;
            finish(job, xhr);
            pump();
        });

        running += 1;
        xhr.send(body);
    }

    function finish(job, xhr) {
        if (job.cancelled) {
            job.row.remove();
            return;
        }

        var payload = null;
        try { payload = JSON.parse(xhr.responseText); } catch (e) { payload = null; }

        if (payload && payload.success) {
            setProgress(job, 100);
            drawDone(job);
            options.onUploaded(payload.data);
            return;
        }

        var reason = (payload && payload.error) || t('error');
        drawFailed(job, t('uploadFailed', { name: job.file.name, error: reason }), true);
    }

    function enqueue(job) {
        job.cancelled = false;
        waiting.push(job);
        pump();
    }

    function pump() {
        while (running < PARALLEL && waiting.length > 0) {
            send(waiting.shift());
        }
    }

    function add(files) {
        Array.prototype.forEach.call(files, function (file) {
            var job = { file: file, xhr: null, row: null, cancelled: false };
            makeRow(job);

            var refused = refusal(file);
            if (refused) {
                drawFailed(job, refused, false);
                return;
            }

            drawProgress(job);
            enqueue(job);
        });
    }

    return { add: add };
};
