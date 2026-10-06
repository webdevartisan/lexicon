<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Templates\HtmlFragment;

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
            ->fromTemplate([
                'handle' => $this->handle,
                'unfounded' => $this->unfounded,
                'unfounded_reports' => $this->countText(),
                'message' => HtmlFragment::fromText($this->message),
            ]);
    }

    private function countText(): string
    {
        return $this->unfounded === 1 ? '1 of your reports' : $this->unfounded.' of your reports';
    }
}
