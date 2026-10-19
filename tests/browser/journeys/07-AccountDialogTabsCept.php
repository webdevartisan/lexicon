<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('save the public page and social links tabs on their own, and reach the danger tab');

$I->signInAs('reader');
$I->amOnPage('/en');
$I->click('.nav-user-btn');
$I->waitForElementVisible('.nav-user-menu', 5);
$I->click('Account', '.nav-user-menu');
$I->waitForElementVisible('[data-account-dialog] #first_name', 10);
$I->dontSeeElement('[data-account-dialog] #twitter');
$I->dontSeeElement('[data-account-dialog] #handle');

$I->click('Social links', '[data-account-dialog] nav');
$I->waitForElementVisible('[data-account-dialog] #twitter', 10);
$I->fillField('[data-account-dialog] #twitter', 'https://twitter.com/walkreader');
$I->click('Save', '[data-account-dialog] form[action$="/account/profile/update"]');
$I->waitForText('updated successfully', 10, '[data-account-dialog]');
$I->seeInField('[data-account-dialog] #twitter', 'https://twitter.com/walkreader');

$I->click('Public page', '[data-account-dialog] nav');
$I->waitForElementVisible('[data-account-dialog] #handle', 10);
$I->seeElement('[data-account-dialog] input[name=is_public]');
$I->click('Save', '[data-account-dialog] form[action$="/account/profile/update"]');
$I->waitForText('updated successfully', 10, '[data-account-dialog]');
$I->makeScreenshot('account-dialog-public');

$I->click('Social links', '[data-account-dialog] nav');
$I->waitForElementVisible('[data-account-dialog] #twitter', 10);
$I->seeInField('[data-account-dialog] #twitter', 'https://twitter.com/walkreader');

$I->click('Danger', '[data-account-dialog] nav');
$I->waitForElementVisible('[data-account-dialog] .acct-details summary', 10);
$I->makeScreenshot('account-dialog-danger');
