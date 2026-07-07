{% extends "front.lex.php" %}

{% block title %}Invitation Declined{% endblock %}

{% block body %}
<section class="auth">
  <div class="auth__wrap">
    <div class="auth__card">
      <header class="auth__header">
        <h2 class="auth__title">Invitation declined</h2>
        <p class="auth__subtitle">You've declined the invitation. The blog owner has been notified.</p>
      </header>
      <div class="mt-4 text-center">
        <a href="/" class="text-sm font-medium text-custom-600 hover:underline">Return home</a>
      </div>
    </div>
  </div>
</section>
{% endblock %}
