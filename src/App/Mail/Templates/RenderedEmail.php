<?php

declare(strict_types=1);

namespace App\Mail\Templates;

/**
 * The finished parts of one email, ready to hand to a Mailable.
 */
final readonly class RenderedEmail
{
    public function __construct(
        public string $subject,
        public string $html,
        public string $text,
    ) {}
}
