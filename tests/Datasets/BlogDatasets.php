<?php

declare(strict_types=1);

/**
 * Test datasets for blog-related tests.
 *
 * Provides reusable data for blog collaborator roles, statuses,
 * and edge cases for blog operations.
 */

/**
 * Valid collaborative roles for blog users.
 *
 * These roles are independent from global user roles.
 */
dataset('blog_collaborator_roles', [
    'editor',
    'author',
    'contributor',
    'reviewer',
    'viewer',
]);

/**
 * Invalid collaborative roles that should be rejected.
 *
 * Tests role validation in addUserToBlog method.
 */
dataset('invalid_blog_roles', [
    'admin',
    'moderator',
    'super_admin',
    'invalid_role',
    '',
    'EDITOR', // case-sensitive check
]);

/**
 * Blog status values.
 */
dataset('blog_statuses', [
    ['draft'],
    ['published'],
]);

/**
 * Pagination limits for featured creators.
 */
dataset('featured_creator_limits', [1, 4, 10, 50]);
