<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/**
 * CircularA depends on CircularB.
 * 
 * We use this pair to test circular dependency detection.
 */
class CircularA
{
    public function __construct(public CircularB $b) {}
}