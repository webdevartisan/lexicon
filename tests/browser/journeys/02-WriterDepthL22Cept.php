<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('see images inside a post load wherever the post is shown');

$I->amAGuest();
$I->amOnPage($I->recall('postUrl'));
$I->seeElementInDOM('main img');
$I->dontSeeElementInDOM('main img[src^=".."]');
