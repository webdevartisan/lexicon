<!DOCTYPE html>
<html lang="{{ currentLang }}" {{ isRtl|raw }}>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="googlebot" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="/assets/icon/favicon.svg" />
    <link rel="shortcut icon" href="/assets/icon/favicon.ico" />
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>{% yield title %}</title>

    <style>
      /* CSS Reset */
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
      }

      /* Error Page Styles */
      .error-container {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #a78f6c 0%, #764ba2 100%);
        padding: 2rem 1rem;
      }

      .error-content {
        max-width: 48rem;
        width: 100%;
      }

      /* Error Header */
      .error-header {
        text-align: center;
        margin-bottom: 2rem;
        color: #fff;
      }

      .error-code {
        font-size: 6rem;
        font-weight: 700;
        margin: 0 0 0.5rem 0;
        line-height: 1;
        text-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
      }

      .error-title {
        font-size: 1.5rem;
        font-weight: 600;
        margin: 0;
        opacity: 0.95;
      }

      /* Debug Card */
      .debug-card {
        background: #fff;
        border-radius: 0.75rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        padding: 1.5rem;
        margin-bottom: 2rem;
      }

      .debug-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #dc2626;
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0 0 1.5rem 0;
      }

      .debug-icon {
        width: 1.5rem;
        height: 1.5rem;
        flex-shrink: 0;
      }

      /* Debug Information */
      .debug-info {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        font-size: 0.875rem;
      }

      .debug-row {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
      }

      .debug-label {
        font-weight: 600;
        color: #374151;
        min-width: 6rem;
      }

      .debug-value {
        color: #111827;
      }

      .debug-value.mono {
        font-family: 'Courier New', Courier, monospace;
      }

      .debug-value.small {
        font-size: 0.75rem;
        word-break: break-all;
      }

      /* Stack Trace */
      .stack-trace-container {
        margin-top: 0.5rem;
      }

      .stack-trace {
        background: #1f2937;
        color: #e5e7eb;
        padding: 1rem;
        border-radius: 0.5rem;
        overflow-x: auto;
        font-size: 0.75rem;
        line-height: 1.6;
        margin: 0.5rem 0 0 0;
        font-family: 'Courier New', Courier, monospace;
      }

      /* User-Friendly Error Message */
      .error-message-card {
        background: #fff;
        border-radius: 0.75rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        padding: 2rem;
        margin-bottom: 2rem;
        text-align: center;
      }

      .error-message-icon {
        width: 4rem;
        height: 4rem;
        color: #f59e0b;
        margin: 0 auto 1.5rem;
        stroke-width: 1.5;
      }

      .error-message-text {
        color: #374151;
        font-size: 1.125rem;
        line-height: 1.75;
        margin: 0;
        max-width: 36rem;
        margin: 0 auto;
      }

      /* Action Button */
      .error-actions {
        text-align: center;
      }

      .btn-home {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: #fff;
        color: #667eea;
        font-weight: 600;
        padding: 0.875rem 1.75rem;
        border-radius: 0.5rem;
        text-decoration: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transition: all 0.2s ease;
      }

      .btn-home:hover {
        background: #f9fafb;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
      }

      .btn-icon {
        width: 1.25rem;
        height: 1.25rem;
      }

      /* Responsive Design */
      @media (min-width: 640px) {
        .debug-row {
          flex-direction: row;
          gap: 0.5rem;
        }
        
        .error-code {
          font-size: 8rem;
        }
      }

      @media (max-width: 639px) {
        .error-container {
          padding: 1rem 0.5rem;
        }

        .error-code {
          font-size: 4rem;
        }
        
        .error-title {
          font-size: 1.25rem;
        }

        .debug-card,
        .error-message-card {
          padding: 1.25rem;
        }
      }
    </style>
  </head>

  <body>
    {% yield body %}
  </body>
</html>
