<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('open Saved, Replies and Subscriptions each in their own dialog from the menu');

$openFromMenu = function (string $item) use ($I): void {
    $I->click('.nav-user-btn');
    $I->waitForElementVisible('.nav-user-menu', 5);
    $I->click($item, '.nav-user-menu');
    $I->waitForElementVisible('[data-panel-dialog] .lx-reader-title', 10);
};

$I->signInAs('reader');
$I->amOnPage('/en');

// Saved, with Liked switched to inside the same dialog
$openFromMenu('Articles');
$I->see('Articles', '[data-panel-dialog] .lx-reader-title');
$I->dontSeeElement('[data-panel-dialog] .lx-account-nav');
$I->makeScreenshot('reader-dialog-saved');
// Remove posts back to the same panel; it also leaves ReaderDepth a post to like and save afresh
if ($I->grabMultiple('[data-panel-dialog] .lx-reader-row .lx-reader-act-btn') !== []) {
    $I->click('[data-panel-dialog] .lx-reader-row .lx-reader-act-btn');
    $I->waitForText('Removed from saved', 10, '[data-panel-dialog]');
    $I->see('Articles', '[data-panel-dialog] .lx-reader-title');
}
$I->click('Liked', '[data-panel-dialog] .lx-reader-tabs');
$I->waitForText('Liked', 10, '[data-panel-dialog] .lx-reader-title');
if ($I->grabMultiple('[data-panel-dialog] .lx-reader-row .lx-reader-act-btn') !== []) {
    $I->click('[data-panel-dialog] .lx-reader-row .lx-reader-act-btn');
    $I->waitForText('Removed from liked', 10, '[data-panel-dialog]');
    $I->see('Liked', '[data-panel-dialog] .lx-reader-title');
}
$I->seeInCurrentUrl('/en');
$I->dontSeeInCurrentUrl('/saved');
$I->click('[data-panel-dialog] [data-panel-close]');
$I->waitForElementNotVisible('[data-panel-dialog]', 5);

// Replies, then Your comments
$openFromMenu('Comments');
$I->see('Comments', '[data-panel-dialog] .lx-reader-title');
$I->makeScreenshot('reader-dialog-replies');
$I->click('Your comments', '[data-panel-dialog] .lx-reader-tabs');
$I->waitForText('Your comments', 10, '[data-panel-dialog] .lx-reader-title');
$I->pressKey('[data-panel-dialog]', \Facebook\WebDriver\WebDriverKeys::ESCAPE);
$I->waitForElementNotVisible('[data-panel-dialog]', 5);

// Subscriptions: unsubscribing and undoing both stay in the dialog
$openFromMenu('Subscriptions');
$I->see('Subscriptions', '[data-panel-dialog] .lx-reader-title');
if ($I->grabMultiple('[data-panel-dialog] .lx-reader-row .lx-reader-act-btn') !== []) {
    $I->click('[data-panel-dialog] .lx-reader-row .lx-reader-act-btn');
    $I->waitForElementVisible('[data-panel-dialog] .lx-reader-undo', 10);
    $I->makeScreenshot('reader-dialog-unsubscribed');
    $I->click('Undo', '[data-panel-dialog] .lx-reader-undo');
    $I->waitForText('again', 10, '[data-panel-dialog]');
    $I->seeElement('[data-panel-dialog] .lx-reader-row');
}
$I->makeScreenshot('reader-dialog-subscriptions');
$I->dontSeeInCurrentUrl('/subscriptions');

// The full pages still render the same panels on their own
foreach (['saved' => 'Articles', 'saved/liked' => 'Liked', 'replies' => 'Comments', 'replies/mine' => 'Your comments', 'subscriptions' => 'Subscriptions'] as $page => $title) {
    $I->amOnPage("/en/{$page}");
    $I->see($title, 'section.lx-reader .lx-reader-title');
}

// A blog post uses its theme's layout; the menu there opens the same modal
$I->amOnPage('/en');
$post = $I->grabAttributeFrom("//main//a[contains(substring-after(@href, '/blog/'), '/')]", 'href');
$I->amOnPage($post);
$openFromMenu('Articles');
$I->see('Articles', '[data-panel-dialog] .lx-reader-title');
$I->seeInCurrentUrl(parse_url($post, PHP_URL_PATH));
$I->makeScreenshot('reader-dialog-on-post');
