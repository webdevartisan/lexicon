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
$I->waitForElementVisible('[data-panel-dialog] #first_name', 10);

$I->see('Profile', $toggle);
$I->dontSeeElement("$nav ul");
$I->makeScreenshot('account-dialog-phone-menu-closed');

$I->click($toggle);
$I->seeElement("$nav ul");
$I->seeElement("{$toggle}[aria-expanded=\"true\"]");
$I->makeScreenshot('account-dialog-phone-menu-open');

$I->click('Danger', "$nav ul");
$I->waitForElementVisible('[data-panel-dialog] .acct-details summary', 10);
$I->see('Danger', $toggle);
$I->dontSeeElement("$nav ul");

$I->click($toggle);
$I->pressKey($toggle, WebDriverKeys::ESCAPE);
$I->dontSeeElement("$nav ul");
$I->seeElement('[data-panel-dialog][open]');

$I->putThePhoneAway();
