<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Warns someone that several of their reports were found unfounded, and that
 * their reporting can be paused if it goes on. DSA Art. 23(1) requires this
 * warning before any pause, so it always carries the moderator's own words.
 */
class ReporterWarningMail extends Mailable
{
    /**
     * @param  int  $unfounded  How many of their reports were ruled unfounded
     * @param  string  $message  What the moderator wrote to them
     */
    public function __construct(
        private string $toEmail,
        private string $handle,
        private int $unfounded,
        private string $message,
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->subject('About the reports you have been sending')
            ->html($this->buildHtmlBody())
            ->textAlternative($this->buildTextBody());
    }

    private function buildHtmlBody(): string
    {
        $handle = e($this->handle);
        $count = $this->countText();
        $message = nl2br(e($this->message));

        return <<<HTML
        <!DOCTYPE html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                <h2>About the reports you have been sending</h2>
                <p>Hi @{$handle},</p>
                <p>Moderators found {$count} to be unfounded.</p>
                <blockquote style="margin:16px 0;padding:12px 16px;border-left:3px solid #e5a000;background:#fff8e6;">{$message}</blockquote>
                <p>Reports help keep the site safe, so please keep sending them when something is wrong. If more reports turn out to be unfounded, your reporting may be paused for a while.</p>
            </div>
        </body></html>
        HTML;
    }

    private function buildTextBody(): string
    {
        return "Hi @{$this->handle},\n\n"
            .'Moderators found '.$this->countText().' to be unfounded.'."\n\n"
            ."{$this->message}\n\n"
            ."Reports help keep the site safe, so please keep sending them when something is wrong. If more reports turn out to be unfounded, your reporting may be paused for a while.\n";
    }

    private function countText(): string
    {
        return $this->unfounded === 1 ? '1 of your reports' : $this->unfounded.' of your reports';
    }
}
