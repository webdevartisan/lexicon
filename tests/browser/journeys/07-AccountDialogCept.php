<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('change email notifications from the account menu, on the front and in the dashboard');

$I->signInAs('writer');

$menus = [
    '/en' => ['.nav-user-btn', '.nav-user-menu'],
    '/en/dashboard' => ['.nav-user-btn', '.nav-user-menu'],
];

foreach ($menus as $start => [$toggle, $menu]) {
    $I->amOnPage($start);
    $I->click($toggle);
    $I->waitForElementVisible($menu, 5);
    $I->click('Settings', $menu);
    $I->waitForElementVisible('[data-panel-dialog] .lx-account-nav', 10);
    $I->waitForElementVisible('[data-panel-dialog] .acct-title', 10);
    $I->click('Notifications', '[data-panel-dialog] .lx-account-nav');
    $I->waitForText('Email notifications', 10, '[data-panel-dialog]');
    $I->click('[data-panel-dialog] form[action$="/account/notifications/update"] button[type=submit]');
    $I->waitForText('Notification settings saved.', 10, '[data-panel-dialog]');
    $I->seeElement('[data-panel-dialog][open]');
    $I->executeJS("document.querySelector('[data-panel-close]').click();");
    $I->dontSeeElement('[data-panel-dialog][open]');
}

$I->amOnPage('/en/account/notifications');
$I->seeElement('.acct-page .acct-form');

// Preferences, reached through Settings in the dashboard menu
$I->amOnPage('/en/dashboard');
$I->click('.nav-user-btn');
$I->waitForElementVisible('.nav-user-menu', 5);
$I->click('Settings', '.nav-user-menu');
$I->waitForElementVisible('[data-panel-dialog] .lx-account-nav', 10);
$I->click('Preferences', '[data-panel-dialog] .lx-account-nav');
$I->waitForText('Interface language', 10, '[data-panel-dialog]');
$I->see('Timezone', '[data-panel-dialog]');
$I->click('[data-panel-dialog] .acct-details summary');
$I->waitForElementVisible('[data-panel-dialog] #new_email', 5);
$I->click('Save', '[data-panel-dialog] form[action$="/account/preferences/update"]');
$I->waitForText('saved', 10, '[data-panel-dialog]');
$I->seeElement('[data-panel-dialog][open]');
$I->makeScreenshot('account-dialog-preferences');

// From a blog post, whose theme layout has the same menu
$I->amOnPage('/en');
$post = $I->grabAttributeFrom("//main//a[contains(substring-after(@href, '/blog/'), '/')]", 'href');
$I->amOnPage($post);
$I->click('.nav-user-btn');
$I->waitForElementVisible('.nav-user-menu', 5);
$I->click('Settings', '.nav-user-menu');
$I->waitForElementVisible('[data-panel-dialog] .lx-account-nav', 10);
$I->click('Notifications', '[data-panel-dialog] .lx-account-nav');
$I->waitForText('Email notifications', 10, '[data-panel-dialog]');
$I->seeInCurrentUrl(parse_url($post, PHP_URL_PATH));
$I->makeScreenshot('account-dialog-on-post');
