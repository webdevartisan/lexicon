<script nonce="<?= csp_nonce() ?>">
  // Shared behaviour for _confirm_modal.lex.php dialogs. Any element with
  // data-confirm-open="<modalId>" opens the matching dialog; confirming submits
  // the form named by the dialog's data-confirm-form. Programmatic form.submit()
  // does not refire the submit event, so no re-entrancy guard is needed.
  (function () {
    document.querySelectorAll('.lx-modal[data-confirm-form]').forEach(function (modal) {
      const form = document.getElementById(modal.getAttribute('data-confirm-form'));
      const ok = modal.querySelector('[data-confirm-ok]');
      let opener = null;

      function open(trigger) { opener = trigger || null; modal.hidden = false; if (ok) ok.focus(); }
      function close() { modal.hidden = true; if (opener) opener.focus(); }

      modal.open = open;
      modal.querySelectorAll('[data-close]').forEach(function (el) { el.addEventListener('click', close); });
      modal.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
      if (ok) ok.addEventListener('click', function () { close(); if (form) form.submit(); });
    });

    document.querySelectorAll('[data-confirm-open]').forEach(function (trigger) {
      trigger.addEventListener('click', function (e) {
        e.preventDefault();
        const modal = document.getElementById(trigger.getAttribute('data-confirm-open'));
        if (modal && modal.open) modal.open(trigger);
      });
    });
  })();
</script>
