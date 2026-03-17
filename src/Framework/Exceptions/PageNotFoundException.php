<?php

declare(strict_types=1);

namespace Framework\Exceptions;

use Throwable;

class PageNotFoundException extends HttpException
{
    public function __construct(
        string $message = 'The requested page could not be found.',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 404, $code, $previous);
    }
}
