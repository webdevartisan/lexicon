<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use RuntimeException;

/**
 * An admin action on the map page was refused. The message is shown as is; the status is the HTTP answer.
 */
final class MapActionRefused extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
