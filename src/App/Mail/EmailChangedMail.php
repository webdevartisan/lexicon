<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Sent to the PREVIOUS email address when an account's email is changed.
 *
 * OWASP's guidance on changing a registered address is to notify the old
 * address as well as the new one, so an account takeover is visible to the
 * person losing the account. The new address is not asked to confirm anything
 * here; that verified-token flow is deferred to follow-up.
 *
 * Queued like every other message, so a mail outage cannot fail the save.
 */
class EmailChangedMail extends Mailable
{
    public function __construct(
        private string $oldEmail,
        private string $newEmail,
        private string $changedAt
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->oldEmail)
            ->subject('Your '.$this->appName().' email address was changed')
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
    }

    private function appName(): string
    {
        return (string) (env('APP_NAME', 'Lexicon'));
    }

    private function buildHtmlBody(): string
    {
        $appName = htmlspecialchars($this->appName());
        $newEmail = htmlspecialchars($this->newEmail);
        $when = htmlspecialchars($this->changedAt);

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2>Your email address was changed</h2>
                <p>The email address on your {$appName} account was changed to <strong>{$newEmail}</strong> on {$when} (UTC).</p>
                <p>If you made this change, no action is needed.</p>
                <p><strong>If this was not you</strong>, your account may have been accessed by someone else. Reset your password immediately and contact support.</p>
                <p style="font-size:12px;color:#666;">You are receiving this at your previous address because it was the one on the account when the change was made.</p>
            </div>
        </body>
        </html>
        HTML;
    }

    private function buildTextBody(): string
    {
        $appName = $this->appName();

        return "Your email address was changed\n\n"
            ."The email address on your {$appName} account was changed to {$this->newEmail} "
            ."on {$this->changedAt} (UTC).\n\n"
            ."If you made this change, no action is needed.\n\n"
            ."If this was not you, your account may have been accessed by someone else. "
            ."Reset your password immediately and contact support.\n\n"
            ."You are receiving this at your previous address because it was the one on the account when the change was made.";
    }
}
