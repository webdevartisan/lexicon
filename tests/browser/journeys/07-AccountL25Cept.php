<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('see the Security page fit the screen');

// Known bug L25: the Security page scrolls sideways. Delete this line once it is fixed.
$scenario->incomplete('L25: the Security page scrolls sideways');

$I->signInAs('reader');
$I->amOnPage('/en/account/security');
$I->seeThePageLooksRight('67-security-page');
