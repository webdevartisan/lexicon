<?php

declare(strict_types=1);

use Facebook\WebDriver\WebDriverKeys;
use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('edit an image in the media library the same way as every other dialog');

$card = '#media-grid figure:first-child';
$editor = '#media-editor';
$total = static fn (): int => (int) $I->grabTextFrom('[data-media-total]');

$I->signInAs('writer');
$image = $I->makeImage('walk-editor');
$I->amOnPage('/en/dashboard/blog/'.$I->recall('blogId').'/media');
$I->attachFile('#media-uploader-input', $image);
$I->waitForElement("{$card} figcaption[title^=\"".pathinfo($image, PATHINFO_FILENAME).'"]', 15);

// Opens from the keyboard, on the shared dialog card
$I->pressKey("{$card} [data-media-open]", WebDriverKeys::ENTER);
$I->waitForElementVisible("{$editor} .confirm-card");
$I->waitForElement("{$editor} .cropper-container", 10);
$I->waitForText('Output:', 10, '#media-output');

// Follows the light and dark switch like the other dialogs
$cardColour = "return getComputedStyle(document.querySelector('{$editor} .confirm-card')).backgroundColor;";
$I->executeJS("document.documentElement.setAttribute('data-mode', 'light');");
$I->assertSame('rgb(255, 255, 255)', $I->executeJS($cardColour));
$I->executeJS("document.documentElement.setAttribute('data-mode', 'dark');");
$I->assertNotSame('rgb(255, 255, 255)', $I->executeJS($cardColour));

// Cancelling the delete confirmation goes back to the editor, still modal
$I->click('#media-editor-delete');
$I->waitForElementVisible('#confirmModalDanger');
$I->click('#confirmModalDanger [data-modal-cancel]');
$I->waitForElementNotVisible('#confirmModalDanger');
$I->seeElement($editor);
$I->dontSeeElement('#backDropDiv.hidden');
$I->assertTrue($I->executeJS("return document.body.classList.contains('overflow-hidden');"));

// Escape closes it and focus goes back to the card
$I->pressKey('#media-name', WebDriverKeys::ESCAPE);
$I->waitForElementNotVisible($editor);
$I->assertTrue($I->executeJS("return document.activeElement.matches('{$card} [data-media-open]');"));

// Renaming updates the card without a reload
$I->click("{$card} [data-media-open]");
$I->waitForText('Output:', 10, '#media-output');
$I->fillField('#media-name', 'walk renamed');
$I->click('#media-details-save');
$I->waitForText('walk renamed', 10, "{$card} figcaption");

// Saving a smaller copy adds it to the grid without a reload
$before = $total();
$I->click('#media-scale-label + [data-toggle] [data-value="0.5"]');
$I->click('#media-editor-apply');
$I->waitForElementNotVisible($editor, 15);
$I->assertSame($before + 1, $total());

// Deleting from the editor takes the card and the count with it
$I->click("{$card} [data-media-open]");
$I->waitForText('Output:', 10, '#media-output');
$I->click('#media-editor-delete');
$I->waitForElementVisible('#confirmModalDanger');
$I->click('#confirmModalDanger [data-modal-confirm]');
$I->waitForElementNotVisible($editor, 15);
$I->assertSame($before, $total());
