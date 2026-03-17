{% extends "front.lex.php" %}

{% block title %}Register{% endblock %}

{% block body %}
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
        <p class="auth__subtitle">Start publishing in minutes.</p>
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

        <div class="auth__grid">
          <div class="auth__field">
            <label class="auth__label" for="first_name">First name</label>
            <input class="auth__input"
                   type="text"
                   name="first_name"
                   id="first_name"
                   value="{{ old('first_name') }}"
                   autocomplete="given-name"
                   required
                   autofocus>
            {% if errors.first_name|notempty %}
              {% foreach ($errors["first_name"] as $msg): %}
                <p class="mt-1 text-xs text-red"> <?= $msg ?> </p>
              {% endforeach %}
            {% endif %}
          </div>

          <div class="auth__field">
            <label class="auth__label" for="last_name">Last name</label>
            <input class="auth__input"
                   type="text"
                   name="last_name"
                   id="last_name"
                   value="{{ old('last_name') }}"
                   autocomplete="family-name"
                   required>
            {% if errors.last_name|notempty %}
              {% foreach ($errors["last_name"] as $msg): %}
                <p class="mt-1 text-xs text-red"> <?= $msg ?> </p>
              {% endforeach %}
            {% endif %}
          </div>

          <div class="auth__field">
            <label class="auth__label" for="username">Username</label>
            <input class="auth__input"
                   type="text"
                   name="username"
                   id="username"
                   value="{{ old('username') }}"
                   autocomplete="username"
                   autocapitalize="none"
                   spellcheck="false"
                   required>
            {% if errors.username|notempty %}
              {% foreach ($errors["username"] as $msg): %}
                <p class="mt-1 text-xs text-red"> <?= $msg ?> </p>
              {% endforeach %}
            {% endif %}
          </div>

          <div class="auth__field">
            <label class="auth__label" for="email">Email</label>
            <input class="auth__input"
                   type="email"
                   name="email"
                   id="email"
                   value="{{ old('email') }}"
                   autocomplete="email"
                   inputmode="email"
                   required>

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

                <input
                    class="auth__input"
                    type="password"
                    name="password"
                    id="password"
                    autocomplete="new-password"
                    required
                >

                {% if errors.password|notempty %}
                  {% foreach ($errors["password"] as $msg): %}
                    <p class="mt-1 text-xs text-red"> <?= $msg ?> </p>
                  {% endforeach %}
                {% endif %}

                <div class="auth__tooltip" id="password_tip" role="tooltip" hidden>
                    Use 12+ characters (a passphrase is best). Avoid reused passwords.
                </div>
            </div>


          <div class="auth__field">
            <label class="auth__label" for="confirm_password">Confirm password</label>
            <input class="auth__input"
                   type="password"
                   name="confirm_password"
                   id="confirm_password"
                   autocomplete="new-password"
                   required>

           {% if errors.confirm_password|notempty %}
              {% foreach ($errors["confirm_password"] as $msg): %}
                <p class="mt-1 text-xs text-red"> <?= $msg ?> </p>
              {% endforeach %}
            {% endif %}
            
          </div>
        </div>

        <button type="submit" class="button primary fit">Create account</button>

        <div class="auth__links">
          <a href="/login">Already have an account? Sign In</a>
        </div>
      </form>
    </div>
  </div>
</section>
{% endblock %}

{% block scripts %}
<script src="/assets/js/tooltip.js"></script>
{% endblock %}