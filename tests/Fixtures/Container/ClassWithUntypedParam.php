<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/**
 * Class with untyped constructor parameter.
 * 
 * We use this to verify the container throws an exception when it cannot determine
 * what to inject (no type hint means no way to resolve the dependency).
 */
class ClassWithUntypedParam
{
    public function __construct($param) {}
}