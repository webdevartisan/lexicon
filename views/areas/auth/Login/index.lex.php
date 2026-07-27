{% extends "front.lex.php" %}

{% block title %}Sign In{% endblock %}

{% block body %}
<section class="auth">
  <div class="auth__wrap">
    <div class="auth__card">
      <!-- We keep the SVG inline to avoid third‑party asset requests and extra CSP allowances. -->
      <div class="auth__hero" aria-hidden="true">
        <svg class="auth__heroIcon" viewBox="0 0 64 64" role="img" focusable="false">
          <path d="M32 34c8.3 0 15-6.7 15-15S40.3 4 32 4 17 10.7 17 19s6.7 15 15 15Z" fill="currentColor" opacity=".15"/>
          <path d="M12 58c2.8-10.9 10.7-18 20-18s17.2 7.1 20 18" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
          <path d="M43 30l3 3 7-7" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>

      <?php
      // Keep the reading context alive across the login hop
      $returnTo = !empty($return_to) ? $return_to : '';
      $registerHref = '/register'.($returnTo !== '' ? '?return_to='.urlencode($returnTo) : '');
      ?>
      <header class="auth__header">
        <h2 class="auth__title">Sign in</h2>
        <?php if ($returnTo !== '') { ?>
          <p class="auth__subtitle">Log in to continue where you left off.</p>
        <?php } else { ?>
          <p class="auth__subtitle">Welcome back. Sign in to continue.</p>
        <?php } ?>
      </header>
      <?php $flash = flash(); ?>
      {% if flash|notempty %}
          {% foreach ($flash as $type => $msgs): %}
            {% foreach ($msgs as $msg): %}
              <div class="auth__alert" role="alert">
                {{ msg }}
              </div>
            {% endforeach %}
          {% endforeach %}
      {% endif %}
      {% if (isset($error) && $error) : %}
        <div class="auth__alert" role="alert">
          {{ error }}
        </div>
      {% endif; %}

      <form class="auth__form" action="/login/submit" method="post">
        <?= csrf_field(); ?>
        <?php if ($returnTo !== '') { ?>
          <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
        <?php } ?>

        <label class="auth__label" for="email">Email</label>
        <?php $email = old('email') ?? ''; ?>
        <input
          class="auth__input"
          type="email"
          name="email"
          id="email"
          value="{{ email }}"
          autocomplete="email"
          inputmode="email"
          required
          <?= empty($email) ? 'autofocus' : ''?>
        >

        <label class="auth__label" for="password">Password</label>
        <div class="auth__inputRow" style="position: relative;">
          <input
            class="auth__input"
            type="password"
            name="password"
            id="password"
            autocomplete="current-password"
            required
            style="padding-right: 3.5rem;"
            <?= $email ? 'autofocus' : ''?>
          >
          <button type="button"
                  id="password_toggle"
                  aria-label="Show password"
                  aria-pressed="false"
                  style="position: absolute; top: 50%; right: .75rem; transform: translateY(-50%); background: none; border: 0; cursor: pointer; font-size: .75rem; letter-spacing: .05em; text-transform: uppercase; opacity: .7;">Show</button>
        </div>

        <button class="button primary fit" type="submit">Login</button>

        <div class="auth__links">
          <a href="<?= e($registerHref) ?>">Register</a>
          <a href="/password/forgot">Forgot password?</a>
        </div>
      </form>
    </div>
  </div>
</section>
{% endblock %}

{% block scripts %}
<script nonce="<?= csp_nonce() ?>">
(function () {
  var toggle = document.getElementById('password_toggle');
  var field = document.getElementById('password');
  if (!toggle || !field) return;

  toggle.addEventListener('click', function () {
    var show = field.type === 'password';
    field.type = show ? 'text' : 'password';
    toggle.textContent = show ? 'Hide' : 'Show';
    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
  });
})();
</script>
{% endblock %}
