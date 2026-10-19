<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('open Security, two-step sign-in and Delete from the account dialog');

$I->signInAs('reader');
$I->amOnPage('/en');
$I->click('.nav-user-btn');
$I->waitForElementVisible('.nav-user-menu', 5);
$I->click('Account', '.nav-user-menu');
$I->waitForElementVisible('[data-account-dialog] #first_name', 10);

$I->click('Security', '[data-account-dialog] .acct-dialog-nav');
$I->waitForText('Where you', 10, '[data-account-dialog]');
$I->see('This device', '[data-account-dialog]');
$I->dontSee('Sign-in activity', '[data-account-dialog]');
$I->click('[data-account-dialog] .acct-session.is-current summary');
$I->waitForElementVisible('[data-account-dialog] .acct-session.is-current form', 5);
$I->makeScreenshot('account-dialog-security');

// Change password opens its own page in the dialog
$I->click('Change password', '[data-account-dialog] .acct-card-row');
$I->waitForElementVisible('[data-account-dialog] #current_password', 10);
$I->click('.acct-back', '[data-account-dialog]');
$I->waitForText('Where you', 10, '[data-account-dialog]');

// The two-step page opens in the same dialog and links back
$I->click('Manage', '[data-account-dialog]');
$I->waitForElementVisible('[data-account-dialog] .acct-back', 10);
$I->see('Two-step', '[data-account-dialog]');
$I->makeScreenshot('account-dialog-two-factor');
$I->click('.acct-back', '[data-account-dialog]');
$I->waitForText('Where you', 10, '[data-account-dialog]');

// Your data stays on Security; closing the account has its own Danger tab
$I->see('Your data', '[data-account-dialog]');
$I->click('Danger', '[data-account-dialog] nav');
$I->waitForElementVisible('[data-account-dialog] .acct-details summary', 10);
$I->dontSeeElement('[data-account-dialog] .acct-details form input[name=password]:not([hidden])[type=password]:checked');
$I->makeScreenshot('account-dialog-delete');
