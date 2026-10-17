<?php

declare(strict_types=1);

use Facebook\WebDriver\WebDriverKeys;
use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('use categories, tags and images, change post states, schedule and delete posts, and switch themes');

$blog = '/en/dashboard/blog/'.$I->recall('blogId');
$blogSlug = $I->recall('blogSlug');
$postUrl = $I->recall('postUrl');
$postEditUrl = $I->recall('postEditUrl');

$I->signInAs('writer');

// A category and a tag
$I->amOnPage("{$blog}/categories");
$I->fillField('input[placeholder^="New category"]', 'Field notes');
$I->pressKey('input[placeholder^="New category"]', WebDriverKeys::ENTER);
$I->waitForText('Category added');
$I->fillField('input[placeholder^="New tag"]', 'walks');
$I->pressKey('input[placeholder^="New tag"]', WebDriverKeys::ENTER);
$I->waitForText('Tag added');
$I->seeThePageLooksRight('20-category-and-tag');

// An image in the media library
$inline = $I->makeImage('walk-inline', '#8f5a3b');
$I->amOnPage("{$blog}/media");
$I->attachFile('#media-uploader-input', $inline);
$I->waitForText("{$inline} uploaded", 15);
$I->dontSee('Upload failed');
$I->seeThePageLooksRight('21-media-library');

// The post gets a cover image, the category, the tag and the library image
$I->amOnPage($postEditUrl);
$I->waitForElement('#content_ifr', 15);
$I->attachFile('input.dz-hidden-input', $I->makeImage('walk-cover'));
$I->selectOption('category_id', 'Field notes');
$I->fillField('#tagInput', 'walks');
$I->pressKey('#tagInput', WebDriverKeys::ENTER);
// At this width the image button sits in the toolbar's overflow menu
$I->scrollDownTo('button[aria-label="Reveal or hide additional toolbar items"]');
$I->click('button[aria-label="Reveal or hide additional toolbar items"]');
$I->click('button[aria-label="Insert image from library"]');
$I->waitForElementVisible('[data-picker-grid] img');
$I->click('[data-picker-grid] img');
$I->switchToIFrame('#content_ifr');
$I->seeElement('img');
$I->switchToIFrame();
$I->click('Update');
$I->waitForText('Post updated');
$I->seeThePageLooksRight('22-post-with-images');

// Readers see the category, the tag and the images, and the links work
$I->amAGuest();
$I->amOnPage($postUrl);
$I->seeElementInDOM("a[href$='/category/field-notes']");
$I->seeElementInDOM("a[href$='/tag/walks']");
$I->seeElementInDOM('main img');
$I->seeThePageLooksRight('23-guest-sees-images');
$I->amOnPage("/en/blog/{$blogSlug}/category/field-notes");
$I->dontSeeInTitle('Page not found');
$I->seeElementInDOM("a[href$='/hello-from-the-walkthrough']");
$I->amOnPage("/en/blog/{$blogSlug}/tag/walks");
$I->dontSeeInTitle('Page not found');
$I->seeThePageLooksRight('24-tag-page');

// Unpublishing takes the post away from readers
$I->signInAs('writer');
$I->amOnPage($postEditUrl);
$I->click('summary[title="More actions"]');
$I->click('button[name=intent][value=unpublish]');
$I->waitForText('Post updated');
$I->see('Draft');
$I->seeThePageLooksRight('25-post-unpublished');
$I->amAGuest();
$I->amOnPage($postUrl);
$I->seeInTitle('Page not found');

// Publishing again brings it back
$I->signInAs('writer');
$I->amOnPage($postEditUrl);
$I->click('Publish');
$I->waitForText('Post updated');
$I->see('Published');
$I->amAGuest();
$I->amOnPage($postUrl);
$I->dontSeeInTitle('Page not found');

// Archiving takes it away too, and publishing from the menu brings it back
$I->signInAs('writer');
$I->amOnPage($postEditUrl);
$I->click('summary[title="More actions"]');
$I->click('button[name=intent][value=archive]');
$I->waitForText('Post updated');
$I->see('Archived');
$I->seeThePageLooksRight('26-post-archived');
$I->amAGuest();
$I->amOnPage($postUrl);
$I->seeInTitle('Page not found');
$I->signInAs('writer');
$I->amOnPage($postEditUrl);
$I->click('summary[title="More actions"]');
$I->click('Publish', '[role=menu]');
$I->waitForText('Post updated');
$I->see('Published');
$I->amAGuest();
$I->amOnPage($postUrl);
$I->dontSeeInTitle('Page not found');

