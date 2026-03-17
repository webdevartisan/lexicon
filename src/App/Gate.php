<?php

declare(strict_types=1);

namespace App;

use App\Policies\PolicyResolver;

class Gate
{
    public static function allows(string $action, object|string $resource, array $user): bool
    {
        $policy = PolicyResolver::for($resource);

        if (!method_exists($policy, $action)) {
            throw new \Exception("Policy does not support action: {$action}");
        }

        // For class names (create action), only pass user
        if (is_string($resource)) {
            return $policy->{$action}($user);
        }

        // For instances (view, update, delete), pass both
        return $policy->{$action}($user, $resource);
    }

    public static function denies(string $action, object|string $resource, array $user): bool
    {
        return !self::allows($action, $resource, $user);
    }

    public static function authorize(string $action, object|string $resource, array $user): void
    {
        if (self::allows($action, $resource, $user) === false) {
            http_response_code(403);
            throw new \Framework\Exceptions\UnauthorizedException('Unauthorized');
        }
    }
}
