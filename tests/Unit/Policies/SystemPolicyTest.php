<?php

declare(strict_types=1);

use App\Policies\SystemPolicy;

/**
 * SystemPolicy Unit Test Suite
 *
 * Guards control panel authorization: administrators pass every area,
 * other roles only pass areas whose permission slug they hold, and the
 * dashboard opens to anyone holding at least one area permission.
 */
beforeEach(function () {
    $this->policy = new SystemPolicy();

    $this->admin = ['id' => 1, 'roles' => ['administrator'], 'permissions' => []];
    $this->moderator = ['id' => 2, 'roles' => ['comment_moderator'], 'permissions' => ['moderate_comments']];
    $this->regular = ['id' => 3, 'roles' => ['author'], 'permissions' => ['create_posts']];
});

test('administrator passes every control panel ability', function (string $ability) {
    expect($this->policy->{$ability}($this->admin))->toBeTrue();
})->with([
    'accessDashboard', 'manageUsers', 'manageBlogs', 'managePosts',
    'moderateComments', 'manageTaxonomy', 'manageRoles', 'viewAuditLog',
    'viewSystem', 'manageCache', 'manageSettings',
]);

test('permission holder passes only the matching area', function () {
    expect($this->policy->moderateComments($this->moderator))->toBeTrue()
        ->and($this->policy->manageUsers($this->moderator))->toBeFalse()
        ->and($this->policy->manageSettings($this->moderator))->toBeFalse()
        ->and($this->policy->manageCache($this->moderator))->toBeFalse();
});

test('any area permission opens the dashboard', function () {
    expect($this->policy->accessDashboard($this->moderator))->toBeTrue();
});

test('user without area permissions is denied everywhere', function (string $ability) {
    expect($this->policy->{$ability}($this->regular))->toBeFalse();
})->with([
    'accessDashboard', 'manageUsers', 'manageBlogs', 'managePosts',
    'moderateComments', 'manageTaxonomy', 'manageRoles', 'viewAuditLog',
    'viewSystem', 'manageCache', 'manageSettings',
]);

test('empty user array is denied everywhere', function () {
    expect($this->policy->accessDashboard([]))->toBeFalse()
        ->and($this->policy->manageCache([]))->toBeFalse();
});
