{% extends "front.lex.php" %}

{% block title %}Forgot Password{% endblock %}

{% block body %}
<section class="auth">
  <div class="auth__wrap">
    <div class="auth__card">
      <!-- We keep the SVG inline to avoid third‑party asset requests and extra CSP allowances. -->
      <div class="auth__hero" aria-hidden="true">
        <svg class="auth__heroIcon" viewBox="0 0 64 64" role="img" focusable="false">
          <path d="M32 6c9.4 0 17 7.6 17 17 0 5.2-2.3 9.9-6 13.1" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
          <path d="M15 27c0-9.4 7.6-17 17-17" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" opacity=".35"/>
          <path d="M22 30h20c2.2 0 4 1.8 4 4v16c0 2.2-1.8 4-4 4H22c-2.2 0-4-1.8-4-4V34c0-2.2 1.8-4 4-4Z" fill="currentColor" opacity=".12"/>
          <path d="M22 34l10 8 10-8" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>

      <header class="auth__header">
        <h2 class="auth__title">Forgot your password?</h2>
        <p class="auth__subtitle">Enter your email and we’ll send a reset link.</p>
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

      <form class="auth__form" action="/password/forgot" method="post">
        <?= csrf_field(); ?>

        <label class="auth__label" for="email">Email</label>
        <input
          class="auth__input"
          type="email"
          name="email"
          id="email"
          value="{{ email }}"
          autocomplete="email"
          inputmode="email"
          required
          autofocus
        >

        <button class="button primary fit" type="submit">Send reset link</button>

        <div class="auth__links">
          <a href="/login">Back to sign in</a>
          <a href="/register">Register</a>
        </div>
      </form>
    </div>
  </div>
</section>
{% endblock %}
