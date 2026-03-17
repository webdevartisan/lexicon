<?php

declare(strict_types=1);

namespace App\Policies;

use App\Resources\BlogResource;
use App\Resources\PostResource;
use App\Resources\UserResource;
use Framework\Interfaces\PolicyInterface;

class PolicyResolver
{
    protected static array $map = [
        BlogResource::class => BlogPolicy::class,
        PostResource::class => PostPolicy::class,
        UserResource::class => UserPolicy::class,
    ];

    public static function for(object|string $resource): PolicyInterface
    {
        // If it's already a class name, use it directly
        $class = is_string($resource) ? $resource : get_class($resource);

        if (!isset(self::$map[$class])) {
            throw new \Exception("No policy defined for resource: {$class}");
        }

        $policyClass = self::$map[$class];

        return new $policyClass();
    }
}
