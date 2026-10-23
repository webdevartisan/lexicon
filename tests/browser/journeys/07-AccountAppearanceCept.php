<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('pick dark or light in the user menu and see it on the front and in the dashboard');

$I->signInAs('writer');

$mode = "document.documentElement.getAttribute('data-mode')";
$menuBg = "getComputedStyle(document.querySelector('.nav-user-menu')).backgroundColor";
$modalBg = "getComputedStyle(document.querySelector('[data-panel-dialog] .panel-dialog-card')).backgroundColor";

$I->amOnPage('/en');
$I->click('.nav-user-btn');
$I->waitForElementVisible('.nav-user-menu', 5);
$I->click('.nav-user-menu [data-theme-choice="dark"]');
$I->seeElementInDOM('.nav-user-menu [data-theme-choice="dark"][aria-pressed="true"]');
$I->assertSame('dark', $I->executeJS("return $mode;"));
$I->assertSame('rgb(28, 28, 28)', $I->executeJS("return $menuBg;"));

$I->click('Settings', '.nav-user-menu');
$I->waitForElementVisible('[data-panel-dialog] .lx-account-nav', 10);
$I->assertSame('rgb(28, 28, 28)', $I->executeJS("return $modalBg;"));
$I->makeScreenshot('appearance-front-dark');
$I->click('[data-panel-dialog] [data-panel-close]');

$I->amOnPage('/en/dashboard');
$I->assertSame('dark', $I->executeJS("return $mode;"));
$I->click('.nav-user-btn');
$I->waitForElementVisible('.nav-user-menu', 5);
$I->click('.nav-user-menu [data-theme-choice="light"]');
$I->assertSame('light', $I->executeJS("return $mode;"));
$I->assertSame('rgb(255, 255, 255)', $I->executeJS("return $menuBg;"));
$I->wait(1);
$I->makeScreenshot('appearance-dashboard-light');

$I->amOnPage('/en');
$I->assertSame('light', $I->executeJS("return $mode;"));
$I->click('.nav-user-btn');
$I->waitForElementVisible('.nav-user-menu', 5);
$I->seeElement('.nav-user-menu [data-theme-choice="light"][aria-pressed="true"]');
$I->assertSame('rgb(255, 255, 255)', $I->executeJS("return $menuBg;"));

// The Preferences tab has the same choice, and the menu follows it
$I->click('Settings', '.nav-user-menu');
$I->waitForElementVisible('[data-panel-dialog] .lx-account-nav', 10);
$I->click('Preferences', '[data-panel-dialog] .lx-account-nav');
$I->waitForElementVisible('[data-panel-dialog] .nav-theme-choices.is-labelled', 10);
$I->see('Device', '[data-panel-dialog] .nav-theme-choices');
$I->seeElement('[data-panel-dialog] [data-theme-choice="light"][aria-pressed="true"]');
$I->wait(1);
$I->makeScreenshot('appearance-preferences-light');
$I->click('[data-panel-dialog] [data-theme-choice="dark"]');
$I->assertSame('dark', $I->executeJS("return $mode;"));
$I->seeElementInDOM('.nav-user-menu [data-theme-choice="dark"][aria-pressed="true"]');
$I->wait(1);
$I->makeScreenshot('appearance-preferences-dark');
$I->click('[data-panel-dialog] [data-panel-close]');

$I->click('.nav-user-btn');
$I->waitForElementVisible('.nav-user-menu', 5);
$I->makeScreenshot('appearance-menu-icons');
$I->click('.nav-user-menu [data-theme-choice="auto"]');
$I->executeJS("localStorage.removeItem('theme-pref');");
