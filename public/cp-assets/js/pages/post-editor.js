/**
 * Post editor page: keeps the settings rows, cover strip, counters and word
 * count in step with the fields, and runs the focus switch, the save shortcut
 * and the check shown before publishing. Loads after post.js.
 */
(function () {
    'use strict';

    var editor = document.querySelector('[data-editor]');
    if (!editor) return;

    var form = editor.closest('form');
    var WORDS_PER_MINUTE = 200;
    var FOCUS_KEY = 'postEditorFocus';

    // Tailwind's display classes outrank the hidden attribute, so visibility goes through a class.
    function show(el, on, display) {
        el.classList.toggle('hidden', !on);
        if (display) el.classList.toggle(display, on);
    }

    function field(name) {
        return form.querySelector('[name="' + name + '"]');
    }

    function filled(names) {
        return names.some(function (name) {
            var el = field(name);
            return el && el.value.trim() !== '';
        });
    }

    function organizeValue() {
        var category = field('category_id');
        var tags = field('tags');
        var parts = [];
        if (category && category.value) parts.push(category.options[category.selectedIndex].text.trim());
        var count = tags && tags.value ? tags.value.split(',').filter(Boolean).length : 0;
        if (count > 0) parts.push(count === 1 ? '1 tag' : count + ' tags');

        return parts.join(', ') || 'None';
    }

    function optionsValue() {
        var comments = field('comments_enabled');
        var featured = field('is_featured');
        var parts = [comments && comments.checked ? 'Comments on' : 'Comments off'];
        if (featured && featured.checked) parts.push('featured');

        return parts.join(', ');
    }

    var rowValues = {
        link: function () { return field('slug') ? field('slug').value : ''; },
        summary: function () { return filled(['excerpt']) ? 'Your own' : 'Automatic'; },
        organize: organizeValue,
        options: optionsValue,
        search: function () {
            return filled(['focus_keyword', 'meta_title', 'meta_description', 'canonical_url']) ? 'Your own' : 'Automatic';
        },
        social: function () {
            return filled(['og_title', 'og_description', 'og_image', 'twitter_title', 'twitter_description', 'twitter_image'])
                ? 'Your own' : 'Automatic';
        },
    };

    function paintRows() {
        form.querySelectorAll('[data-row-value]').forEach(function (el) {
            var read = rowValues[el.dataset.rowValue];
            if (read) el.textContent = read();
        });
    }

    // The cover dialog holds the real upload field; the strip above the title mirrors it.
    function coverUrl() {
        var current = form.querySelector('.current-image-section');
        var saved = current && current.style.display !== 'none' ? current.querySelector('img[src]') : null;
        if (saved && saved.getAttribute('src')) return saved.getAttribute('src');
        var dropped = form.querySelector('#postCoverDialog [data-dz-thumbnail]');

        return dropped ? dropped.getAttribute('src') || '' : '';
    }

    function paintCover() {
        var shown = form.querySelector('[data-cover-shown]');
        var empty = form.querySelector('[data-cover-empty]');
        if (!shown || !empty) return;
        var url = coverUrl();
        if (url) shown.querySelector('[data-cover-image]').src = url;
        show(shown, url !== '', 'block');
        show(empty, url === '');
    }

    function paintCounter(input) {
        var out = form.querySelector('[data-chars-left-for="' + input.id + '"]');
        if (!out) return;
        var left = input.maxLength - input.value.length;
        out.textContent = left + ' left';
        // A threshold below the limit keeps the counter out of sight until it matters.
        show(out, left <= parseInt(input.dataset.charsLeft, 10));
    }

    function paintSummary() {
        var auto = form.querySelector('[data-auto-summary]');
        var clear = form.querySelector('[data-summary-clear]');
        var own = field('excerpt');
        if (!auto || !own) return;
        // Read from the editor itself: its text box only catches up when the editor saves into it.
        var tiny = window.tinymce && window.tinymce.get('content');
        var html = tiny && tiny.initialized ? tiny.getContent() : undefined;
        var text = window.contentSummary ? window.contentSummary(160, html) : '';
        auto.textContent = text || 'The opening of your post appears here once you start writing.';
        if (clear) show(clear, own.value.trim() !== '');
    }

    function paintAll() {
        paintRows();
        paintCover();
        paintSummary();
    }

    function paintWords(tiny) {
        var out = document.querySelector('[data-word-count]');
        if (!out || !tiny.plugins.wordcount) return;
        var words = tiny.plugins.wordcount.body.getWordCount();
        var minutes = Math.max(1, Math.round(words / WORDS_PER_MINUTE));
        out.textContent = (words === 1 ? '1 word' : words + ' words') + ', ' + minutes + ' min read';
        out.classList.toggle('sm:inline', words > 0);
    }

    function save() {
        var intent = editor.dataset.saveIntent;
        var button = intent ? form.querySelector('[data-actionbar] button[name="intent"][value="' + intent + '"]') : null;
        if (button) button.click();
    }

    function whenEditorReady(run) {
        var tiny = window.tinymce && window.tinymce.get('content');
        if (tiny && tiny.initialized) {
            run(tiny);
            return;
        }
        setTimeout(function () { whenEditorReady(run); }, 150);
    }

    function bindEditor(tiny) {
        if (tiny.postEditorBound) return;
        tiny.postEditorBound = true;
        tiny.addShortcut('meta+s', 'Save', save);
        tiny.on('input change keyup SetContent', function () { paintWords(tiny); });
        tiny.on('change', paintSummary);
        paintWords(tiny);
        paintSummary();
    }

    function setFocus(on) {
        var side = editor.querySelector('[data-editor-side]');
        var button = document.querySelector('[data-focus-toggle]');
        if (side) show(side, !on);
        if (button) button.setAttribute('aria-pressed', String(on));
        try { localStorage.setItem(FOCUS_KEY, on ? '1' : ''); } catch (e) { /* private mode keeps no settings */ }
    }

    function initFocus() {
        var button = document.querySelector('[data-focus-toggle]');
        if (!button) return;
        var remembered = '';
        try { remembered = localStorage.getItem(FOCUS_KEY) || ''; } catch (e) { remembered = ''; }
        // A rejected save needs its marked row in view, whatever was remembered.
        if (remembered && !form.querySelector('[data-modal-open-on-load]')) setFocus(true);
        button.addEventListener('click', function () {
            setFocus(button.getAttribute('aria-pressed') !== 'true');
        });
    }

    function initPublishCheck() {
        var notes = form.querySelector('[data-publish-notes]');

        function paintNotes() {
            if (!notes) return;
            var category = field('category_id');
            var missing = { cover: coverUrl() === '', category: !category || category.value === '' };
            var any = false;
            notes.querySelectorAll('[data-publish-note]').forEach(function (note) {
                var applies = missing[note.dataset.publishNote];
                show(note, applies, 'flex');
                any = any || applies;
            });
            show(notes, any);
        }

        form.querySelectorAll('[data-publish-check]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                if (!window.cpModal) return;
                event.preventDefault();
                paintNotes();
                window.cpModal.open(button.dataset.publishCheck, button);
            });
        });

        form.querySelectorAll('[data-publish-fix]').forEach(function (button) {
            button.addEventListener('click', function () {
                window.cpModal.open(button.dataset.publishFix, button);
            });
        });

        form.querySelectorAll('[modal-center]').forEach(function (dialog) {
            dialog.addEventListener('modal:close', function () {
                paintAll();
                paintNotes();
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        form.querySelectorAll('[data-chars-left]').forEach(function (input) {
            paintCounter(input);
            input.addEventListener('input', function () { paintCounter(input); });
        });

        form.addEventListener('change', paintAll);
        // The link follows the title through another script's input handler, so read it after that ran.
        form.addEventListener('input', function () { setTimeout(paintAll, 0); });

        // Autosave can settle on a different link than the one typed.
        if (field('slug')) field('slug').addEventListener('slug:saved', paintRows);

        var clear = form.querySelector('[data-summary-clear]');
        if (clear) {
            clear.addEventListener('click', function () {
                var own = field('excerpt');
                own.value = '';
                own.dispatchEvent(new Event('input', { bubbles: true }));
            });
        }

        document.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && !event.altKey && event.key.toLowerCase() === 's') {
                event.preventDefault();
                save();
            }
        });

        whenEditorReady(bindEditor);
        // Switching between light and dark starts a fresh editor, which needs the same wiring.
        if (window.tinymce) {
            window.tinymce.on('AddEditor', function (event) {
                event.editor.on('init', function () { bindEditor(event.editor); });
            });
        }

        initFocus();
        initPublishCheck();
        paintAll();
    });
})();
