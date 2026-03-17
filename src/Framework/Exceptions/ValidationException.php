<?php

declare(strict_types=1);

namespace Framework\Exceptions;

use Exception;
use Framework\Validation\DatabaseValidator;

/**
 * Exception thrown when validation fails.
 */
class ValidationException extends Exception
{
    public function __construct(
        protected DatabaseValidator $validator,
        string $message = 'The given data was invalid.'
    ) {
        parent::__construct($message);
    }

    public function getValidator(): DatabaseValidator
    {
        return $this->validator;
    }

    public function errors(): array
    {
        return $this->validator->errors();
    }
}
