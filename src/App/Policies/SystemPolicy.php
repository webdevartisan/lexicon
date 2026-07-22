<?php

declare(strict_types=1);

namespace App\Policies;

use Framework\Interfaces\PolicyInterface;

/**
 * Authorization policy for system-level admin tools.
 *
 * Administrators always pass. Other roles can be granted individual
 * abilities through permission slugs, so parts of the control panel can
 * open up to non-admin roles without widening the whole admin area.
 */
class SystemPolicy implements PolicyInterface
{
    /**
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function view(array $user, object $resource): bool
    {
        return $this->isAdministrator($user);
    }

    /**
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function update(array $user, object $resource): bool
    {
        return $this->isAdministrator($user);
    }

    /**
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function delete(array $user, object $resource): bool
    {
        return $this->isAdministrator($user);
    }

    /**
     * Permission slug that unlocks each control panel area for non-admins.
     */
    private const AREA_PERMISSIONS = [
        'accessDashboard' => 'access_control_panel',
        'manageUsers' => 'manage_all_users',
        'manageBlogs' => 'manage_all_blogs',
        'managePosts' => 'manage_all_posts',
        'moderateComments' => 'moderate_comments',
        'manageTaxonomy' => 'manage_taxonomy',
        'manageRoles' => 'manage_roles',
        'viewAuditLog' => 'view_audit_log',
        'viewSystem' => 'view_system_health',
        'manageCache' => 'manage_cache',
        'manageMailQueue' => 'manage_mail_queue',
        'manageSettings' => 'manage_site_settings',
    ];

    /**
     * Open the control panel dashboard.
     *
     * Anyone holding any control panel permission may land here, so a
     * moderator is not greeted with a 403 before reaching their area.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function accessDashboard(array $user): bool
    {
        if ($this->isAdministrator($user)) {
            return true;
        }

        foreach (self::AREA_PERMISSIONS as $permission) {
            if ($this->hasPermission($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Manage user accounts in the control panel.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function manageUsers(array $user): bool
    {
        return $this->allowsArea($user, 'manageUsers');
    }

    /**
     * Manage all blogs in the control panel.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function manageBlogs(array $user): bool
    {
        return $this->allowsArea($user, 'manageBlogs');
    }

    /**
     * Manage all posts in the control panel.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function managePosts(array $user): bool
    {
        return $this->allowsArea($user, 'managePosts');
    }

    /**
     * Approve, unapprove, mark spam, and delete comments.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function moderateComments(array $user): bool
    {
        return $this->allowsArea($user, 'moderateComments');
    }

    /**
     * Manage categories and tags in the control panel.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function manageTaxonomy(array $user): bool
    {
        return $this->allowsArea($user, 'manageTaxonomy');
    }

    /**
     * Create custom roles and edit role permissions.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function manageRoles(array $user): bool
    {
        return $this->allowsArea($user, 'manageRoles');
    }

    /**
     * Read the audit trail.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function viewAuditLog(array $user): bool
    {
        return $this->allowsArea($user, 'viewAuditLog');
    }

    /**
     * View system diagnostics.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function viewSystem(array $user): bool
    {
        return $this->allowsArea($user, 'viewSystem');
    }

    /**
     * Manage the response and compiled view caches (view stats, prune, clear).
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function manageCache(array $user): bool
    {
        return $this->allowsArea($user, 'manageCache');
    }

    /**
     * Inspect the outbound mail queue and retry failed sends.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function manageMailQueue(array $user): bool
    {
        return $this->allowsArea($user, 'manageMailQueue');
    }

    /**
     * Edit site-wide settings and test email delivery.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    public function manageSettings(array $user): bool
    {
        return $this->allowsArea($user, 'manageSettings');
    }

    /**
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    private function allowsArea(array $user, string $ability): bool
    {
        return $this->isAdministrator($user)
            || $this->hasPermission($user, self::AREA_PERMISSIONS[$ability]);
    }

    /**
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    private function isAdministrator(array $user): bool
    {
        return in_array('administrator', $user['roles'] ?? [], true);
    }

    /**
     * @param  array<string, mixed>  $user  Authenticated user record
     */
    private function hasPermission(array $user, string $permission): bool
    {
        return in_array($permission, $user['permissions'] ?? [], true);
    }
}
