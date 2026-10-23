<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('open Security, two-step sign-in and Delete from the account dialog');

$I->signInAs('reader');
$I->amOnPage('/en');
$I->click('.nav-user-btn');
$I->waitForElementVisible('.nav-user-menu', 5);
$I->click('Settings', '.nav-user-menu');
$I->waitForElementVisible('[data-panel-dialog] #first_name', 10);

$I->click('Security', '[data-panel-dialog] .lx-account-nav');
$I->waitForText('Where you', 10, '[data-panel-dialog]');
$I->see('This device', '[data-panel-dialog]');
$I->dontSee('Sign-in activity', '[data-panel-dialog]');
$I->click('[data-panel-dialog] .acct-session.is-current summary');
$I->waitForElementVisible('[data-panel-dialog] .acct-session.is-current form', 5);
$I->makeScreenshot('account-dialog-security');

// Change password opens its own page in the dialog
$I->click('Change password', '[data-panel-dialog] .acct-card-row');
$I->waitForElementVisible('[data-panel-dialog] #current_password', 10);
$I->click('.acct-back', '[data-panel-dialog]');
$I->waitForText('Where you', 10, '[data-panel-dialog]');

// The two-step page opens in the same dialog and links back
$I->click('Manage', '[data-panel-dialog]');
$I->waitForElementVisible('[data-panel-dialog] .acct-back', 10);
$I->see('Two-step', '[data-panel-dialog]');
$I->makeScreenshot('account-dialog-two-factor');
$I->click('.acct-back', '[data-panel-dialog]');
$I->waitForText('Where you', 10, '[data-panel-dialog]');

// Your data stays on Security; closing the account has its own Danger tab
$I->see('Your data', '[data-panel-dialog]');
$I->click('Danger', '[data-panel-dialog] nav');
$I->waitForElementVisible('[data-panel-dialog] .acct-details summary', 10);
$I->dontSeeElement('[data-panel-dialog] .acct-details form input[name=password]:not([hidden])[type=password]:checked');
$I->makeScreenshot('account-dialog-delete');
