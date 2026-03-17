<?php

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
    | Mail driver
    |--------------------------------------------------------------------------
    |
    | Selects the mail transport driver. Supported values are 'smtp',
    | 'sendmail', and 'mail'. SMTP is recommended for production for better
    | reliability and control over delivery settings.
    |
    */
    'driver' => $_ENV['MAIL_DRIVER'] ?? 'smtp',

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
        'host' => $_ENV['MAIL_HOST'] ?? 'smtp.mailtrap.io',
        'port' => (int) ($_ENV['MAIL_PORT'] ?? 2525),
        'username' => $_ENV['MAIL_USERNAME'] ?? '',
        'password' => $_ENV['MAIL_PASSWORD'] ?? '',
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls', // 'tls' or 'ssl'
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
        'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com',
        'name' => $_ENV['MAIL_FROM_NAME'] ?? 'Blog Platform',
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
    'debug' => filter_var($_ENV['MAIL_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
];
