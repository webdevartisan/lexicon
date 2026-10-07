(function () {
  // Open state lives here rather than in PHP because the sidebar is cached
  // without the request path in its key, so a server-rendered "expanded"
  // flag would be wrong on every page that reused the cached copy.
  var here = window.location.pathname.replace(/\/+$/, '');
  var STORAGE_PREFIX = 'lx-nav-group:';

  // Storage can be blocked or full; then a manual toggle just isn't remembered.
  function remembered(key) {
    try {
      return window.localStorage.getItem(STORAGE_PREFIX + key);
    } catch (e) {
      return null;
    }
  }

  function remember(key, open) {
    try {
      window.localStorage.setItem(STORAGE_PREFIX + key, open ? 'open' : 'closed');
    } catch (e) {
      // Nothing to do: the group still works, it just won't stay as it was left.
    }
  }

  function samePath(link) {
    return link.getAttribute('data-nav-path').replace(/\/+$/, '') === here;
  }

  document.querySelectorAll('[data-nav-group]').forEach(function (group) {
    var submenu = group.querySelector('[data-nav-submenu]');
    var toggle = group.querySelector('[data-nav-toggle]');
    if (!submenu || !toggle) return;

    var key = group.getAttribute('data-nav-group-key') || '';

    function setOpen(open) {
      submenu.classList.toggle('hidden', !open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      // The chevron turns from a class on the button: lucide swaps the <i> for
      // an <svg> of its own, so a reference to the original icon goes stale.
      toggle.classList.toggle('is-open', open);
    }

    var base = (group.getAttribute('data-nav-group-path') || '').replace(/\/+$/, '');
    // - If we are on the group’s base path or a child of it, keep it open.
    // - Otherwise, always start closed (ignore remembered state).
    var inside = base !== '' && (here === base || here.indexOf(base + '/') === 0);

    setOpen(inside);

    // On a child page the parent link is not the active row, and on the
    // icon-only rail the children are not rendered at all, so the parent
    // carries a softer marker for the section you are standing in.
    var parentLink = group.querySelector('.sidebar-menu-item[data-nav-path]');
    var childMatches = Array.prototype.some.call(submenu.querySelectorAll('[data-nav-path]'), samePath);
    if (parentLink && inside && (here !== base || childMatches)) {
      parentLink.classList.add('active-within');
      // A child is the active row then, even one that shares the parent's address.
      parentLink.setAttribute('data-nav-skip-active', '');
    }

    toggle.addEventListener('click', function () {
      var open = submenu.classList.contains('hidden');
      setOpen(open);
      remember(key, open);
    });
  });

  // Nothing else marks the active row, so do it here for parents and children
  document.querySelectorAll('.sidebar-menu-item[data-nav-path]').forEach(function (link) {
    if (samePath(link) && !link.hasAttribute('data-nav-skip-active')) {
      link.classList.add('active');
      link.setAttribute('aria-current', 'page');
    }
  });

  // Reveal the active item after page-load initialization.
  function scrollActiveIntoView() {
    requestAnimationFrame(function () {
      var active = document.querySelector('.sidebar-menu-item.active');
      if (!active) return;

      var target = active;

      // Compact sidebar: use the visible parent instead of a hidden child.
      if (!active.getClientRects().length) {
        var group = active.closest('[data-nav-group]');
        if (!group) return;

        target = group.querySelector(
          '.sidebar-menu-row > .sidebar-menu-item[data-nav-path]'
        );
      }

      if (!target || !target.getClientRects().length) return;

      target.scrollIntoView({
        behavior: 'instant',
        block: 'center',
        inline: 'nearest'
      });
    });
  }

  if (document.readyState === 'complete') {
    scrollActiveIntoView();
  } else {
    window.addEventListener('load', scrollActiveIntoView, { once: true });
  }
})();
