(function () {
  var CHEVRON = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
  var CHECK = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
  var counter = 0;
  var openMenu = null;

  function closeOpen() {
    if (!openMenu) return;
    openMenu.wrap.classList.remove('select-menu-open');
    openMenu.button.setAttribute('aria-expanded', 'false');
    openMenu = null;
  }

  function enhance(select) {
    if (select.dataset.menuReady || select.multiple || select.size > 1 || select.hasAttribute('data-native')) return;
    select.dataset.menuReady = '1';
    counter += 1;

    var wrap = document.createElement('div');
    wrap.className = 'select-menu';
    if (!select.classList.contains('w-auto')) wrap.classList.add('select-menu-block');

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'select-menu-button';
    button.setAttribute('aria-haspopup', 'listbox');
    button.setAttribute('aria-expanded', 'false');
    var labelEl = select.id ? document.querySelector('label[for="' + select.id + '"]') : null;
    if (labelEl) {
      if (!labelEl.id) labelEl.id = 'select-menu-label-' + counter;
      button.setAttribute('aria-labelledby', labelEl.id);
    }
    var text = document.createElement('span');
    text.className = 'select-menu-value';
    var chevron = document.createElement('span');
    chevron.className = 'select-menu-chevron';
    chevron.innerHTML = CHEVRON;
    button.append(text, chevron);

    var list = document.createElement('div');
    list.className = 'select-menu-list';
    list.setAttribute('role', 'listbox');
    list.id = 'select-menu-list-' + counter;
    button.setAttribute('aria-controls', list.id);

    var items = [];

    function addOption(option) {
      var item = document.createElement('button');
      item.type = 'button';
      item.className = 'select-menu-item';
      item.setAttribute('role', 'option');
      item.disabled = option.disabled;
      var label = document.createElement('span');
      label.textContent = option.textContent;
      var mark = document.createElement('span');
      mark.className = 'select-menu-check';
      mark.innerHTML = CHECK;
      item.append(label, mark);
      item.addEventListener('click', function () {
        choose(option.index);
      });
      list.appendChild(item);
      items.push({ el: item, option: option });
    }

    Array.prototype.forEach.call(select.children, function (child) {
      if (child.tagName === 'OPTGROUP') {
        var heading = document.createElement('div');
        heading.className = 'select-menu-group';
        heading.textContent = child.label;
        list.appendChild(heading);
        Array.prototype.forEach.call(child.children, addOption);
      } else if (child.tagName === 'OPTION' && !(child.disabled && child.textContent.trim() === '')) {
        addOption(child);
      }
    });

    var search = null;
    if (items.length > 15) {
      search = document.createElement('input');
      search.type = 'search';
      search.className = 'select-menu-search';
      search.autocomplete = 'off';
      search.setAttribute('aria-label', 'Search');
      list.insertBefore(search, list.firstChild);
      search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase();
        items.forEach(function (it) {
          it.el.hidden = q !== '' && it.option.textContent.toLowerCase().indexOf(q) === -1;
        });
        list.querySelectorAll('.select-menu-group').forEach(function (g) {
          var n = g.nextElementSibling;
          var any = false;
          while (n && !n.classList.contains('select-menu-group')) {
            if (n.classList.contains('select-menu-item') && !n.hidden) any = true;
            n = n.nextElementSibling;
          }
          g.hidden = !any;
        });
      });
    }

    function sync() {
      var current = select.options[select.selectedIndex];
      text.textContent = current ? current.textContent.trim() : '';
      items.forEach(function (it) {
        var on = it.option === current;
        it.el.setAttribute('aria-selected', on ? 'true' : 'false');
        it.el.classList.toggle('select-menu-item-on', on);
      });
      button.disabled = select.disabled;
    }

    function choose(index) {
      select.selectedIndex = index;
      select.dispatchEvent(new Event('change', { bubbles: true }));
      sync();
      close(true);
    }

    function open() {
      if (select.disabled) return;
      closeOpen();
      wrap.classList.add('select-menu-open');
      button.setAttribute('aria-expanded', 'true');
      openMenu = { wrap: wrap, button: button };
      if (search) {
        search.value = '';
        search.dispatchEvent(new Event('input'));
      }
      var on = list.querySelector('.select-menu-item-on') || list.querySelector('.select-menu-item:not(:disabled)');
      if (on) {
        on.scrollIntoView({ block: 'nearest' });
      }
      (search || on || list).focus({ preventScroll: true });
    }

    function close(refocus) {
      if (openMenu && openMenu.wrap === wrap) closeOpen();
      if (refocus) button.focus();
    }

    function move(from, step) {
      var enabled = items.filter(function (it) { return !it.el.disabled && !it.el.hidden; }).map(function (it) { return it.el; });
      var at = enabled.indexOf(from);
      var next = at === -1 && step > 0 ? enabled[0] : enabled[at + step];
      if (next) next.focus();
      else if (at === 0 && search) search.focus();
    }

    button.addEventListener('click', function () {
      if (wrap.classList.contains('select-menu-open')) close(false);
      else open();
    });
    button.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        open();
      }
    });
    list.addEventListener('keydown', function (e) {
      var target = document.activeElement;
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        move(target, 1);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        move(target, -1);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        e.stopPropagation();
        close(true);
      } else if (e.key === 'Tab') {
        close(false);
      } else if (e.key.length === 1 && target !== search) {
        var typed = e.key.toLowerCase();
        var hit = items.filter(function (it) {
          return !it.el.disabled && it.option.textContent.trim().toLowerCase().indexOf(typed) === 0;
        })[0];
        if (hit) hit.el.focus();
      }
    });
    select.addEventListener('change', sync);
    if (select.form) {
      select.form.addEventListener('reset', function () {
        setTimeout(sync, 0);
      });
    }

    select.classList.add('select-menu-native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    select.parentNode.insertBefore(wrap, select);
    wrap.append(button, list, select);
    sync();
  }

  function init(root) {
    (root || document).querySelectorAll('select.form-select').forEach(enhance);
  }

  document.addEventListener('mousedown', function (e) {
    if (openMenu && !openMenu.wrap.contains(e.target)) closeOpen();
  });
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { init(document); });
  } else {
    init(document);
  }
  window.initSelectMenus = init;
})();
