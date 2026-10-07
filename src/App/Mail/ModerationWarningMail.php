<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Templates\HtmlFragment;

/**
 * Tells an author a moderator upheld reports against something they wrote,
 * with the moderator's own words, so a warning is never silent.
 *
 * A post and a comment get different emails, ModerationWarningPostMail and
 * ModerationWarningCommentMail, so each can be worded on its own; for() picks one.
 */
abstract class ModerationWarningMail extends Mailable
{
    public function __construct(
        private string $toEmail,
        private string $handle,
        private string $subjectLabel,
        private string $category,
        private string $message,
    ) {
        parent::__construct();
    }

    /**
     * The warning for a moderation case's subject type ('post' or 'comment').
     *
     * @return class-string<self>
     */
    public static function for(string $subjectType): string
    {
        return $subjectType === 'post' ? ModerationWarningPostMail::class : ModerationWarningCommentMail::class;
    }

    public function build(): void
    {
        $this->to($this->toEmail)
            ->fromTemplate([
                'handle' => $this->handle,
                'subject_label' => $this->subjectLabel,
                'category' => $this->category,
                'message' => HtmlFragment::fromText($this->message),
            ]);
    }
}
