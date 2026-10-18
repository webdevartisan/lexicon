<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('close registration and see the sign-up form go away');

if (!getenv('WALK_ADMIN_EMAIL') || !getenv('WALK_ADMIN_PASSWORD')) {
    $scenario->skip('Set WALK_ADMIN_EMAIL and WALK_ADMIN_PASSWORD to a local test admin to run this');
}

$save = "//form[.//select[@id='registration_enabled']]//button[@type='submit']";

$I->signInAs('admin');
$I->amOnPage('/en/admin/settings');
$I->click('Users & Availability');
$I->chooseFromMenu('#registration_enabled', 'Disabled');
$I->click($save);
$I->waitForText('Settings saved');

// Note what a visitor gets, then open registration again before checking,
// so a failing check never leaves sign-ups closed for the next run
$I->amAGuest();
$I->amOnPage('/en/register');
$signUpFields = count($I->grabMultiple('input[name=age_confirmed]'));
$I->makeScreenshot('95-register-while-closed');

$I->signInAs('admin');
$I->amOnPage('/en/admin/settings');
$I->click('Users & Availability');
$I->chooseFromMenu('#registration_enabled', 'Enabled');
$I->click($save);
$I->waitForText('Settings saved');

$I->assertSame(0, $signUpFields, 'The sign-up form is still offered while registration is closed');
