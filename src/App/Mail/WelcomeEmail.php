<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Welcome email sent to new users after registration.
 *
 * Carries the link that proves the address is theirs, so nothing else is mailed
 * to it until the link is followed.
 */
class WelcomeEmail extends Mailable
{
    protected string $tier = self::TIER_CRITICAL;

    protected bool $toUnverified = true;

    /**
     * @param  array<string, mixed>  $user  The new account: email, handle and first_name
     * @param  string  $token  Raw confirmation token for the link in the email
     * @param  int  $expiresInHours  How long the link lives, for the notice under the button
     */
    public function __construct(private array $user, private string $token = '', private int $expiresInHours = 24)
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
                'confirm_url' => $this->url('/verify-email/'.urlencode($this->token)),
                'expires_hours' => $this->expiresInHours,
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
