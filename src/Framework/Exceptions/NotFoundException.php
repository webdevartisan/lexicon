<?php

declare(strict_types=1);

namespace Framework\Exceptions;

use Throwable;

class NotFoundException extends HttpException
{
    public function __construct(
        string $message = 'The requested resource could not be found.',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 404, $code, $previous);
    }
}
