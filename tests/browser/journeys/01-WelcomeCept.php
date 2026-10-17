<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('sign up from the sign-up page, get asked what I came for, and choose reading');

$stamp = time();

// Signing up without having clicked "Create a Blog" asks what they'd like to do
$I->amAGuest();
$I->amOnPage('/en/register');
$I->declineCookies();
$I->waitForElementVisible('#email');
$I->fillField('Email address', "walk-welcome-{$stamp}@example.test");
$I->fillField('Password', "Walk-{$stamp}-7!");
$I->checkOption('age_confirmed');
$I->waitForTheSignUpCheck();
$I->click('Create account');
$I->waitForText('What would you like to do?', 15);
$I->seeInCurrentUrl('/welcome');
$I->see('Start a blog', '[data-welcome-choice=start]');
$I->see('Read and follow blogs', '[data-welcome-choice=read]');
$I->seeThePageLooksRight('01w-welcome');
$I->resizeWindow(390, 844);
$I->seeThePageLooksRight('01w-welcome-phone');
$I->resizeWindow(1280, 900);

// Choosing to read lands on the reading hub with its welcome note
$I->click('[data-welcome-choice=read]');
$I->seeInCurrentUrl('welcome=1');
$I->seeThePageLooksRight('01w-reading-hub');
