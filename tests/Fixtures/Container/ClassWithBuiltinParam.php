<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/**
 * Class with built-in type parameter.
 * 
 * We use this to verify the container throws an exception for scalar types
 * (string, int, etc.) since it cannot auto-resolve primitive values.
 */
class ClassWithBuiltinParam
{
    public function __construct(public string $name) {}
}