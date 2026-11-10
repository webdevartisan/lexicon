<?php

declare(strict_types=1);

use Facebook\WebDriver\WebDriverKeys;
use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('change a draft link until the post goes live, then find the post at that link');

$stamp = date('His');
$link = "moved-link-{$stamp}";
$blogSlug = $I->recall('blogSlug');

$I->signInAs('writer');
$I->amOnPage('/en/dashboard/post/new');
$I->waitForElement('#content_ifr', 15);
$I->fillField('title', "A link that moves {$stamp}");
$I->writeInTheEmptyEditor('A draft whose address changes before it goes live.');

// The first autosave, half a minute after the last keystroke, makes the draft and leaves the address open to change
$I->openEditorDialog('postLinkDialog');
$I->waitForText('You can change it until the post is published.', 45);
$I->assertNull($I->grabAttributeFrom('input[name=slug]', 'readonly'));
$I->fillField('slug', $link);
$I->waitForText('This address is available.', 10);
$I->closeEditorDialog('postLinkDialog');
$I->click('Save draft');
$I->waitForText('Post updated successfully.');
$I->openEditorDialog('postLinkDialog');
$I->seeInField('slug', $link);

// The edit page checks the address too, and the post's own address counts as free
$I->click('input[name=slug]');
$I->pressKey('input[name=slug]', ' ');
$I->pressKey('input[name=slug]', WebDriverKeys::BACKSPACE);
$I->waitForText('This address is available.', 10);
$I->closeEditorDialog('postLinkDialog');

// Publishing fixes the address, so the field goes away and readers find the post there
$I->publishFromTheEditor();
$I->waitForText('Post updated successfully.');
$I->dontSeeElement('input[name=slug]');
$editUrl = $I->grabFromCurrentUrl();
$I->amAGuest();
$I->amOnPage("/en/blog/{$blogSlug}/{$link}");
$I->dontSeeInTitle('Page not found');

// Leave the walk blog as later journeys expect it
$I->signInAs('writer');
$I->amOnPage($editUrl);
$I->deleteFromTheEditor();
$I->acceptConfirm();
$I->waitForText('deleted', 10);
