<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('be told plainly that a deleted account no longer exists');

$I->amAGuest();
$I->amOnPage('/en/login');
$I->declineCookies();
$I->fillField('Email address', $I->recall('deletedEmail'));
$I->fillField('Password', $I->recall('deletedPassword'));
$I->waitForTheSignUpCheck();
$I->click('Sign in', 'form[action="/login"]');
$I->waitForElementVisible('[role=alert], .lx-field-error, .invalid-feedback, [data-error]');
$I->dontSee('suspended');
