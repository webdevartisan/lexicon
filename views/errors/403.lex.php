{% extends "error.lex.php" %}

{% block title %}<?= e($status) ?> - <?= e($title) ?>{% endblock %}

{% block body %}

<section class="error-container">
  <div class="error-content">
    
    <!-- Error Header -->
    <div class="error-header">
      <h1 class="error-code"><?= e($status) ?></h1>
      <p class="error-title"><?= e($title) ?></p>
    </div>

    {% if (empty($isDev)): %}
      <!-- User-Friendly Message -->
      <div class="error-message-card">
        <svg class="error-message-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 0h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8V7a4 4 0 00-8 0v4h8z"/>
        </svg>
        <p class="error-message-text">You don't have permission to access this resource. If you believe this is an error, please contact the administrator.</p>
      </div>
    {% else %}
      <!-- Debug Information (Dev Only) -->
      <div class="debug-card">
        <h2 class="debug-header">
          <svg class="debug-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
          Debug Information
        </h2>
        
        <div class="debug-info">
          <div class="debug-row">
            <span class="debug-label">Type:</span>
            <span class="debug-value mono"><?= e($exceptionClass ?? '') ?></span>
          </div>
          
          <div class="debug-row">
            <span class="debug-label">Message:</span>
            <span class="debug-value"><?= e($exceptionMessage ?? '') ?></span>
          </div>
          
          {% if (!empty($exceptionTrace)): %}
            <div class="stack-trace-container">
              <span class="debug-label">Stack Trace:</span>
              <pre class="stack-trace"><?= e($exceptionTrace) ?></pre>
            </div>
          {% endif; %}
        </div>
      </div>
    {% endif %}
    
    <!-- Action Button -->
    <div class="error-actions">
      <a href="<?= e($homeUrl) ?>" class="btn-home">
        <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
        Back to Home
      </a>
    </div>

  </div>
</section>

{% endblock %}
