<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/**
 * Class that depends on SimpleClass.
 *
 * We use this to verify the container can resolve and inject dependencies automatically.
 */
class ClassWithDependency
{
    public function __construct(public SimpleClass $simple) {}
}
