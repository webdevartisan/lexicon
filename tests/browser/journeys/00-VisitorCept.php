<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('look around without signing in: find a blog, read a post, open the information pages');

// The homepage invites people to start a blog
$I->amAGuest();
$I->amOnPage('/en');
$I->seeThePageLooksRight('visitor-homepage-with-banner');
$I->declineCookies();
$I->seeElementInDOM('a[href*="/register?return_to="]');
$I->seeThePageLooksRight('visitor-homepage');

// A visitor finds a blog and reads a post
$I->amOnPage('/en/discover');
$I->seeInTitle('Discover');
$I->seeThePageLooksRight('visitor-discover');
// The first link to a blog's front page, not to one of its posts
$I->amOnPage($I->grabAttributeFrom("//main//a[contains(@href, '/blog/') and not(contains(substring-after(@href, '/blog/'), '/'))]", 'href'));
$I->seeThePageLooksRight('visitor-a-blog');
$blogPath = $I->grabFromCurrentUrl();
$I->amOnPage($I->grabAttributeFrom(
    "//a[starts-with(@href, '{$blogPath}/') and not(contains(@href, '/category/')) and not(contains(@href, '/tag/'))"
    ." and not(contains(@href, '/archive')) and not(contains(@href, '#'))]",
    'href'
));
$I->seeElementInDOM('#comment_content');
$I->seeThePageLooksRight('visitor-a-post');

// The information pages open
foreach (['getting-started' => 'Getting started', 'about' => 'About Lexicon', 'contact' => 'Contact us', 'privacy' => 'Privacy Policy', 'terms' => 'Terms of Service'] as $page => $heading) {
    $I->amOnPage("/en/{$page}");
    $I->dontSeeInTitle('Page not found');
    $I->seeInSource($heading);
    $I->seeThePageLooksRight("visitor-{$page}");
}

// A page that does not exist says so
$I->amOnPage('/en/no-such-page');
$I->seeInTitle('Page not found');
$I->seeThePageLooksRight('visitor-not-found');

// The contact form will not send empty
$I->amOnPage('/en/contact');
$I->click('main form button[type=submit]');
$I->seeInCurrentUrl('/contact');
$I->seeElementInDOM('main form :invalid');
$I->seeThePageLooksRight('visitor-contact-empty');
