/**
 * The phone sheet for one library image: edit, download, copy link or delete.
 *
 * @param {object} deps id, thumb, displayName, meta, onEdit, onCopy, onDelete
 * @returns {{open: function(object, Element=): void}}
 */
window.createMediaItemSheet = function (deps) {
    var sheet = document.getElementById(deps.id);
    var thumb = sheet.querySelector('[data-sheet-thumb]');
    var name = sheet.querySelector('[data-sheet-name]');
    var meta = sheet.querySelector('[data-sheet-meta]');
    var download = sheet.querySelector('[data-sheet-action="download"]');
    var handlers = { edit: deps.onEdit, copy: deps.onCopy, delete: deps.onDelete };
    var current = null;
    var opener = null;

    function open(item, trigger) {
        current = item;
        opener = trigger || null;
        thumb.src = deps.thumb(item);
        name.textContent = deps.displayName(item);
        meta.textContent = deps.meta(item);
        download.href = item.url;
        download.setAttribute('download', deps.displayName(item));
        cpModal.open(deps.id, trigger);
    }

    sheet.addEventListener('click', function (e) {
        var action = e.target.closest('[data-sheet-action]');
        if (!action || !current) return;

        // The download row is a plain link, so it needs no handler
        var handler = handlers[action.getAttribute('data-sheet-action')];
        var item = current;
        cpModal.close(deps.id);
        if (handler) handler(item, opener);
    });

    return { open: open };
};
