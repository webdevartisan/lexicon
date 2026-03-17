<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/**
 * Class with default parameter value.
 *
 * We use this to verify the container respects default values when no explicit
 * binding exists for optional parameters.
 */
class ClassWithDefaultParam
{
    public function __construct(public string $name = 'default') {}
}
