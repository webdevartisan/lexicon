<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Welcome email sent to new users after registration.
 *
 * We send this email immediately after a user successfully registers
 * to confirm their account creation and provide getting-started guidance.
 */
class WelcomeEmail extends Mailable
{
    /**
     * @param  array<string, mixed>  $user  User row (first_name, handle, email)
     */
    public function __construct(private array $user)
    {
        parent::__construct();
    }

    public function build(): void
    {
        // The handle is a better name than none, and a greeting without a name
        // is worded as one rather than as "Hello there".
        $name = (string) ($this->user['first_name'] ?? '') ?: (string) ($this->user['handle'] ?? '');

        $this->to($this->user['email'], $name)
            ->subject($this->t('subjects.WelcomeEmail', ['app_name' => (string) env('APP_NAME', 'Lexicon')]))
            ->fromTemplate([
                'greeting' => $name !== '' ? $this->t('phrases.greeting', ['name' => $name]) : $this->t('phrases.greeting_anonymous'),
                'first_name' => $name !== '' ? $name : $this->t('phrases.there'),
                'handle' => (string) $this->user['handle'],
                // Explore, not a personal page: a brand new account has nothing on
                // its own lists yet, so the useful first destination is the catalog.
                'explore_url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/discover',
            ]);
    }
}
