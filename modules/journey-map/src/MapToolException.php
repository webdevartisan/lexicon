<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use RuntimeException;

/**
 * The map tool could not be installed. The reason is one word the admin page turns into a sentence:
 * system, programs, release, download, digest or archive.
 */
final class MapToolException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('The map tool could not be installed: '.$reason);
    }
}
