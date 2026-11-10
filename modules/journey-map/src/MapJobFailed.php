<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use RuntimeException;

/**
 * A map job step could not finish. The reason is one word the admin page turns into a sentence:
 * request, no-tool, builds, tool, size, archive, assets, cancelled or error.
 */
final class MapJobFailed extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('The map job failed: '.$reason);
    }
}
