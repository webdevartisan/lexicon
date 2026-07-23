document.addEventListener('change', function (e) {
  if (e.target.matches('[data-auto-submit]')) {
    e.target.form.submit();
  }
});
