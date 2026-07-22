<?php

declare(strict_types=1);

namespace Framework\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when a request carries a missing, stale, or forged CSRF token.
 *
 * Extends RuntimeException rather than HttpException on purpose. A rejected
 * token is a runtime condition (a session expired while a form sat open), not
 * the LogicException-family programming error that HttpException descends from
 * — and callers already written against RuntimeException keep working.
 */
final class CsrfTokenException extends RuntimeException
{
    /**
     * 419 is not in the RFC, but it is the de facto "session/token expired"
     * status and MediaController already answers AJAX CSRF failures with it.
     */
    private const STATUS = 419;

    public function __construct(
        string $message = 'Invalid CSRF token.',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return self::STATUS;
    }
}
