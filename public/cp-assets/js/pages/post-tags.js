/**
 * Tag field of the post editor: chips for the chosen tags, suggestions from
 * the blog's existing tags while typing, and one-tap chips for the most used.
 */
(function () {
    var field = document.getElementById('tagsField');
    var chips = document.getElementById('tagChips');
    var input = document.getElementById('tagInput');
    var panel = document.getElementById('tagSuggestions');
    if (!field || !chips || !input || !panel) return;

    var tags = field.value ? field.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean) : [];
    var quick = document.querySelectorAll('[data-quick-tag]');

    var known = [];
    try { known = JSON.parse(input.dataset.tags || '[]'); } catch (e) { known = []; }

    var activeIndex = -1;
    var visible = [];

    function sync() {
        field.value = tags.join(',');
        chips.innerHTML = '';
        tags.forEach(function (name, i) {
            var chip = document.createElement('span');
            chip.className = 'inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-full bg-custom-50 text-custom-700 border border-custom-100 dark:bg-custom-500/20 dark:text-custom-300';
            chip.textContent = name;
            var x = document.createElement('button');
            x.type = 'button';
            x.className = 'leading-none text-sm hover:text-red-600';
            x.setAttribute('aria-label', 'Remove ' + name);
            x.innerHTML = '&times;';
            x.addEventListener('click', function () { tags.splice(i, 1); changed(); });
            chip.appendChild(x);
            chips.appendChild(chip);
        });
        quick.forEach(function (button) {
            button.classList.toggle('hidden', tags.indexOf(button.dataset.quickTag) !== -1);
            button.classList.toggle('inline-flex', tags.indexOf(button.dataset.quickTag) === -1);
        });
    }

    // Tells the rest of the page the tags changed, as a typed field would.
    function changed() {
        sync();
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function add(value) {
        value = value.trim().replace(/,+$/, '').trim();
        if (value && tags.indexOf(value) === -1) tags.push(value);
        input.value = '';
        closePanel();
        changed();
    }

    function closePanel() {
        panel.classList.add('hidden');
        input.setAttribute('aria-expanded', 'false');
        activeIndex = -1;
        visible = [];
    }

    function paintPanel() {
        panel.innerHTML = '';
        visible.forEach(function (name, i) {
            var item = document.createElement('button');
            item.type = 'button';
            item.setAttribute('role', 'option');
            item.className = 'flex w-full px-3 py-1.5 text-sm text-left transition-colors '
                + (i === activeIndex
                    ? 'bg-custom-50 text-custom-700 dark:bg-custom-500/20 dark:text-custom-300'
                    : 'text-slate-600 hover:bg-slate-50 dark:text-zink-200 dark:hover:bg-zink-500');
            item.textContent = name;
            // mousedown fires before the input's blur, so the click always lands
            item.addEventListener('mousedown', function (e) {
                e.preventDefault();
                add(name);
            });
            panel.appendChild(item);
        });
        panel.classList.toggle('hidden', visible.length === 0);
        input.setAttribute('aria-expanded', visible.length ? 'true' : 'false');
    }

    function suggest() {
        var q = input.value.trim().toLowerCase();
        if (q === '') { closePanel(); paintPanel(); return; }
        visible = known.filter(function (name) {
            return tags.indexOf(name) === -1 && name.toLowerCase().indexOf(q) !== -1;
        }).slice(0, 8);
        activeIndex = -1;
        paintPanel();
    }

    input.addEventListener('input', suggest);
    input.addEventListener('focus', suggest);

    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' && visible.length) {
            e.preventDefault();
            activeIndex = (activeIndex + 1) % visible.length;
            paintPanel();
        } else if (e.key === 'ArrowUp' && visible.length) {
            e.preventDefault();
            activeIndex = (activeIndex - 1 + visible.length) % visible.length;
            paintPanel();
        } else if (e.key === 'Escape' && visible.length) {
            // Only the list closes; the dialog around it stays open.
            e.stopPropagation();
            closePanel();
            paintPanel();
        } else if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            add(activeIndex >= 0 && visible[activeIndex] ? visible[activeIndex] : input.value);
        } else if (e.key === 'Backspace' && input.value === '' && tags.length) {
            tags.pop();
            changed();
        }
    });

    input.addEventListener('blur', function () {
        closePanel();
        paintPanel();
        if (input.value.trim()) add(input.value);
    });

    quick.forEach(function (button) {
        button.addEventListener('click', function () { add(button.dataset.quickTag); });
    });

    sync();
})();
