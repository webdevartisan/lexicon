<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('turn on localized posts, write a Greek version, and see Greek readers get it while English readers keep the original');

$blog = '/en/dashboard/blog/'.$I->recall('blogId');
$postEditUrl = $I->recall('postEditUrl');
$englishUrl = $I->recall('postUrl');
$greekUrl = preg_replace('#^/en/#', '/el/', $englishUrl);
preg_match('#/post/(\d+)/edit#', $postEditUrl, $match);
$postId = $match[1];
$greekTitle = 'Γεια σου από τον περίπατο';

// A Greek version left by an earlier run that stopped halfway would hide the empty tab, so it goes first
$I->signInAs('writer');
$I->amOnPage("/en/dashboard/post/{$postId}/translations/el");
if (count($I->grabMultiple('#deleteTranslation')) > 0) {
    $I->click('Delete Translation');
    $I->acceptConfirm();
    $I->waitForText('Translation deleted');
}

// Without the setting the post has no language tabs; switching it off first lets the journey run again
$I->amOnPage("{$blog}/settings");
$I->scrollDownTo('#translations_enabled');
$I->uncheckOption('#translations_enabled');
$I->click('button[data-actionbar-primary]');
$I->waitForText('Blog updated');
$I->amOnPage($postEditUrl);
$I->dontSeeElement('nav[aria-label="Post languages"]');

// Turning it on adds them
$I->amOnPage("{$blog}/settings");
$I->scrollDownTo('#translations_enabled');
$I->checkOption('#translations_enabled');
$I->click('button[data-actionbar-primary]');
$I->waitForText('Blog updated');
$I->seeThePageLooksRight('localized-turned-on');
$I->amOnPage($postEditUrl);
$I->seeElement('nav[aria-label="Post languages"]');
$I->seeThePageLooksRight('localized-tabs');

// The Greek tab starts empty and takes a title and a body
$I->click('EL', 'nav[aria-label="Post languages"]');
$I->waitForElement('#content_ifr', 15);
$I->see('Not translated yet');
$I->fillField('title', $greekTitle);
// WebDriver's keyboard can't send Greek letters one by one, so the editor is filled directly
$I->executeJS("tinymce.get('content').setContent('<p>Η ελληνική έκδοση της ανάρτησης.</p>')");
$I->click('Save Translation');
$I->waitForText('Translation saved');
$I->dontSee('Not translated yet');
$I->seeThePageLooksRight('localized-greek-saved');

// Greek readers get the Greek version, English readers keep the original
$I->amAGuest();
$I->amOnPage($greekUrl);
$I->seeInTitle($greekTitle);
$I->seeThePageLooksRight('localized-guest-greek');
$I->amOnPage($englishUrl);
$I->dontSeeInTitle($greekTitle);

// Deleting the translation sends Greek readers back to the original
$I->signInAs('writer');
$I->amOnPage("/en/dashboard/post/{$postId}/translations/el");
$I->click('Delete Translation');
$I->acceptConfirm('Delete this translation?');
$I->waitForText('Translation deleted');
$I->amAGuest();
$I->amOnPage($greekUrl);
$I->dontSeeInTitle($greekTitle);
