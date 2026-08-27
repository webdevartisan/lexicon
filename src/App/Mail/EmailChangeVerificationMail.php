<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Sent to the NEW address when someone asks to move an account onto it.
 *
 * The address does not change until this link is followed, so the token is
 * what proves the requester actually controls the inbox. The previous address
 * is told separately by EmailChangedMail once the move completes.
 */
class EmailChangeVerificationMail extends Mailable
{
    /** The recipient is waiting on a link that expires, so it cannot queue behind a fan-out. */
    protected string $tier = self::TIER_CRITICAL;

    public function __construct(
        private string $newEmail,
        private string $token,
        private int $expiresInMinutes = 60
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->newEmail)
            ->subject('Confirm your new '.$this->appName().' email address')
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
    }

    private function appName(): string
    {
        return (string) env('APP_NAME', 'Lexicon');
    }

    /**
     * The confirmation link, carrying the raw token that only this message holds.
     */
    private function confirmUrl(): string
    {
        return rtrim((string) env('APP_URL', 'http://localhost'), '/')
            .'/account/email/confirm/'.urlencode($this->token);
    }

    private function buildHtmlBody(): string
    {
        $appName = htmlspecialchars($this->appName());
        $address = htmlspecialchars($this->newEmail);
        $url = htmlspecialchars($this->confirmUrl());
        $minutes = $this->expiresInMinutes;

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2>Confirm your new email address</h2>
                <p>Someone asked to change the email address on a {$appName} account to <strong>{$address}</strong>.</p>
                <p>Confirm it to finish the change:</p>
                <p><a href="{$url}" style="display:inline-block;padding:12px 24px;background:#1b3a6b;color:#fff;text-decoration:none;border-radius:5px;">Confirm this address</a></p>
                <p style="font-size:12px;color:#666;">Or paste this into your browser: {$url}</p>
                <p>The link stops working in {$minutes} minutes, and the account keeps its current address until you follow it.</p>
                <p><strong>If you were not expecting this</strong>, ignore this message. Nothing changes and this address is not added to the account.</p>
            </div>
        </body>
        </html>
        HTML;
    }

    private function buildTextBody(): string
    {
        $appName = $this->appName();
        $url = $this->confirmUrl();

        return "Confirm your new email address\n\n"
            ."Someone asked to change the email address on a {$appName} account to {$this->newEmail}.\n\n"
            ."Confirm it to finish the change:\n{$url}\n\n"
            ."The link stops working in {$this->expiresInMinutes} minutes, and the account keeps its "
            ."current address until you follow it.\n\n"
            .'If you were not expecting this, ignore this message. Nothing changes and this address '
            .'is not added to the account.';
    }
}
