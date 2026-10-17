<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('see images inside a post load wherever the post is shown');

// Known bug L22: images placed inside a post are saved as ../../../../uploads/...
// Delete this line once it is fixed.
$scenario->incomplete('L22: relative image addresses inside posts');

$I->amAGuest();
$I->amOnPage($I->recall('postUrl'));
$I->seeElementInDOM('main img');
$I->dontSeeElementInDOM('main img[src^=".."]');
