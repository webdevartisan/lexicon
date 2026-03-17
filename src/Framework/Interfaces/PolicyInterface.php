<?php

declare(strict_types=1);

namespace Framework\Interfaces;

interface PolicyInterface
{
    public function view(array $user, object $resource): bool;

    public function update(array $user, object $resource): bool;

    public function delete(array $user, object $resource): bool;
}
