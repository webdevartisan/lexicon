<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Tells an author a moderator upheld reports against something they wrote,
 * with the moderator's own words, so a warning is never silent.
 */
class ModerationWarningMail extends Mailable
{
    /**
     * @param  string  $subjectKind  'post' or 'comment'
     * @param  string  $subjectLabel  Post title, or the start of the comment
     * @param  string  $category  Label of the reason the reports gave
     * @param  string  $message  What the moderator wrote to the author
     */
    public function __construct(
        private string $toEmail,
        private string $handle,
        private string $subjectKind,
        private string $subjectLabel,
        private string $category,
        private string $message,
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject('A warning about your '.$this->subjectKind)
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
    }

    private function buildHtmlBody(): string
    {
        $handle = e($this->handle);
        $kind = e($this->subjectKind);
        $label = e($this->subjectLabel);
        $category = e($this->category);
        $message = nl2br(e($this->message));

        return <<<HTML
        <!DOCTYPE html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                <h2>A warning about your {$kind}</h2>
                <p>Hi @{$handle},</p>
                <p>Readers reported your {$kind} <strong>{$label}</strong> for <strong>{$category}</strong>, and a moderator agreed with them.</p>
                <blockquote style="margin:16px 0;padding:12px 16px;border-left:3px solid #e5a000;background:#fff8e6;">{$message}</blockquote>
                <p>Further reports that are upheld can lead to your account being suspended.</p>
            </div>
        </body></html>
        HTML;
    }

    private function buildTextBody(): string
    {
        return "Hi @{$this->handle},\n\n"
            ."Readers reported your {$this->subjectKind} \"{$this->subjectLabel}\" for {$this->category}, and a moderator agreed with them.\n\n"
            ."{$this->message}\n\n"
            ."Further reports that are upheld can lead to your account being suspended.\n";
    }
}
