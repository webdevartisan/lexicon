<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use InvalidArgumentException;

/**
 * The admin's map choice did not pass the checks. Errors are keyed by field: zoom, area or region.
 */
final class InvalidMapRequest extends InvalidArgumentException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The map request is not valid: '.implode(', ', array_keys($errors)));
    }
}
