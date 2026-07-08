<?php

declare(strict_types=1);

namespace Framework\Interfaces;

interface PolicyInterface
{
    /**
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function view(array $user, object $resource): bool;

    /**
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function update(array $user, object $resource): bool;

    /**
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function delete(array $user, object $resource): bool;
}
