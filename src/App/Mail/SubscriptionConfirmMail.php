<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Sent when someone subscribes an address to a blog.
 *
 * Anyone can type any address into the form, so no post notifications go out
 * until the owner of the inbox confirms through this link.
 */
class SubscriptionConfirmMail extends Mailable
{
    /** The subscriber is waiting for it, so it cannot queue behind a fan-out. */
    protected string $tier = self::TIER_CRITICAL;

    public function __construct(
        private string $toEmail,
        private string $blogName,
        private string $token
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject('Confirm your subscription to '.$this->blogName)
            ->fromTemplate([
                'blog_name' => $this->blogName,
                'email' => $this->toEmail,
                'confirm_url' => $this->confirmUrl(),
            ]);
    }

    private function confirmUrl(): string
    {
        return rtrim((string) env('APP_URL', 'http://localhost'), '/')
            .'/subscriptions/confirm/'.rawurlencode($this->token);
    }
}
