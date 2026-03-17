{% extends "front.lex.php" %}

{% block title %}Reset Password{% endblock %}

{% block body %}
<section class="auth">
  <div class="auth__wrap">
    <div class="auth__card">

      <div class="auth__hero" aria-hidden="true">
        <i class="fa fa-unlock-alt auth__heroFa" aria-hidden="true"></i>
        {# We keep icons decorative only; the visible heading carries meaning. #}
      </div>

      <header class="auth__header">
        <h2 class="auth__title">Reset password</h2>
        <p class="auth__subtitle">Choose a new password for your account.</p>
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

      <form class="auth__form" action="/password/reset" method="post">
        <?= csrf_field(); ?>
        <input type="hidden" name="token" value="{{ token }}">
        <input type="hidden" name="email" value="{{ email }}">

        <label class="auth__label" for="password">New password</label>
        <input
          class="auth__input"
          type="password"
          name="password"
          id="password"
          autocomplete="new-password"
          required
        >

        <label class="auth__label" for="password_confirm">Confirm new password</label>
        <input
          class="auth__input"
          type="password"
          name="password_confirm"
          id="password_confirm"
          autocomplete="new-password"
          requiredd
        >

        <button class="button primary fit" type="submit">
          <i class="fa fa-save" aria-hidden="true"></i>
          Reset password
        </button>

        <div class="auth__links">
          <a href="/login">Back to sign in</a>
        </div>
      </form>
    </div>
  </div>
</section>
{% endblock %}
