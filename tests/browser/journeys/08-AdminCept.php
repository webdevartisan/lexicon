<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('read the admin overview, find the new writer and open the main admin pages');

if (!getenv('WALK_ADMIN_EMAIL') || !getenv('WALK_ADMIN_PASSWORD')) {
    $scenario->skip('Set WALK_ADMIN_EMAIL and WALK_ADMIN_PASSWORD to a local test admin to run this');
}

$I->signInAs('admin');
$I->amOnPage('/en/admin');
$I->dontSeeInTitle('403');
$I->seeThePageLooksRight('80-admin-overview');

$I->amOnPage('/en/admin/users?q='.urlencode($I->recall('writerEmail')));
$I->seeElement('table a[href*="/admin/users/"]');
$I->seeThePageLooksRight('81-admin-finds-writer');

// Only a screenshot here: this page scrolls sideways, which 08-AdminReportsWidthCept tracks
$I->amOnPage('/en/admin/reports');
$I->dontSeeInTitle('403');
$I->makeScreenshot('82-admin-reports');

foreach (['blogs', 'settings', 'mail-queue', 'scheduled-tasks', 'audit-log', 'front-page', 'roles'] as $area) {
    $I->amOnPage("/en/admin/{$area}");
    $I->dontSeeInTitle('not found');
    $I->dontSeeInTitle('403');
    $I->seeThePageLooksRight("82-admin-{$area}");
}
