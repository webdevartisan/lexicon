<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('put the same image into the media library twice');

// Known bug L21: the second copy fails with "Upload failed". Delete this line once it is fixed.
$scenario->incomplete('L21: the same image twice fails');

$I->signInAs('writer');
$image = $I->makeImage('walk-twice');
$I->amOnPage('/en/dashboard/blog/'.$I->recall('blogId').'/media');
$I->attachFile('#media-uploader-input', $image);
$I->waitForText("{$image} uploaded", 15);
$I->attachFile('#media-uploader-input', $image);
$I->wait(3);
$I->dontSee('Upload failed');
$I->seeNumberOfElements('#media-upload-feedback .text-emerald-600', 2);
