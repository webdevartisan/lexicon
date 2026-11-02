<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('start a first blog in the site modal from the main site and land in the editor');

$stamp = time();
$email = "walk-modal-{$stamp}@example.test";
$password = "Walk-{$stamp}-7!";

$I->amOnPage('/en');
$I->declineCookies();
$I->click('Create your blog');
$I->waitForElementVisible('#email');
$I->fillField('Email address', $email);
$I->fillField('Password', $password);
$I->checkOption('age_confirmed');
$I->waitForTheSignUpCheck();
$I->click('Create account');
$I->waitForText('Check your inbox', 15);
$I->followTheConfirmLinkMailedTo($email);

// The link opens the first step as a page of the main site, with no dashboard sidebar
$I->waitForText('What will you call your blog?', 15);
$I->seeInCurrentUrl('/en/start');
$I->dontSeeElement('.app-menu');

// The home banner and the phone menu open the steps in the modal too
$I->amOnPage('/en');
$I->click('Create your blog');
$I->waitForText('What will you call your blog?', 10, '[data-panel-dialog]');
$I->seeInCurrentUrl('/en');
$I->amOnAPhone();
$I->amOnPage('/en');
$I->click('[data-lx-drawer-open]');
$I->waitForElementVisible('#lx-drawer');
$I->click('Create a blog', '.lx-drawer-links');
$I->waitForText('What will you call your blog?', 10, '[data-panel-dialog]');
$I->seeThePageLooksRight('writer-modal-phone-menu');
$I->putThePhoneAway();

// From the main site the same steps open in the modal
$I->amOnPage('/en');
$I->click('Create a blog', '.lx-nav-actions');
$I->waitForText('What will you call your blog?', 10, '[data-panel-dialog]');
$I->see('Step 1 of 3', '[data-panel-dialog]');
$I->fillField('[data-panel-dialog] #name', "Modal walk {$stamp}");
$I->fillField('[data-panel-dialog] #first_name', 'Mona');
$I->fillField('[data-panel-dialog] #last_name', 'Modal');
$I->waitForText("/blog/modal-walk-{$stamp}", 5, '[data-panel-dialog] [data-address]');
$I->click('[data-panel-dialog] [data-address-change] summary');
$I->waitForText('This address is available.', 10, '[data-panel-dialog]');
$I->makeScreenshot('writer-modal-name');
$I->click('Continue', '[data-panel-dialog]');

$I->waitForText("What's it about?", 10, '[data-panel-dialog]');
$I->see('Step 2 of 3', '[data-panel-dialog]');
$I->seeInCurrentUrl('/en');
$I->dontSeeInCurrentUrl('/start');
$I->fillField('[data-panel-dialog] #description', 'A blog started in the modal.');
$I->click('Continue', '[data-panel-dialog]');

$I->waitForText('Pick a look.', 10, '[data-panel-dialog]');
$I->see('Step 3 of 3', '[data-panel-dialog]');
$I->click('[data-panel-dialog] [data-theme-card=offset]');
$I->seeCheckboxIsChecked('[data-panel-dialog] #theme-offset');
$I->makeScreenshot('writer-modal-look');
$I->click('Continue', '[data-panel-dialog]');

// The last step leaves the modal for the editor
$I->waitForElement('#content_ifr', 15);
$I->seeInCurrentUrl('/post/new?blog_id=');

// The footer link opens the same steps in the modal
$I->amOnPage('/en/discover');
$I->scrollDownTo('.lx-foot');
$I->click('Create a blog', '.lx-foot');
$I->waitForText('What will you call your blog?', 10, '[data-panel-dialog]');
$I->seeInCurrentUrl('/en/discover');

// So does the button on My blogs
$I->amOnPage('/en/dashboard/blog');
$I->click('a[data-panel=open]');
$I->waitForText('What will you call your blog?', 10, '[data-panel-dialog]');
$I->seeInCurrentUrl('/en/dashboard/blog');
$I->seeThePageLooksRight('writer-modal-my-blogs');
