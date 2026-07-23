/**
 * Language switcher: open, close, position. Nothing else.
 *
 * The entries are rendered by the server from this page's own alternates, so
 * switching language needs no JavaScript at all. This file only makes the
 * dropdown behave like one, and the page degrades to a plain visible list
 * without it.
 */
(function () {
  'use strict';

  var GAP = 8;

  function parts(root) {
    return {
      menu: root.querySelector('[data-lang-menu]'),
      toggle: root.querySelector('[data-lang-toggle]')
    };
  }

  function close(root) {
    var p = parts(root);
    if (!p.menu || !p.toggle) return;
    p.menu.classList.remove('show');
    p.toggle.setAttribute('aria-expanded', 'false');
  }

  function position(root) {
    var p = parts(root);
    if (!p.menu || !p.toggle) return;

    var rect = p.toggle.getBoundingClientRect();
    var viewportH = (window.visualViewport && window.visualViewport.height) || window.innerHeight;

    // Measured while still hidden: without .show the menu has no layout box, so
    // we force one for the measurement and put the inline styles back.
    var prevDisplay = p.menu.style.display;
    var prevVisibility = p.menu.style.visibility;
    p.menu.style.display = 'block';
    p.menu.style.visibility = 'hidden';
    var menuHeight = Math.ceil(p.menu.getBoundingClientRect().height);
    p.menu.style.display = prevDisplay;
    p.menu.style.visibility = prevVisibility;

    var spaceBelow = viewportH - rect.bottom;
    var spaceAbove = rect.top;

    // Vertical placement only. Width belongs to the stylesheet, which each theme
    // sets for itself; an inline min-width here silently overrode it and squeezed
    // the menu down to the width of the little "EN" button.
    p.menu.style.top = '';
    p.menu.style.bottom = '';
    p.menu.style.maxHeight = '';
    p.menu.style.overflowY = '';

    // Flip above the button when there is not enough room below it.
    if (spaceBelow < menuHeight + GAP && spaceAbove > spaceBelow) {
      p.menu.style.top = 'auto';
      p.menu.style.bottom = (Math.ceil(rect.height) + GAP) + 'px';

      if (spaceAbove - GAP > 0 && menuHeight > spaceAbove - GAP) {
        p.menu.style.maxHeight = Math.floor(spaceAbove - GAP) + 'px';
        p.menu.style.overflowY = 'auto';
      }

      return;
    }

    p.menu.style.bottom = 'auto';
    p.menu.style.top = (Math.ceil(rect.height) + GAP) + 'px';

    if (spaceBelow - GAP > 0 && menuHeight > spaceBelow - GAP) {
      p.menu.style.maxHeight = Math.floor(spaceBelow - GAP) + 'px';
      p.menu.style.overflowY = 'auto';
    }
  }

  var roots = Array.prototype.slice.call(document.querySelectorAll('[data-lang-switcher]'));

  roots.forEach(function (root) {
    var p = parts(root);
    if (!p.menu || !p.toggle) return;

    p.toggle.addEventListener('click', function (e) {
      e.preventDefault();
      var open = p.menu.classList.contains('show');
      if (!open) position(root);
      p.menu.classList.toggle('show', !open);
      p.toggle.setAttribute('aria-expanded', String(!open));
    });

    window.addEventListener('resize', function () {
      if (p.menu.classList.contains('show')) position(root);
    });
  });

  document.addEventListener('click', function (e) {
    roots.forEach(function (root) {
      if (!root.contains(e.target)) close(root);
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    roots.forEach(close);
  });
})();
