<?php

declare(strict_types=1);

namespace Framework\Exceptions;

use Throwable;

class UnauthorizedException extends HttpException
{
    public function __construct(
        string $message = 'You are not authorized to perform this action.',
        int $statusCode = 403,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $code, $previous);
    }
}
