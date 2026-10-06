<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Templates\HtmlFragment;

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
            ->fromTemplate([
                'handle' => $this->handle,
                'subject_kind' => $this->subjectKind,
                'subject_label' => $this->subjectLabel,
                'category' => $this->category,
                'message' => HtmlFragment::fromText($this->message),
            ]);
    }
}
