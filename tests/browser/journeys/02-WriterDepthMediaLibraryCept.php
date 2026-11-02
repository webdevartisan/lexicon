<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('browse the media library as a grid or a list, sorted and searched');

$library = '/en/dashboard/blog/'.$I->recall('blogId').'/media';
$names = static fn (): array => $I->executeJS(
    "return Array.from(document.querySelectorAll('#media-items [data-media-name]')).map(function (el) { return el.textContent; });"
);

$I->signInAs('writer');
$first = $I->makeImage('walk-aaa-library');
$second = $I->makeImage('walk-zzz-library', '#8f3b5a');
$I->amOnPage($library);
$I->attachFile('#media-file-input', $first);
$I->waitForText("{$first} uploaded", 15);
$I->attachFile('#media-file-input', $second);
$I->waitForText("{$second} uploaded", 15);

// A file that isn't an image type is refused before it is sent, with no retry to offer
$notes = 'walk-notes-'.uniqid().'.txt';
file_put_contents(codecept_data_dir($notes), 'plain words');
$I->attachFile('#media-file-input', $notes);
$I->waitForText("{$notes} isn't a JPG, PNG, WEBP file.", 10, '#media-upload-feedback');
$I->dontSeeElement('#media-upload-feedback [data-retry]');
$I->click('#media-upload-feedback [data-dismiss]');
$I->dontSee($notes, '#media-upload-feedback');

// A file the server turns down can be tried again from its row
$broken = 'walk-broken-'.uniqid().'.png';
file_put_contents(codecept_data_dir($broken), 'not really a picture');
$I->attachFile('#media-file-input', $broken);
$I->waitForElementVisible('#media-upload-feedback [data-retry]', 15);
$I->see($broken, '#media-upload-feedback');
$I->click('#media-upload-feedback [data-retry]');
$I->waitForElementVisible('#media-upload-feedback [data-retry]', 15);
$I->seeNumberOfElements('#media-upload-feedback [data-retry]', 1);
$I->click('#media-upload-feedback [data-dismiss]');

// A slow upload shows its progress and can be stopped before it lands
$count = (int) $I->grabTextFrom('[data-media-count]');
// Noise does not compress, so this file is big enough to still be on its way when cancelled
$slow = 'walk-slow-'.uniqid().'.png';
$noise = imagecreatetruecolor(900, 600);
for ($y = 0; $y < 600; $y++) {
    for ($x = 0; $x < 900; $x++) {
        imagesetpixel($noise, $x, $y, random_int(0, 0xFFFFFF));
    }
}
imagepng($noise, codecept_data_dir($slow));
$I->onASlowConnection(50000);
$I->attachFile('#media-file-input', $slow);
$I->waitForElementVisible('#media-upload-feedback [role="progressbar"]', 10);
$I->seeThePageLooksRight('21-media-library-uploading');
$I->click('#media-upload-feedback [data-cancel]');
$I->waitForElementNotVisible('#media-upload-feedback [role="progressbar"]', 10);
$I->backOnAFastConnection();
$I->amOnPage($library);
$I->waitForElement('#media-items [data-media-id]', 10);
$I->dontSee($slow, '#media-items');
$I->assertSame($count, (int) $I->grabTextFrom('[data-media-count]'));

// A grid by default, with the count and the total size in the header
$I->seeElement('#media-items ul[data-media-grid] [data-media-id]');
$I->seeElement('[data-media-view="grid"][aria-pressed="true"]');
$I->assertGreaterThanOrEqual(2, (int) $I->grabTextFrom('[data-media-count]'));
$I->see('Total:', '[data-media-bytes]');

// The list view is one click away and is remembered after a reload
$I->click('[data-media-view="list"]');
$I->seeElement('#media-items ul[data-media-list] [data-media-id]');
$I->amOnPage($library);
$I->waitForElement('#media-items ul[data-media-list] [data-media-id]', 10);
$I->seeElement('[data-media-view="list"][aria-pressed="true"]');
$I->seeThePageLooksRight('21-media-library-list');

