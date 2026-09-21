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
    $this->moderator = ['id' => 2, 'roles' => ['moderator'], 'permissions' => ['manage_taxonomy']];
    $this->regular = ['id' => 3, 'roles' => ['author'], 'permissions' => ['create_posts']];
});

test('administrator passes every control panel ability', function (string $ability) {
    expect($this->policy->{$ability}($this->admin))->toBeTrue();
})->with([
    'accessDashboard', 'manageUsers', 'manageBlogs', 'managePosts',
    'handleReports', 'manageTaxonomy', 'manageRoles', 'viewAuditLog',
    'viewSystem', 'manageCache', 'manageSettings',
]);

test('permission holder passes only the matching area', function () {
    expect($this->policy->manageTaxonomy($this->moderator))->toBeTrue()
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
    'handleReports', 'manageTaxonomy', 'manageRoles', 'viewAuditLog',
    'viewSystem', 'manageCache', 'manageSettings',
]);

test('empty user array is denied everywhere', function () {
    expect($this->policy->accessDashboard([]))->toBeFalse()
        ->and($this->policy->manageCache([]))->toBeFalse();
});

test('only administrators may clear logs, even someone allowed to read them', function () {
    $healthViewer = ['id' => 4, 'roles' => ['ops'], 'permissions' => ['view_system_health']];

    expect($this->policy->clearLogs($this->admin))->toBeTrue()
        ->and($this->policy->viewSystem($healthViewer))->toBeTrue()
        ->and($this->policy->clearLogs($healthViewer))->toBeFalse()
        ->and($this->policy->clearLogs($this->regular))->toBeFalse();
});

test('a user-management delegate cannot assign site roles, sign in as others, or act on administrators', function (string $ability) {
    $delegate = ['id' => 5, 'roles' => ['user_manager'], 'permissions' => ['manage_all_users']];

    expect($this->policy->manageUsers($delegate))->toBeTrue()
        ->and($this->policy->{$ability}($delegate))->toBeFalse()
        ->and($this->policy->{$ability}($this->admin))->toBeTrue()
        ->and($this->policy->{$ability}([]))->toBeFalse();
})->with(['assignSystemRoles', 'impersonateUsers', 'actOnAdministrators']);

test('handling reports needs its own permission, and gives nothing beyond the queue', function () {
    $reportHandler = ['id' => 4, 'roles' => ['moderator'], 'permissions' => ['handle_reports']];

    expect($this->policy->handleReports($reportHandler))->toBeTrue()
        ->and($this->policy->accessDashboard($reportHandler))->toBeTrue()
        ->and($this->policy->manageUsers($reportHandler))->toBeFalse()
        ->and($this->policy->managePosts($reportHandler))->toBeFalse()
        ->and($this->policy->handleReports($this->moderator))->toBeFalse()
        ->and($this->policy->handleReports($this->regular))->toBeFalse();
});

test('only administrators may change the moderation rules, even someone who handles reports', function () {
    $reportHandler = ['id' => 5, 'roles' => ['content_manager'], 'permissions' => ['handle_reports', 'manage_site_settings']];

    expect($this->policy->configureModeration($this->admin))->toBeTrue()
        ->and($this->policy->configureModeration($reportHandler))->toBeFalse()
        ->and($this->policy->configureModeration($this->regular))->toBeFalse();
});
