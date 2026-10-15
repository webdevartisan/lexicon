<?php

declare(strict_types=1);

/**
 * Mail configuration.
 *
 * Defines email sending configuration for the application.
 * All credentials and sensitive values should be stored in .env
 * and never committed to version control.
 *
 * Supported drivers: 'smtp', 'sendmail', 'mail'.
 * SMTP is recommended for production due to its reliability and flexibility.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Mail enabled
    |--------------------------------------------------------------------------
    |
    | Master switch for outgoing mail. When false, MailService suppresses every
    | send instead of opening an SMTP connection. This lives here, and is
    | enforced inside MailService, because individual call sites used to check
    | MAIL_ENABLED themselves and most of them forgot to.
    |
    */
    'enabled' => filter_var(env('MAIL_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Mail driver
    |--------------------------------------------------------------------------
    |
    | Selects the mail transport driver. Supported values are 'smtp',
    | 'sendmail', and 'mail'. SMTP is recommended for production for better
    | reliability and control over delivery settings.
    |
    */
    'driver' => (string) env('MAIL_DRIVER', 'smtp'),

    /*
    |--------------------------------------------------------------------------
    | SMTP configuration
    |--------------------------------------------------------------------------
    |
    | SMTP connection settings. These should be defined in the .env file
    | to keep credentials secure and avoid hard‑coding them in the config.
    |
    */
    'smtp' => [
        'host' => (string) env('MAIL_HOST', 'smtp.mailtrap.io'),
        'port' => (int) env('MAIL_PORT', 2525),
        'username' => (string) env('MAIL_USERNAME', ''),
        'password' => (string) env('MAIL_PASSWORD', ''),
        'encryption' => (string) env('MAIL_ENCRYPTION', 'tls'), // 'tls' or 'ssl'
    ],

    /*
    |--------------------------------------------------------------------------
    | From address
    |--------------------------------------------------------------------------
    |
    | Default sender address and name for outgoing emails.
    * Individual Mailable classes can override these values.
    |
    */
    'from' => [
        'address' => (string) env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
        'name' => (string) env('MAIL_FROM_NAME', 'Blog Platform'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Pacing for the mail:queue-work cron worker. Providers reject bursts, so
    | the worker sends a capped batch per run and waits between messages. The
    | defaults suit a rate-limited sandbox; raise them once a real sending
    | domain is in place.
    |
    | batch_size          how many emails one worker run may send
    | delay_ms            pause between individual sends within a run
    | max_attempts        deliveries tried before a row is marked failed
    | backoff_seconds     base retry delay, multiplied by the attempt count
    | stuck_after_minutes how long a claim may sit before it is reclaimed
    |
    */
    'queue' => [
        'batch_size' => (int) env('MAIL_QUEUE_BATCH_SIZE', 10),
        'delay_ms' => (int) env('MAIL_QUEUE_DELAY_MS', 500),
        'max_attempts' => (int) env('MAIL_QUEUE_MAX_ATTEMPTS', 3),
        'backoff_seconds' => (int) env('MAIL_QUEUE_BACKOFF_SECONDS', 60),
        'stuck_after_minutes' => (int) env('MAIL_QUEUE_STUCK_AFTER_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug mode
    |--------------------------------------------------------------------------
    |
    | Enables SMTP debug output for troubleshooting email delivery.
    | Should be disabled in production to avoid exposing sensitive details
    | in logs or error output.
    |
    */
    'debug' => filter_var(env('MAIL_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
];
