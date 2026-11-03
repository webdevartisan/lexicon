<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('save the name, social links and privacy settings on their own, reach the delete account tab, and open notification settings from the bell');

$I->signInAs('reader');
$I->amOnPage('/en');
$I->click('.nav-user-btn');
$I->waitForElementVisible('.nav-user-menu', 5);
$I->click('Settings', '.nav-user-menu');
$I->waitForElementVisible('[data-panel-dialog] #occupation', 10);
$I->dontSeeElement('[data-panel-dialog] #twitter');
$I->dontSeeElement('[data-panel-dialog] #handle');
$I->dontSeeElement('[data-panel-dialog] #first_name');

$I->openSettingsPage('Social links');
$I->waitForElementVisible('[data-panel-dialog] #twitter', 10);
$I->fillField('[data-panel-dialog] #twitter', 'https://twitter.com/walkreader');
$I->click('Save', '[data-panel-dialog] form[action$="/account/profile/update"]');
$I->waitForText('updated successfully', 10, '[data-panel-dialog]');
$I->seeInField('[data-panel-dialog] #twitter', 'https://twitter.com/walkreader');

$I->openSettingsPage('Name and tag');
$I->waitForElementVisible('[data-panel-dialog] #handle', 10);
$I->seeElement('[data-panel-dialog] #first_name');
$I->seeElement('[data-panel-dialog] input[name=show_name]');
$I->dontSeeElement('[data-panel-dialog] input[name=is_public]');
$I->click('Save', '[data-panel-dialog] form[action$="/account/profile/update"]');
$I->waitForText('updated successfully', 10, '[data-panel-dialog]');
$I->makeScreenshot('account-dialog-name');

// Who can see the profile is a privacy choice, so it saves with the rest of the preferences
$I->openSettingsPage('Preferences');
$I->waitForElementVisible('[data-panel-dialog] input[name=is_public]', 10);
$I->seeNumberOfElements('[data-panel-dialog] button[type=submit]', 1);
$I->waitForElement('[data-panel-dialog] .select-menu-search', 10);
$I->click('Save changes', '[data-panel-dialog] form[action$="/account/preferences/update"]');
$I->waitForText('Preferences saved', 10, '[data-panel-dialog]');
$I->waitForElementVisible('[data-panel-dialog] input[name=is_public]', 10);
$I->makeScreenshot('account-dialog-privacy');

$I->openSettingsPage('Social links');
$I->waitForElementVisible('[data-panel-dialog] #twitter', 10);
$I->seeInField('[data-panel-dialog] #twitter', 'https://twitter.com/walkreader');

$I->openSettingsPage('Delete account');
$I->waitForElementVisible('[data-panel-dialog] .acct-details summary', 10);
$I->makeScreenshot('account-dialog-danger');

$I->click('[data-panel-dialog] [data-panel-close]');
$I->click('.nav-bell-btn');
$I->waitForElementVisible('#notification-panel a.notif-icon-btn[data-panel]', 10);
$I->click('#notification-panel a.notif-icon-btn[data-panel]');
$I->waitForElement('[data-panel-dialog] .lx-account-nav a[aria-current="page"][href$="/account/notifications"]', 10);
$I->seeInCurrentUrl('/en');
