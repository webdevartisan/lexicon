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
            ->fromTemplate([
                'handle' => $this->handle,
                'unfounded' => $this->number($this->unfounded),
                'message' => HtmlFragment::fromText($this->message),
            ]);
    }
}