// Sorting by name runs both ways, checked on just the two images from this walk
$I->fillField('#media-q', pathinfo($first, PATHINFO_FILENAME));
$I->waitForJS("return document.querySelectorAll('#media-items [data-media-id]').length === 1;", 10);
$I->fillField('#media-q', '-library-');
$I->selectOption('#media-sort', 'name');
$I->waitForJS("return document.querySelector('#media-items [data-media-name]').textContent.indexOf('walk-zzz') === 0;", 10);
$I->click('#media-sort-dir');
$I->waitForJS("return document.querySelector('#media-items [data-media-name]').textContent.indexOf('walk-aaa') === 0;", 10);
$I->assertSame($names(), array_values(array_unique($names())));

// Ticking files shows how many and how big, select all takes every shown file
$I->waitForJS("return document.querySelectorAll('#media-items [data-media-id]').length >= 2;", 10);
$shown = (int) $I->executeJS("return document.querySelectorAll('#media-items [data-media-id]').length;");
$I->see("0/{$shown} selected", '[data-media-selection]');
$I->seeElement('[data-media-delete-selected][disabled]');
$I->click('#media-items [data-media-id]:first-child [data-media-select]');
$I->see("1/{$shown} selected", '[data-media-selection]');
$I->seeElement('#media-items [data-media-id][data-selected]');
$I->assertTrue($I->executeJS("return document.querySelector('[data-media-select-all]').indeterminate;"));
$I->click('[data-media-select-all]');
$I->see("{$shown}/{$shown} selected", '[data-media-selection]');

// The selection survives a switch of view
$I->click('[data-media-view="grid"]');
$I->see("{$shown}/{$shown} selected", '[data-media-selection]');
$I->seeNumberOfElements('#media-items [data-media-select]:checked', $shown);
$I->seeThePageLooksRight('21-media-library-selected');
$I->click('[data-media-view="list"]');

// Cancelling a bulk delete keeps everything, confirming removes the files and the count
$before = (int) $I->grabTextFrom('[data-media-count]');
$I->click('[data-media-delete-selected]');
$I->waitForElementVisible('#confirmModalDanger', 10);
$I->see("({$shown})", '#confirmModalDanger');
$I->click('#confirmModalDanger [data-modal-cancel]');
$I->waitForElementNotVisible('#confirmModalDanger');
$I->seeNumberOfElements('#media-items [data-media-id]', $shown);
$I->click('[data-media-delete-selected]');
$I->waitForElementVisible('#confirmModalDanger', 10);
$I->click('#confirmModalDanger [data-modal-confirm]');
$I->waitForText("Images deleted: {$shown}", 15, '[data-media-status]');
$I->dontSeeElement('#media-items [data-media-id]');
$I->assertSame($before - $shown, (int) $I->grabTextFrom('[data-media-count]'));

// A search with no hits says so
$I->fillField('#media-q', 'nothing-is-called-this');
$I->waitForText('No images match', 10, '#media-empty');
$I->fillField('#media-q', 'walk-');
$I->waitForElementNotVisible('#media-empty', 10);

// Rescan lives in the toolbar menu and answers on the page
$I->click('[data-media-library] [data-row-actions-toggle]');
$I->waitForElementVisible('[data-media-rescan]');
$I->click('[data-media-rescan]');
$I->waitForText('already in the library', 15, '[data-media-status]');

// Neither view runs off the side of a phone
$I->amOnAPhone();
$I->amOnPage($library);
$I->waitForElement('#media-items [data-media-id]', 10);
$I->seeThePageLooksRight('21-media-library-list-phone');
$I->click('[data-media-view="grid"]');
$I->seeThePageLooksRight('21-media-library-grid-phone');
$I->putThePhoneAway();

// Leave the library as the next walker expects it
$I->click('[data-media-view="grid"]');
