<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('change email notifications from the account menu, on the front and in the dashboard');

$I->signInAs('writer');

$menus = [
    '/en' => ['.nav-user-btn', '.nav-user-menu'],
    '/en/dashboard' => ['#dropdownMenuButton', '#theme-menu .dropdown-menu'],
];

foreach ($menus as $start => [$toggle, $menu]) {
    $I->amOnPage($start);
    $I->click($toggle);
    $I->waitForElementVisible($menu, 5);
    $I->click('Account', $menu);
    $I->waitForElementVisible('[data-account-dialog] .acct-dialog-nav', 10);
    $I->waitForElementVisible('[data-account-dialog] .acct-title', 10);
    $I->click('Notifications', '[data-account-dialog] .acct-dialog-nav');
    $I->waitForText('Email notifications', 10, '[data-account-dialog]');
    $I->click('[data-account-dialog] form[action$="/account/notifications/update"] button[type=submit]');
    $I->waitForText('Notification settings saved.', 10, '[data-account-dialog]');
    $I->seeElement('[data-account-dialog][open]');
    $I->executeJS("document.querySelector('[data-account-close]').click();");
    $I->dontSeeElement('[data-account-dialog][open]');
}

$I->amOnPage('/en/account/notifications');
$I->seeElement('.acct-page .acct-form');

// Preferences, opened from "Account settings" in the dashboard menu
$I->amOnPage('/en/dashboard');
$I->click('#dropdownMenuButton');
$I->waitForElementVisible('#theme-menu .dropdown-menu', 5);
$I->click('Account Settings', '#theme-menu .dropdown-menu');
$I->waitForText('Interface language', 10, '[data-account-dialog]');
$I->see('Timezone', '[data-account-dialog]');
$I->click('[data-account-dialog] .acct-details summary');
$I->waitForElementVisible('[data-account-dialog] #new_email', 5);
$I->click('Save', '[data-account-dialog] form[action$="/account/preferences/update"]');
$I->waitForText('saved', 10, '[data-account-dialog]');
$I->seeElement('[data-account-dialog][open]');
$I->makeScreenshot('account-dialog-preferences');
