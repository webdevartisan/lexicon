<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('sign up, start a blog, publish a first post, and take the blog offline and back');

$stamp = time();
$email = "walk-writer-{$stamp}@example.test";
$password = "Walk-{$stamp}-7!";

// A visitor signs up from the homepage
$I->amOnPage('/en');
$I->declineCookies();
$I->click('Create a Blog');
$I->waitForElementVisible('#email');
$I->seeThePageLooksRight('01-sign-up');
$I->fillField('Email address', $email);
$I->fillField('Password', $password);
$I->checkOption('age_confirmed');
$I->waitForTheSignUpCheck();
$I->click('Create account');
$I->waitForText('Welcome!', 15);
$I->dontSeeInCurrentUrl('/register');
$I->dontSee('Too many accounts were created');
$I->seeThePageLooksRight('02-signed-up');
$I->saveSessionSnapshot('writer');

// The writer creates a blog
$I->amOnPage('/en/dashboard');
$I->click('Create your blog');
$I->seeThePageLooksRight('03-new-blog');
$I->fillField('name', "Walkthrough {$stamp}");
$I->waitForJS("return document.querySelector('input[name=slug]').value !== ''");
$blogSlug = $I->grabValueFrom('slug');
$I->click('form[action*="blog/create"] button[type=submit]');
$I->waitForText('Blog created');
$blogId = $I->grabFromCurrentUrl('~/blog/(\d+)~');
$I->waitForJS('return !!window.Alpine');
$I->seeThePageLooksRight('04-blog-created');

// An empty post can't be published
$I->amOnPage('/en/dashboard/post/new');
$I->waitForElement('#content_ifr', 15);
$I->see('Publishing also puts your blog online.', '[data-visibility-line]');
$I->click('Publish');
$I->waitForText('Title is required');
$I->seeInCurrentUrl('/post/new');
$I->click('[data-close]');
$I->waitForElementNotVisible('[data-close]');

// The first post takes the blog live
$I->fillField('title', 'Hello from the walkthrough');
$I->waitForJS("return document.querySelector('input[name=slug]').value !== ''");
$postSlug = $I->grabValueFrom('slug');
$I->scrollDownTo('#content_ifr');
$I->switchToIFrame('#content_ifr');
$I->click('#tinymce p');
$I->type('This is my first post.');
$I->switchToIFrame();
$I->click('Publish');
$I->waitForText('Your blog is live now');
$I->seeCurrentUrlMatches('~/post/\d+~');
$I->see('Anyone can read this post.', '[data-visibility-line]');
$I->seeThePageLooksRight('05-first-post');
$postEditUrl = $I->grabFromCurrentUrl();
$postUrl = "/en/blog/{$blogSlug}/{$postSlug}";

// A guest can read it
$I->amAGuest();
$I->amOnPage($postUrl);
$I->dontSeeInTitle('Page not found');
$I->waitForText('This is my first post.');
$I->seeThePageLooksRight('06-guest-reads-post');

// The writer takes the blog offline and keeps editing
$I->loadSessionSnapshot('writer');
$I->amOnPage("/en/dashboard/blog/{$blogId}/settings");
$I->click('summary[title="More actions"]');
$I->click('Unpublish');
$I->waitForText('Blog updated');
$I->see('Your blog is offline.', '[data-visibility-line]');
$I->seeThePageLooksRight('07-blog-offline');
$I->amOnPage($postEditUrl);
$I->fillField('title', 'Hello again from the walkthrough');
$I->click('Update');
$I->waitForText('Post updated');
$I->see('so only you and your team can see this post', '[data-visibility-line]');
$I->seeThePageLooksRight('08-post-updated');
$I->resizeWindow(390, 844);
$I->seeThePageLooksRight('08-post-updated-phone');
// The docked bar grows with the visibility line, so the form's last field must still clear it
$clearance = $I->executeJS(<<<'JS'
    window.scrollTo(0, document.documentElement.scrollHeight);
    const grid = document.querySelector('form[data-dropzone-form] .grid');
    const contentBottom = grid.getBoundingClientRect().bottom - parseFloat(getComputedStyle(grid).paddingBottom);
    return Math.round(document.querySelector('[data-actionbar]').getBoundingClientRect().top - contentBottom);
    JS);
$I->assertGreaterThanOrEqual(0, $clearance, "The docked bar covers the end of the form by {$clearance}px");
$I->resizeWindow(1280, 900);
$I->amOnPage('/en/dashboard/post');
$I->see('Your blog is offline.', '[data-visibility-line]');
$I->seeThePageLooksRight('08-post-list-offline');

// Guests can't see it while it's offline, the writer still can
$I->amAGuest();
$I->amOnPage($postUrl);
$I->seeInTitle('Page not found');
$I->loadSessionSnapshot('writer');
$I->amOnPage($postUrl);
$I->dontSeeInTitle('Page not found');

// Back online from the editor's line, guests can read it again
$I->amOnPage($postEditUrl);
$I->click('Put your blog online');
$I->waitForText('Anyone can read your published posts.');
$I->see('Anyone can read this post.', '[data-visibility-line]');
$I->seeInCurrentUrl('/edit');
$I->amAGuest();
$I->amOnPage($postUrl);
$I->dontSeeInTitle('Page not found');
$I->amOnPage("/en/blog/{$blogSlug}");
$I->seeThePageLooksRight('09-blog-back-online');

// Later journeys use this writer, blog and post
$I->remember('writerEmail', $email);
$I->remember('writerPassword', $password);
$I->remember('blogId', $blogId);
$I->remember('blogSlug', $blogSlug);
$I->remember('postEditUrl', $postEditUrl);
$I->remember('blogName', "Walkthrough {$stamp}");
$I->remember('postUrl', $postUrl);
