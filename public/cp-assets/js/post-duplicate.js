/**
 * The Duplicate dialog on the Posts list. A [data-post-duplicate] button holds the
 * post's duplicate URL: the dialog asks it for a free title and address, shows them
 * for editing, and posts the form back to the same URL.
 *
 * Each opening starts from a fresh copy of the form template, so the address field
 * follows the title again and no feedback from the last post is left behind.
 */
(function () {
    var modalId = 'postDuplicateModal';
    var modal = document.getElementById(modalId);
    var template = document.getElementById('postDuplicateTemplate');
    if (!modal || !template || !window.cpModal) return;

    var slot = modal.querySelector('[data-duplicate-slot]');
    var confirm = modal.querySelector('.confirm-button-ok');
    var latest = 0;

    function note(text) {
        var p = document.createElement('p');
        p.className = 'confirm-message text-sm text-center';
        p.textContent = text;
        slot.replaceChildren(p);
    }

    function fill(url, suggestion) {
        var form = template.content.firstElementChild.cloneNode(true);
        form.action = url;
        form.querySelector('[name="title"]').value = suggestion.title;
        form.querySelector('[name="slug"]').value = suggestion.slug;
        slot.replaceChildren(form);
        confirm.disabled = false;

        if (window.SlugField) {
            window.SlugField.bind({ root: form, source: 'title', target: 'slug', min: 2, max: 100, checkUrl: form.dataset.checkUrl });
        }
        var title = form.querySelector('[name="title"]');
        title.focus();
        title.select();
    }

    // The menu that held the button closes, so focus goes back to its toggle instead.
    function returnPoint(button) {
        var menu = button.closest('[data-row-actions], .dropdown');
        return (menu && menu.querySelector('[data-row-actions-toggle], .dropdown-toggle')) || button;
    }

    document.addEventListener('click', function (e) {
        var button = e.target.closest('[data-post-duplicate]');
        if (!button) return;

        var url = button.getAttribute('data-post-duplicate');
        var request = ++latest;
        confirm.disabled = true;
        note(template.dataset.loading || 'Preparing the copy…');
        window.cpModal.open(modalId, returnPoint(button));

        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                return r.json().then(function (data) { return { ok: r.ok, data: data }; });
            })
            .then(function (res) {
                if (request !== latest) return;
                if (!res.ok) throw new Error(res.data && res.data.error ? res.data.error : '');
                fill(url, res.data);
            })
            .catch(function (err) {
                if (request !== latest) return;
                note(err.message || template.dataset.failed || 'This post could not be copied right now.');
            });
    });

    modal.addEventListener('modal:close', function () {
        latest++;
    });
})();
