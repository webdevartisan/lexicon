<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('edit my profile and avatar from the account dialog, opened from the front menu');

$I->signInAs('reader');
$I->amOnPage('/en');
$I->click('.nav-user-btn');
$I->waitForElementVisible('.nav-user-menu', 5);
$I->click('Account', '.nav-user-menu');
$I->waitForElementVisible('[data-account-dialog] #first_name', 10);
$I->seeElement('[data-account-dialog] .acct-avatar');

$I->fillField('[data-account-dialog] #first_name', 'Walk');
$I->fillField('[data-account-dialog] #last_name', 'Reader');
$I->fillField('[data-account-dialog] #occupation', 'Walkthrough tester');
$I->click('Save', '[data-account-dialog] form[action$="/account/profile/update"]');
$I->waitForText('updated successfully', 10, '[data-account-dialog]');
$I->seeInField('[data-account-dialog] #occupation', 'Walkthrough tester');

// Upload opens the cropper, applying it closes it again
$I->attachFile('[data-account-dialog] #avatar-input', $I->makeImage('walk-avatar'));
$I->waitForElementVisible('#avatar-cropper-modal .cropper-container', 15);
$I->makeScreenshot('account-dialog-cropper');
$I->click('#avatar-cropper-apply');
$I->waitForElementNotVisible('#avatar-cropper-modal', 10);
$I->seeElement('[data-account-dialog] #avatar-preview');

// Removing the photo goes through the inline confirmation
$I->click('[data-account-dialog] .acct-avatar-side .acct-details summary');
$I->click('Remove current avatar', '[data-account-dialog] .acct-avatar-side .acct-details form');
$I->waitForElement('[data-account-dialog] #avatar-placeholder', 10);
$I->makeScreenshot('account-dialog-profile');
