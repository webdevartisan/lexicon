<?php

declare(strict_types=1);

use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('measure type, contrast, tap targets and overflow on the front pages at desktop and phone width');

$guestPages = ['/en', '/en/discover', '/en/login', '/en/register', '/en/about', '/en/getting-started',
    '/en/contact', '/en/privacy', '/en/terms', '/en/no-such-page'];
$readerPages = ['/en/account/profile', '/en/account/public', '/en/account/preferences', '/en/account/notifications',
    '/en/account/security', '/en/saved', '/en/subscriptions', '/en/notifications'];
$script = file_get_contents(codecept_root_dir('_support/design-audit.js'));

// Headless Chrome will not shrink its window below roughly 500px, so the phone is emulated instead
$setViewport = function (string $viewport) use ($I): void {
    $I->executeInSelenium(function (RemoteWebDriver $driver) use ($viewport): void {
        $cdp = new ChromeDevToolsDriver($driver);
        if ($viewport === 'phone') {
            $cdp->execute('Emulation.setDeviceMetricsOverride', [
                'width' => 390, 'height' => 844, 'deviceScaleFactor' => 2, 'mobile' => true,
            ]);
            return;
        }
        $cdp->execute('Emulation.clearDeviceMetricsOverride');
    });
};

$report = ['desktop' => [], 'phone' => []];
$measure = function (array $paths) use ($I, $script, $setViewport, &$report): void {
    foreach (['desktop', 'phone'] as $viewport) {
        $setViewport($viewport);
        foreach ($paths as $path) {
            $I->amOnPage($path);
            $I->waitForElementVisible('body', 5);
            $report[$viewport][] = json_decode($I->executeJS($script), true);
        }
    }
};

$I->amAGuest();
$I->amOnPage('/en');
$I->declineCookies();
$measure($guestPages);

// The error card follows the appearance choice, so it is measured in both modes
foreach (['light', 'dark'] as $mode) {
    $I->executeJS("localStorage.setItem('theme-pref', '{$mode}')");
    foreach (['desktop', 'phone'] as $viewport) {
        $setViewport($viewport);
        $I->amOnPage('/en/no-such-page');
        $I->makeScreenshot("design-error-{$mode}-{$viewport}");
        $report["error-{$mode}"][] = json_decode($I->executeJS($script), true);
    }
}
$I->executeJS("localStorage.removeItem('theme-pref')");

$setViewport('desktop');
$I->signInAs('reader');
$I->amOnPage('/en/account/public');
$handle = $I->grabValueFrom('handle');
$measure([...$readerPages, "/en/profile/{$handle}"]);

file_put_contents(codecept_output_dir('design-audit.json'), json_encode($report, JSON_PRETTY_PRINT));
