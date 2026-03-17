<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/**
 * CircularB depends on CircularA.
 *
 * We use this pair to test circular dependency detection.
 */
class CircularB
{
    public function __construct(public CircularA $a) {}
}
