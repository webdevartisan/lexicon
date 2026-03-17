{% extends "error.lex.php" %}

{% block title %}<?= e($status ?? 500) ?> - <?= e($title ?? 'Template error') ?>{% endblock %}

{% block body %}

<section class="error-container">
  <div class="error-content">
    
    <!-- Error Header -->
    <div class="error-header">
      <h1 class="error-code"><?= e($status ?? 500) ?></h1>
      <p class="error-title"><?= e($title ?? 'Template Error') ?></p>
    </div>

    {% if (empty($isDev)): %}
      <!-- User-Friendly Message -->
      <div class="error-message-card">
        <svg class="error-message-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="error-message-text">Something went wrong on our end. Please try again later.</p>
      </div>
    {% else %}
      <!-- Debug Information (Dev Only) -->
      <div class="debug-card">
        <h2 class="debug-header">
          <svg class="debug-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
          </svg>
          Template Debug Information
        </h2>
        
        <div class="debug-info">
          <div class="debug-row">
            <span class="debug-label">Message:</span>
            <span class="debug-value"><?= e($templateMessage ?? '') ?></span>
          </div>
          
          <div class="debug-row">
            <span class="debug-label">File:</span>
            <span class="debug-value mono small"><?= e(($templateFile ?? '').':'.($templateLine ?? '')) ?></span>
          </div>
          
          {% if (!empty($templateSnippet)): %}
            <div class="stack-trace-container">
              <span class="debug-label">Affected Area:</span>
              <pre class="stack-trace"><?= $templateSnippet ?></pre>
            </div>
          {% endif; %}
        </div>
      </div>
    {% endif %}
    
    <!-- Action Button -->
    <div class="error-actions">
      <a href="<?= e($homeUrl ?? '/') ?>" class="btn-home">
        <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
        Back to Home
      </a>
    </div>

  </div>
</section>

{% endblock %}
