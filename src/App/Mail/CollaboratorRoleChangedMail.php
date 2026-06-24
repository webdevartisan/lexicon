<?php

declare(strict_types=1);

namespace App\Mail;

class CollaboratorRoleChangedMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private string $blogName,
        private string $newRole,
        private string $changedByUsername
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject('Your role on '.$this->blogName.' changed to '.$this->newRole)
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
    }

    private function buildHtmlBody(): string
    {
        $blog = htmlspecialchars($this->blogName);
        $role = htmlspecialchars($this->newRole);
        $by   = htmlspecialchars($this->changedByUsername);

        return <<<HTML
        <!DOCTYPE html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                <h2>Role updated</h2>
                <p>Your role on <strong>{$blog}</strong> is now <strong>{$role}</strong>.</p>
                <p>Changed by {$by}.</p>
            </div>
        </body></html>
        HTML;
    }

    private function buildTextBody(): string
    {
        return "Your role on {$this->blogName} is now {$this->newRole}.\n"
             ."Changed by {$this->changedByUsername}.\n";
    }
}
