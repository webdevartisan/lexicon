<?php

declare(strict_types=1);

use Facebook\WebDriver\WebDriverKeys;
use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('schedule a post for the next minute and see it go live once the scheduler runs');

$blogSlug = $I->recall('blogSlug');
$title = 'Live on schedule '.date('His');

// Scheduled for the start of the next minute
$I->signInAs('writer');
$I->amOnPage('/en/dashboard/post/new');
$I->waitForElement('#content_ifr', 15);
$I->fillField('title', $title);
$I->waitForJS("return document.querySelector('input[name=slug]').value !== ''");
$slug = $I->grabValueFrom('slug');
$I->scrollDownTo('#content_ifr');
$I->switchToIFrame('#content_ifr');
$I->click('#tinymce p');
$I->type('This one waits for the scheduler.');
$I->switchToIFrame();
// The side column sticks while scrolling, so its lower half only shows at the end of the page
$I->pressKey('body', WebDriverKeys::END);
$I->wait(1);
$due = (int) $I->executeJS(<<<'JS'
    const when = new Date(Date.now() + 60000);
    when.setSeconds(0, 0);
    document.querySelector('#published_at')._flatpickr.setDate(when, true);
    return when.getTime() / 1000;
JS);
$I->click('Schedule');
$I->waitForText('Post saved');
$I->seeThePageLooksRight('schedule-saved');

// Until its time comes, readers cannot see it, even after the scheduler has looked
$I->amAGuest();
$I->amOnPage("/en/blog/{$blogSlug}/{$slug}");
$I->seeInTitle('Page not found');
$I->assertStringContainsString('No posts due', $I->runConsoleCommand('posts:publish-due'));

// Once the minute has passed the scheduler publishes it. A machine with its own cron has often done it by then,
// so the command here only makes sure the sweep ran and the check is on what readers see
$I->wait(max(0, $due - time()) + 2);
$I->runConsoleCommand('posts:publish-due');
$I->amOnPage("/en/blog/{$blogSlug}/{$slug}");
$I->dontSeeInTitle('Page not found');
$I->see($title);
$I->seeThePageLooksRight('schedule-live');
