(function () {
  // One open reply form at a time
  function closeAll() {
    document.querySelectorAll('.reply-form').forEach(function (f) { f.setAttribute('hidden', ''); });
  }

  document.addEventListener('click', function (ev) {
    var toggle = ev.target.closest('[data-reply-toggle]');
    if (toggle) {
      var form = document.getElementById('reply-form-' + toggle.getAttribute('data-reply-toggle'));
      var wasHidden = form.hasAttribute('hidden');
      closeAll();
      if (wasHidden) {
        form.removeAttribute('hidden');
        form.querySelector('textarea').focus();
      }
      return;
    }

    var cancel = ev.target.closest('[data-reply-cancel]');
    if (cancel) { closeAll(); return; }

    var collapse = ev.target.closest('[data-collapse-toggle]');
    if (collapse) {
      var list = document.getElementById('replies-' + collapse.getAttribute('data-collapse-toggle'));
      var show = list.hasAttribute('hidden');
      list.toggleAttribute('hidden', !show);
      collapse.textContent = collapse.getAttribute(show ? 'data-label-hide' : 'data-label-show');
    }
  });
})();
