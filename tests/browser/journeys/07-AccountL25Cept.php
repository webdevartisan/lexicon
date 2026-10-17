<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('see the Security page fit the screen');

$I->signInAs('reader');
$I->amOnPage('/en/account/security');
$I->seeThePageLooksRight('67-security-page');
