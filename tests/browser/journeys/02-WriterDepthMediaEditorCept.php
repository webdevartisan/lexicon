<?php

declare(strict_types=1);

use Facebook\WebDriver\WebDriverKeys;
use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('edit an image in the media library the same way as every other dialog');

$card = '#media-items [data-media-id]:first-child';
$open = "{$card} button[data-media-open]:not([aria-hidden])";
$editor = '#media-editor';
$total = static fn (): int => (int) $I->grabTextFrom('[data-media-count]');

$I->signInAs('writer');
$image = $I->makeImage('walk-editor');
$I->amOnPage('/en/dashboard/blog/'.$I->recall('blogId').'/media');
$I->attachFile('#media-file-input', $image);
$I->waitForElement("{$card} [data-media-name][title^=\"".pathinfo($image, PATHINFO_FILENAME).'"]', 15);

// Opens from the keyboard, on the shared dialog card
$I->pressKey($open, WebDriverKeys::ENTER);
$I->waitForElementVisible("{$editor} .confirm-card");
$I->waitForElement("{$editor} .cropper-container", 10);
$I->waitForElementVisible('#media-name', 10);
$I->dontSeeElement('#media-editor-apply');
$I->wait(0.5);
$I->seeThePageLooksRight('21-media-editor-desktop');

// Image settings and the save buttons sit on their own tab
$I->click('[data-editor-tab="edit"]');
$I->waitForText('Output:', 10, '#media-output');
$I->dontSeeElement('#media-name');
$I->seeThePageLooksRight('21-media-editor-desktop-edit');

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

// Escape closes it and focus goes back to the card's edit button
$I->pressKey('#media-editor-apply', WebDriverKeys::ESCAPE);
$I->waitForElementNotVisible($editor);
$I->assertTrue($I->executeJS("return document.activeElement.matches('{$open}');"));

// Renaming updates the card without a reload and says so, and the editor opens on its details again
$I->click($open);
$I->waitForElementVisible('#media-name', 10);
$I->fillField('#media-name', 'walk renamed');
$I->click('#media-details-save');
$I->waitForText('walk renamed', 10, "{$card} [data-media-name]");
$I->waitForText('Details saved', 10, '#toast-stack');
$I->seeThePageLooksRight('21-media-editor-toast');

// Saving a smaller copy adds it to the grid without a reload
$before = $total();
$I->click('[data-editor-tab="edit"]');
$I->waitForText('Output:', 10, '#media-output');
$I->click('#media-scale-label + [data-toggle] [data-value="0.5"]');
$I->click('#media-editor-apply');
$I->waitForElementNotVisible($editor, 15);
$I->waitForText('Saved as a new image', 10, '#toast-stack');
$I->assertSame($before + 1, $total());

// Deleting from the editor takes the card and the count with it
$I->click($open);
$I->waitForElementVisible('#media-name', 10);
$I->click('[data-editor-tab="edit"]');
$I->waitForText('Output:', 10, '#media-output');
$I->click('#media-editor-delete');
$I->waitForElementVisible('#confirmModalDanger');
$I->click('#confirmModalDanger [data-modal-confirm]');
$I->waitForElementNotVisible($editor, 15);
$I->waitForText('Image deleted', 10, '#toast-stack');
$I->assertSame($before, $total());
