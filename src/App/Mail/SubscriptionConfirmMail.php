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
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
    }

    private function confirmUrl(): string
    {
        return rtrim((string) env('APP_URL', 'http://localhost'), '/')
            .'/subscriptions/confirm/'.rawurlencode($this->token);
    }

    private function buildHtmlBody(): string
    {
        $blog = e($this->blogName);
        $address = e($this->toEmail);
        $url = e($this->confirmUrl());

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2>Confirm your subscription</h2>
                <p>Someone asked to send new posts from <strong>{$blog}</strong> to <strong>{$address}</strong>.</p>
                <p><a href="{$url}" style="display:inline-block;padding:12px 24px;background:#1b3a6b;color:#fff;text-decoration:none;border-radius:5px;">Yes, subscribe me</a></p>
                <p style="font-size:12px;color:#666;">Or paste this into your browser: {$url}</p>
                <p><strong>If this wasn't you</strong>, ignore this message. You won't get any more emails and the address is removed within a week.</p>
            </div>
        </body>
        </html>
        HTML;
    }

    private function buildTextBody(): string
    {
        return "Confirm your subscription\n\n"
            ."Someone asked to send new posts from {$this->blogName} to {$this->toEmail}.\n\n"
            ."Yes, subscribe me:\n{$this->confirmUrl()}\n\n"
            ."If this wasn't you, ignore this message. You won't get any more emails and the address "
            .'is removed within a week.';
    }
}
