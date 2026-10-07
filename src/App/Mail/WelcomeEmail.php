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
     * @param  array<string, mixed>  $user  The new account: email, handle and first_name
     */
    public function __construct(private array $user)
    {
        parent::__construct();
    }

    public function build(): void
    {
        $name = self::nameOf($this->user);

        $this->to((string) $this->user['email'], $name)
            ->fromTemplate([
                'name' => $name,
                'handle' => (string) $this->user['handle'],
                // Explore, not a personal page: a brand new account has nothing on
                // its own lists yet, so the useful first destination is the catalog.
                'explore_url' => $this->url('/discover'),
            ]);
    }

    /**
     * What to call someone: their first name, else their handle, which every account has.
     *
     * @param  array<string, mixed>  $user
     */
    public static function nameOf(array $user): string
    {
        return (string) ($user['first_name'] ?? '') ?: (string) ($user['handle'] ?? '');
    }
}
