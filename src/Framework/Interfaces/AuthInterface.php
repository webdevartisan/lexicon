<?php

declare(strict_types=1);

namespace Framework\Interfaces;

/**
 * Authentication contract for framework components.
 *
 * We define only the minimal methods that framework-level code needs
 * (middleware, policies, etc.) without coupling to App\Auth implementation.
 *
 * This allows the framework to check authentication state while remaining
 * agnostic about the underlying storage mechanism (session, JWT, OAuth).
 */
interface AuthInterface
{
    /**
     * Check if a user is currently authenticated.
     *
     * We use this in middleware to determine whether to serve cached content
     * or bypass cache for personalized views.
     *
     * @return bool True if user is logged in, false otherwise
     */
    public function check(): bool;

    /**
     * Get the current authenticated user record.
     *
     * We keep the return type flexible (array|null) to support different
     * user data structures across applications.
     *
     * @return array|null User data array or null if guest
     */
    public function user(): ?array;

    /**
     * Check if the authenticated user has a specific role.
     *
     * We use this in authorization middleware (e.g., 'role:admin') to
     * restrict access to certain routes based on user roles.
     *
     * @param  string  $role  Role slug (e.g., 'administrator', 'author')
     * @return bool True if user has the role, false otherwise
     */
    public function hasRole(string $role): bool;

    /**
     * Check if the authenticated user has a specific permission.
     *
     * We use this for fine-grained access control in policies and
     * authorization checks.
     *
     * @param  string  $permission  Permission slug (e.g., 'edit_post')
     * @return bool True if user has the permission, false otherwise
     */
    public function hasPermission(string $permission): bool;
}
