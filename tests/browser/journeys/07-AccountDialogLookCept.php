<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('see the account dialog at laptop and phone width, in light and dark');

$I->signInAs('reader');

$shots = [
    ['/en', 'front-notifications', false, null, 'notifications', '.acct-switch'],
    ['/en', 'front-security', false, null, 'security', '.acct-card-row'],
    ['/en', 'front-profile', false, null, 'profile', '.acct-avatar'],
    ['/en/dashboard', 'cp-dark-security', false, 'dark', 'security', '.acct-card-row'],
    ['/en', 'phone-security', true, null, 'security', '.acct-card-row'],
];

foreach ($shots as [$path, $name, $onAPhone, $mode, $page, $ready]) {
    if ($onAPhone) {
        $I->amOnAPhone();
    }
    $I->amOnPage($path);
    if ($mode !== null) {
        $I->executeJS("document.documentElement.setAttribute('data-mode', '$mode');");
    }
    $I->executeJS("var l = document.createElement('a'); l.href = '/en/account/$page'; l.setAttribute('data-panel', 'wide'); document.body.prepend(l); l.click();");
    $I->waitForElementVisible("[data-panel-dialog] $ready", 10);
    $I->makeScreenshot("account-dialog-$name");
}

$I->putThePhoneAway();