// A post scheduled for tomorrow stays hidden
$I->signInAs('writer');
$I->amOnPage('/en/dashboard/post');
$I->seeThePageLooksRight('27-posts-list');
$I->amOnPage('/en/dashboard/post/new');
$I->waitForElement('#content_ifr', 15);
$I->fillField('title', 'A post for tomorrow');
$I->waitForJS("return document.querySelector('input[name=slug]').value !== ''");
$scheduledSlug = $I->grabValueFrom('slug');
$I->scrollDownTo('#content_ifr');
$I->switchToIFrame('#content_ifr');
$I->click('#tinymce p');
$I->type('Scheduled from the walkthrough.');
$I->switchToIFrame();
// The side column sticks while scrolling, so its lower half only shows at the end of the page
$I->pressKey('body', WebDriverKeys::END);
$I->wait(1);
$I->click('#published_at');
$I->click('.flatpickr-calendar.open [aria-label="'.date('F j, Y', strtotime('+1 day')).'"]');
$I->pressKey('#published_at', WebDriverKeys::ESCAPE);
$I->click('Schedule');
$I->waitForText('Post saved');
$I->seeThePageLooksRight('28-post-scheduled');
$I->amAGuest();
$I->amOnPage("/en/blog/{$blogSlug}/{$scheduledSlug}");
$I->seeInTitle('Page not found');

// A draft can be deleted
$I->signInAs('writer');
$I->amOnPage('/en/dashboard/post/new');
$I->waitForElement('#content_ifr', 15);
$I->fillField('title', 'A post to delete');
$I->scrollDownTo('#content_ifr');
$I->switchToIFrame('#content_ifr');
$I->click('#tinymce p');
$I->type('Short-lived.');
$I->switchToIFrame();
$I->click('Save draft');
$I->waitForText('Post saved');
$draftUrl = $I->grabFromCurrentUrl();
$I->pressKey('body', WebDriverKeys::END);
$I->wait(1);
$I->click('Delete post');
$I->waitForText('Are you sure?');
$I->click('Yes, Continue');
$I->waitForText('Post deleted');
$I->seeThePageLooksRight('29-draft-deleted');
$I->amOnPage($draftUrl);
$I->seeInTitle('not found');

// Trying and switching themes
$I->amOnPage("/en/blog/{$blogSlug}?_theme=nocturne");
$I->see($I->recall('blogName'));
$I->amOnPage("{$blog}/appearance");
$I->scrollDownTo('//form[input[@value="nocturne"]]//button');
$I->click('//form[input[@value="nocturne"]]//button');
$I->waitForText('Nocturne is now the active theme');
$I->see('Nocturne', '.theme-card.is-active');
$I->amAGuest();
$I->amOnPage("/en/blog/{$blogSlug}");
$I->seeThePageLooksRight('30-blog-in-nocturne');
$I->signInAs('writer');
$I->amOnPage("{$blog}/appearance");
$I->click('Branding & texts');
$I->seeThePageLooksRight('31-branding-and-texts');
$I->amOnPage("{$blog}/appearance");
$I->scrollDownTo('//form[input[@value="folio"]]//button');
$I->click('//form[input[@value="folio"]]//button');
$I->waitForText('Folio is now the active theme');

// The other settings tabs and the rest of the blog menu open
$I->amOnPage("{$blog}/settings");
$I->click('SEO', '[role=tablist]');
$I->seeThePageLooksRight('32-settings-seo');
$I->click('Discussion & Workflow', '[role=tablist]');
$I->seeThePageLooksRight('33-settings-discussion');
foreach (['subscribers', 'insights', 'backup', 'comments'] as $area) {
    $I->amOnPage("{$blog}/{$area}");
    $I->dontSeeInTitle('not found');
    $I->seeThePageLooksRight("34-{$area}");
}
