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
            ->subject($this->t('subjects.ModerationWarningMail', ['your_item' => $this->yourItem()]))
            ->fromTemplate([
                'handle' => $this->handle,
                // The whole phrase, since the possessive and article depend on the noun in many languages.
                'your_item' => $this->yourItem(),
                'subject_kind' => $this->t($this->subjectKind === 'post' ? 'phrases.kind_post' : 'phrases.kind_comment'),
                'subject_label' => $this->subjectLabel,
                'category' => $this->category,
                'message' => HtmlFragment::fromText($this->message),
            ]);
    }

    private function yourItem(): string
    {
        return $this->t($this->subjectKind === 'post' ? 'phrases.your_post' : 'phrases.your_comment');
    }
}
