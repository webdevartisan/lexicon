<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Sent to blog subscribers when a new post goes live.
 */
class NewPostMail extends Mailable
{
    protected string $tier = self::TIER_BULK;

    public function __construct(
        private string $toEmail,
        private string $blogName,
        private string $postTitle,
        private string $blogSlug,
        private string $postSlug,
        private string $unsubscribeToken
    ) {
        parent::__construct();
    }

    public function build(): void
    {
        $unsubscribe = $this->url('/subscriptions/unsubscribe/'.rawurlencode($this->unsubscribeToken));

        // RFC 8058 one-click: the mail provider POSTs to the same link, so the
        // unsubscribe button in Gmail or Yahoo works without opening the page.
        $this->to($this->toEmail)
            ->header('List-Unsubscribe', '<'.$unsubscribe.'>')
            ->header('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click')
            ->fromTemplate([
                'blog_name' => $this->blogName,
                'post_title' => $this->postTitle,
                'post_url' => $this->url('/blog/'.rawurlencode($this->blogSlug).'/'.rawurlencode($this->postSlug)),
                'unsubscribe_url' => $unsubscribe,
                // A subscriber may have no account, so their email settings are this one link.
                'preferences_url' => $unsubscribe,
            ]);
    }
}
