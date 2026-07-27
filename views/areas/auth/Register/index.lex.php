{% extends "front.lex.php" %}

{% block title %}Create account{% endblock %}

{% block body %}
<?php
// Keep the reading context alive across the login/register hop
$returnTo = !empty($return_to) ? $return_to : (old('return_to') ?? '');
$loginHref = '/login'.($returnTo !== '' ? '?return_to='.urlencode($returnTo) : '');
?>
<section class="auth">
  <div class="auth__wrap">
    <div class="auth__card">
      <div class="auth__hero" aria-hidden="true">
        <svg class="auth__heroIcon" viewBox="0 0 64 64" role="img" focusable="false">
          <path d="M32 34c8.3 0 15-6.7 15-15S40.3 4 32 4 17 10.7 17 19s6.7 15 15 15Z" fill="currentColor" opacity=".15"/>
          <path d="M12 58c2.8-10.9 10.7-18 20-18s17.2 7.1 20 18" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
          <path d="M44 24v8M40 28h8" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
        </svg>
      </div>

      <header class="auth__header">
        <h2 class="auth__title">Create your account</h2>
        <p class="auth__subtitle">Save posts, follow blogs, join discussions.</p>
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

      <form class="auth__form" action="/register/submit" method="post" autocomplete="on">
        <?= csrf_field(); ?>
        <?php if ($returnTo !== '') { ?>
          <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
        <?php } ?>

        <div class="auth__field">
          <label class="auth__label" for="email">Email</label>
          <input class="auth__input"
                 type="email"
                 name="email"
                 id="email"
                 value="{{ old('email') }}"
                 autocomplete="email"
                 inputmode="email"
                 required
                 autofocus>

          {% if errors.email|notempty %}
            {% foreach ($errors["email"] as $msg): %}
              <p class="mt-1 text-xs text-red"> <?= $msg ?> </p>
            {% endforeach %}
          {% endif %}
        </div>

        <div class="auth__field">
          <div class="auth__labelRow">
            <label class="auth__label" for="password">Password</label>

            <button
                class="auth__info auth__info--fa"
                type="button"
                aria-label="Password rules"
                aria-describedby="password_tip"
                data-tooltip="password_tip"
            ></button>
          </div>

          <div class="auth__inputRow" style="position: relative;">
            <input
                class="auth__input"
                type="password"
                name="password"
                id="password"
                autocomplete="new-password"
                required
                style="padding-right: 3.5rem;"
            >
            <button type="button"
                    id="password_toggle"
                    aria-label="Show password"
                    aria-pressed="false"
                    style="position: absolute; top: 50%; right: .75rem; transform: translateY(-50%); background: none; border: 0; cursor: pointer; font-size: .75rem; letter-spacing: .05em; text-transform: uppercase; opacity: .7;">Show</button>
          </div>

          {% if errors.password|notempty %}
            {% foreach ($errors["password"] as $msg): %}
              <p class="mt-1 text-xs text-red"> <?= $msg ?> </p>
            {% endforeach %}
          {% endif %}

          <div class="auth__tooltip" id="password_tip" role="tooltip" hidden>
              Use 12+ characters (a passphrase is best). Avoid reused passwords.
          </div>
        </div>

        <button type="submit" class="button primary fit">Create account</button>

        <p class="auth__subtitle" style="font-size: .8rem; margin-top: .75rem;">
          We'll pick a username from your email — you can change it any time in your profile.
        </p>

        <div class="auth__links">
          <a href="<?= e($loginHref) ?>">Already have an account? Sign In</a>
        </div>
      </form>
    </div>
  </div>
</section>
{% endblock %}

{% block scripts %}
<script src="/assets/js/tooltip.js"></script>
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
