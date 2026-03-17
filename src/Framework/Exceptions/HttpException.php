<?php

declare(strict_types=1);

namespace Framework\Exceptions;

use DomainException;
use Throwable;

class HttpException extends DomainException
{
    protected int $statusCode;

    public function __construct(
        string $message = '',
        int $statusCode = 500,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->statusCode = $statusCode;
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
