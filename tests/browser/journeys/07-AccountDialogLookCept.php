<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('see the account dialog at laptop and phone width, in light and dark');

$I->signInAs('reader');

$shots = [
    ['/en', 'front-notifications', 1280, 900, null, 'notifications', '.acct-switch'],
    ['/en', 'front-security', 1280, 900, null, 'security', '.acct-card-row'],
    ['/en', 'front-profile', 1280, 900, null, 'profile', '.acct-avatar'],
    ['/en/dashboard', 'cp-dark-security', 1280, 900, 'dark', 'security', '.acct-card-row'],
    ['/en', 'phone-security', 390, 800, null, 'security', '.acct-card-row'],
];

foreach ($shots as [$path, $name, $width, $height, $mode, $page, $ready]) {
    $I->resizeWindow($width, $height);
    $I->amOnPage($path);
    if ($mode !== null) {
        $I->executeJS("document.documentElement.setAttribute('data-mode', '$mode');");
    }
    $I->executeJS("var l = document.createElement('a'); l.href = '/en/account/$page'; l.setAttribute('data-panel', 'wide'); document.body.prepend(l); l.click();");
    $I->waitForElementVisible("[data-panel-dialog] $ready", 10);
    $I->makeScreenshot("account-dialog-$name");
}

$I->resizeWindow(1280, 900);
