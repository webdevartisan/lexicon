<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use RuntimeException;

/**
 * Place search could not answer: "busy" when the site already asked within the last
 * second, "failed" when the search service did not answer usefully.
 */
final class PlaceSearchUnavailable extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Place search {$reason}");
    }
}
