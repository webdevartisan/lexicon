<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('see who subscribed to the blog, find them, and remove them');

$blog = '/en/dashboard/blog/'.$I->recall('blogId');
$blogSlug = $I->recall('blogSlug');
$readerEmail = $I->recall('readerEmail');
$guestEmail = 'walk-guest-'.uniqid().'@example.test';

// A guest asks to subscribe, but only the link in their inbox makes it count
$I->amAGuest();
$I->amOnPage("/en/blog/{$blogSlug}");
$I->declineCookies();
$I->scrollDownTo('#newsletter');
$I->fillField('Your address', $guestEmail);
$I->click('Subscribe', 'form[action$="/subscribe"]');
$I->waitForText('Follow the link we sent to your inbox');
$I->seeThePageLooksRight('subscribers-guest-asked');

// The writer sees the reader who subscribed, not the guest who has not confirmed
$I->signInAs('writer');
$I->amOnPage("{$blog}/subscribers");
$I->see($readerEmail);
$I->dontSee($guestEmail);
$I->seeThePageLooksRight('subscribers-list');

// Searching narrows the list
$I->fillField('q', 'nobody-has-this-address');
$I->click('Search');
$I->waitForText('No subscribers match');
$I->seeThePageLooksRight('subscribers-no-match');
$I->fillField('q', $readerEmail);
$I->click('Search');
$I->waitForText($readerEmail);
$I->dontSee('No subscribers match');

// Removing a subscriber asks first, then takes them off the list
$I->click('Remove');
$I->acceptConfirm("Remove {$readerEmail} from the subscriber list?");
$I->waitForText('Subscriber removed');
$I->dontSee($readerEmail, 'table');
$I->seeThePageLooksRight('subscribers-removed');
