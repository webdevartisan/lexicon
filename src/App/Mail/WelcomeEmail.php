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
        $firstName = (string) ($this->user['first_name'] ?? '') ?: 'there';

        $this->to($this->user['email'], $firstName)
            ->subject('Welcome to '.(env('APP_NAME', 'Our Blog Platform')))
            ->fromTemplate([
                'first_name' => $firstName,
                'handle' => (string) $this->user['handle'],
                // Explore, not a personal page: a brand new account has nothing on
                // its own lists yet, so the useful first destination is the catalog.
                'explore_url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/discover',
            ]);
    }
}
