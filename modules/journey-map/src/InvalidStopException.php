<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use InvalidArgumentException;

/**
 * A place that cannot be saved, with what is wrong with each field.
 */
final class InvalidStopException extends InvalidArgumentException
{
    /**
     * @param array<string, string> $errors Field name => translation key of the problem
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The place is not valid: '.implode(', ', array_keys($errors)));
    }
}
