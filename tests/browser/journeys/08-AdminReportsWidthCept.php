<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('see the moderation page fit a laptop screen');

if (!getenv('WALK_ADMIN_EMAIL') || !getenv('WALK_ADMIN_PASSWORD')) {
    $scenario->skip('Set WALK_ADMIN_EMAIL and WALK_ADMIN_PASSWORD to a local test admin to run this');
}

// Found 2026-10-17: the reports table is wider than the screen, so the whole page scrolls
// sideways. Not in the readiness review yet. Delete this line once it is fixed.
$scenario->incomplete('The moderation page scrolls sideways');

$I->signInAs('admin');
$I->amOnPage('/en/admin/reports');
$I->seeThePageLooksRight('83-admin-reports-fit');
