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

      <header class="auth__header">
        <h2 class="auth__title">Sign in</h2>
        <p class="auth__subtitle">Welcome back. Sign in to continue.</p>
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
        <input
          class="auth__input"
          type="password"
          name="password"
          id="password"
          autocomplete="current-password"
          required
          <?= $email ? 'autofocus' : ''?>
        >

        <button class="button primary fit" type="submit">Login</button>

        <div class="auth__links">
          <a href="/register">Register</a>
          <a href="/password/forgot">Forgot password?</a>
        </div>
      </form>
    </div>
  </div>
</section>
{% endblock %}
