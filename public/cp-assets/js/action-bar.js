(function () {
  document.addEventListener('click', function (e) {
    document.querySelectorAll('[data-actionbar-menu][open]').forEach(function (menu) {
      if (!menu.contains(e.target)) menu.open = false;
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('[data-actionbar-menu][open]').forEach(function (menu) {
      menu.open = false;
    });
  });
})();
