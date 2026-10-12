<?php

declare(strict_types=1);

namespace App\Services\Archive;

use RuntimeException;

/**
 * An archive was refused, or could not be built, for a reason the owner can act on.
 *
 * The message is plain English and safe to show. The translation key and its
 * parameters let the page show it in the reader's language instead; the services
 * stay free of any locale handling. Anything else that goes wrong is a plain
 * exception, logged and shown as a generic failure.
 */
final class ArchiveException extends RuntimeException
{
    /**
     * @param  array<string, string|int>  $params  Values for the translation's placeholders
     */
    public function __construct(
        string $message,
        public readonly string $key = '',
        public readonly array $params = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
