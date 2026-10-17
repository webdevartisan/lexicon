<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('be told plainly that a deleted account no longer exists');

// Known bug L26: a deleted account is told it is suspended. Delete this line once it is fixed.
$scenario->incomplete('L26: a deleted account is told it is suspended');

$I->amAGuest();
$I->amOnPage('/en/login');
$I->declineCookies();
$I->fillField('Email address', $I->recall('deletedEmail'));
$I->fillField('Password', $I->recall('deletedPassword'));
$I->waitForTheSignUpCheck();
$I->click('Log in', 'form[action="/login"]');
$I->waitForElementVisible('[role=alert], .lx-field-error, .invalid-feedback, [data-error]');
$I->dontSee('suspended');
