<?php

declare(strict_types=1);

use Facebook\WebDriver\WebDriverKeys;
use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('switch settings pages on a phone from the menu button that names the open page');

$nav = '[data-panel-dialog] .lx-account-nav';
$toggle = "$nav [data-panel-menu]";

$I->signInAs('reader');
$I->amOnAPhone();
$I->amOnPage('/en');
$I->click('.nav-user-btn');
$I->waitForElementVisible('.nav-user-menu', 5);
$I->click('Settings', '.nav-user-menu');
$I->waitForElementVisible('[data-panel-dialog] #occupation', 10);

$I->see('Profile', $toggle);
$I->dontSeeElement("$nav ul");
$I->makeScreenshot('account-dialog-phone-menu-closed');

$I->click($toggle);
$I->seeElement("$nav ul");
$I->seeElement("{$toggle}[aria-expanded=\"true\"]");
$I->makeScreenshot('account-dialog-phone-menu-open');

$I->click('Delete account', "$nav ul");
$I->waitForElementVisible('[data-panel-dialog] .acct-details summary', 10);
$I->see('Delete account', $toggle);
$I->dontSeeElement("$nav ul");

$I->click($toggle);
$I->pressKey($toggle, WebDriverKeys::ESCAPE);
$I->dontSeeElement("$nav ul");
$I->seeElement('[data-panel-dialog][open]');

// The full settings page folds the same way, so the tabs never fill the first screen
$page = '.lx-main .lx-account-nav';
$I->amOnPage('/en/account/security');
$I->see('Security', "$page [data-panel-menu]");
$I->dontSeeElement("$page ul");
$I->makeScreenshot('account-page-phone-menu-closed');
$I->click("$page [data-panel-menu]");
$I->seeElement("$page ul");
$I->makeScreenshot('account-page-phone-menu-open');
$I->pressKey("$page [data-panel-menu]", WebDriverKeys::ESCAPE);
$I->dontSeeElement("$page ul");
$I->click("$page [data-panel-menu]");
$I->click('Name and tag', "$page ul");
$I->waitForElementVisible('.lx-main #handle', 10);
$I->seeInCurrentUrl('/account/name');
$I->see('Name and tag', "$page [data-panel-menu]");

$I->putThePhoneAway();
