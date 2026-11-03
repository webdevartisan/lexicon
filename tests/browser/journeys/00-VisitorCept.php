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
$I->seeElement('#discover-heading');
$I->seeElement('.lx-explore-tab.active[href$="/discover"]');
$I->seeThePageLooksRight('visitor-discover');

// A search opens straight on the results, with the search box still at the top and nothing featured above it
$I->fillField('#discover-q', 'a');
$I->click('.lx-discover-search button[type=submit]');
$I->seeInCurrentUrl('q=a');
$I->seeInField('#discover-q', 'a');
$I->dontSeeElement('[data-slider]');
$I->seeThePageLooksRight('visitor-discover-search');

// The Blogs tab works the same way, and its search stays on blogs
$I->click('.lx-explore-tab[href*="tab=blogs"]');
$I->seeInCurrentUrl('tab=blogs');
$I->seeInField('#discover-q', 'a');
$I->dontSeeElement('.lx-discover-featured');
$I->amOnPage('/en/discover?tab=blogs');
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
$I->see('Replies', '.lx-contact-facts');

// A sent message swaps the form for a confirmation, once. The hidden field takes the
// silent path that answers like a real send without queueing mail.
$I->fillField('#contact-name', 'Walk Visitor');
$I->fillField('#contact-email', 'walk-visitor@example.test');
$I->fillField('#contact-subject', 'Hello there');
$I->fillField('#contact-message', 'Just checking the contact page.');
$I->executeJS("document.querySelector('.lx-hp input').value = 'filled';");
$I->click('main form button[type=submit]');
$I->waitForText('Message sent', 10, '.lx-contact-sent');
$I->dontSeeElement('main form[action$="/contact"]');
$I->seeThePageLooksRight('visitor-contact-sent');
$I->reloadPage();
$I->seeElement('main form[action$="/contact"]');
