(function () {
  // Open state lives here rather than in PHP because the sidebar is cached
  // without the request path in its key, so a server-rendered "expanded"
  // flag would be wrong on every page that reused the cached copy.
  var here = window.location.pathname.replace(/\/+$/, '');

  document.querySelectorAll('[data-nav-group]').forEach(function (group) {
    var submenu = group.querySelector('[data-nav-submenu]');
    var toggle = group.querySelector('[data-nav-toggle]');
    var chevron = group.querySelector('[data-nav-chevron]');
    if (!submenu || !toggle) return;

    function setOpen(open) {
      submenu.classList.toggle('hidden', !open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (chevron) chevron.style.transform = open ? 'rotate(180deg)' : '';
    }

    var base = (group.getAttribute('data-nav-group-path') || '').replace(/\/+$/, '');
    var inside = base !== '' && (here === base || here.indexOf(base + '/') === 0);

    setOpen(inside);
    toggle.addEventListener('click', function () {
      setOpen(submenu.classList.contains('hidden'));
    });
  });

  // Nothing else marks the active row, so do it here for parents and children
  document.querySelectorAll('.sidebar-menu-item[data-nav-path]').forEach(function (link) {
    if (link.getAttribute('data-nav-path').replace(/\/+$/, '') === here) {
      link.classList.add('active');
      link.setAttribute('aria-current', 'page');
    }
  });
})();
