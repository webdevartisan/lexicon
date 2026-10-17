<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('let search engines and feed readers follow a blog and its posts');

// Not built yet: nothing outputs JSON-LD or a feed link. Delete this line once it does.
$scenario->incomplete('No feed link or JSON-LD yet');

$I->amAGuest();
$I->amOnPage('/en/blog/'.$I->recall('blogSlug'));
$I->seeElementInDOM('link[type="application/rss+xml"], link[type="application/atom+xml"]');
$I->amOnPage($I->recall('postUrl'));
$I->seeElementInDOM('script[type="application/ld+json"]');
