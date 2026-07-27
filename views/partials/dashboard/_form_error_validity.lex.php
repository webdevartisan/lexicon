<script nonce="<?= csp_nonce() ?>">
  // mirror server-side errors into the browser's constraint validation so the
  // :invalid styling matches what the server rejected
  document.addEventListener('DOMContentLoaded', function () {
    const errorFields = document.querySelectorAll('[aria-invalid="true"]');

    errorFields.forEach(function (field) {
      const errorId = field.getAttribute('aria-describedby');
      const errorDiv = document.getElementById(errorId);

      if (errorDiv) {
        const errorText = errorDiv.querySelector('p')?.textContent.trim() || 'Invalid input';

        field.setCustomValidity(errorText);

        field.addEventListener('input', function () {
          field.setCustomValidity('');
        });
        field.classList.remove('dark:valid:border-green-800', 'valid:border-green-500');
      }
    });
  });
</script>
