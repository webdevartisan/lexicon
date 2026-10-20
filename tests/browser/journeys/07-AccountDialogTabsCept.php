<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('save the public page and social links tabs on their own, reach the danger tab, and open notification settings from the bell');

$I->signInAs('reader');
$I->amOnPage('/en');
$I->click('.nav-user-btn');
$I->waitForElementVisible('.nav-user-menu', 5);
$I->click('Settings', '.nav-user-menu');
$I->waitForElementVisible('[data-panel-dialog] #first_name', 10);
$I->dontSeeElement('[data-panel-dialog] #twitter');
$I->dontSeeElement('[data-panel-dialog] #handle');

$I->click('Social links', '[data-panel-dialog] nav');
$I->waitForElementVisible('[data-panel-dialog] #twitter', 10);
$I->fillField('[data-panel-dialog] #twitter', 'https://twitter.com/walkreader');
$I->click('Save', '[data-panel-dialog] form[action$="/account/profile/update"]');
$I->waitForText('updated successfully', 10, '[data-panel-dialog]');
$I->seeInField('[data-panel-dialog] #twitter', 'https://twitter.com/walkreader');

$I->click('Public page', '[data-panel-dialog] nav');
$I->waitForElementVisible('[data-panel-dialog] #handle', 10);
$I->seeElement('[data-panel-dialog] input[name=is_public]');
$I->click('Save', '[data-panel-dialog] form[action$="/account/profile/update"]');
$I->waitForText('updated successfully', 10, '[data-panel-dialog]');
$I->makeScreenshot('account-dialog-public');

$I->click('Social links', '[data-panel-dialog] nav');
$I->waitForElementVisible('[data-panel-dialog] #twitter', 10);
$I->seeInField('[data-panel-dialog] #twitter', 'https://twitter.com/walkreader');

$I->click('Danger', '[data-panel-dialog] nav');
$I->waitForElementVisible('[data-panel-dialog] .acct-details summary', 10);
$I->makeScreenshot('account-dialog-danger');

$I->click('[data-panel-dialog] [data-panel-close]');
$I->click('.nav-bell-btn');
$I->waitForElementVisible('#notification-panel a.notif-icon-btn[data-panel]', 10);
$I->click('#notification-panel a.notif-icon-btn[data-panel]');
$I->waitForElementVisible('[data-panel-dialog] .lx-account-nav a[aria-current="page"][href$="/account/notifications"]', 10);
$I->seeInCurrentUrl('/en');
