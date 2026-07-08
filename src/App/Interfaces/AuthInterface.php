<?php

declare(strict_types=1);

namespace App\Interfaces;

interface AuthInterface
{
    public function login(string $email, string $password): bool;

    public function logout(): void;

    /**
     * @return array<string, mixed>|null Current user record or null if guest
     */
    public function user(): ?array;

    public function check(): bool;

    public function hasRole(string $role): bool;

    public function hasPermission(string $permission): bool;
}
