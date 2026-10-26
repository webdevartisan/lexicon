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
foreach (['category' => 'Field notes', 'tag' => 'walks'] as $type => $name) {
    $I->click("button[data-taxonomy=\"add\"][data-type=\"{$type}\"]");
    $I->waitForElementVisible('#taxonomyModal input[name="name"]');
    $I->fillField('#taxonomyModal input[name="name"]', $name);
    $I->pressKey('#taxonomyModal input[name="name"]', WebDriverKeys::ENTER);
    $I->waitForText(ucfirst($type).' added');
}
$I->seeThePageLooksRight('20-category-and-tag');

// An image in the media library
$inline = $I->makeImage('walk-inline', '#8f5a3b');
$I->amOnPage("{$blog}/media");
$I->openTheMediaUploader();
$I->attachFile('#media-uploader-input', $inline);
$I->waitForText("{$inline} uploaded", 15);
$I->dontSee('Upload failed');
$I->seeThePageLooksRight('21-media-library');

// The post gets a cover image, the category, the tag and the library image
$I->amOnPage($postEditUrl);
$I->waitForElement('#content_ifr', 15);
$I->attachFile('input.dz-hidden-input', $I->makeImage('walk-cover'));
$I->chooseFromMenu('#category_id', 'Field notes');
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

// A pasted image uploads too, though autosave leaves this live post alone
$I->dontSeeElement('form[data-autosave-form]');
$I->executeJS(<<<'JS'
    var canvas = document.createElement('canvas');
    canvas.width = canvas.height = 40;
    canvas.getContext('2d').fillRect(0, 0, 40, 40);
    var editor = tinymce.get('content');
    editor.insertContent('<img id="walk-pasted" src="' + canvas.toDataURL('image/png') + '">');
    editor.uploadImages();
JS);
$I->switchToIFrame('#content_ifr');
$I->waitForElement('#walk-pasted[src*="/uploads/"]', 15);
$I->switchToIFrame();
$I->click('Update');
$I->waitForText('Post updated');
$I->seeThePageLooksRight('22-post-with-images');

// Switching light and dark is not an edit, and words typed before a switch stay
$editorReady = "return !!(tinymce.get('content') && tinymce.get('content').initialized);";
$switchMode = "var root = document.documentElement; root.setAttribute('data-mode', root.getAttribute('data-mode') === 'dark' ? 'light' : 'dark');";
$wouldWarn = "var e = new Event('beforeunload', {cancelable: true}); window.dispatchEvent(e); return e.defaultPrevented;";
$I->waitForJS($editorReady, 15);
$I->executeJS($switchMode);
$I->waitForJS($editorReady, 15);
$I->assertFalse($I->executeJS($wouldWarn));
$I->executeJS("var ed = tinymce.get('content'); ed.focus(); ed.selection.select(ed.getBody(), true); ed.selection.collapse(false);");
$I->switchToIFrame('#content_ifr');
$I->type(' Typed before a switch.');
$I->switchToIFrame();
$I->executeJS($switchMode);
$I->waitForJS($editorReady, 15);
$I->assertTrue($I->executeJS($wouldWarn));
$I->assertStringContainsString('Typed before a switch.', $I->executeJS("return tinymce.get('content').getContent();"));

// Words that arrive without a key press, the way dictation sends them, are an edit too
$I->amOnPage($postEditUrl);
$I->waitForJS($editorReady, 15);
$I->assertFalse($I->executeJS($wouldWarn));
$I->executeJS("var ed = tinymce.get('content'); ed.selection.select(ed.getBody(), true); ed.selection.collapse(false); ed.getDoc().execCommand('insertText', false, ' Dictated, not typed.');");
$I->assertTrue($I->executeJS($wouldWarn));

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

// Archiving takes it away too. It comes back as a draft first, then publishing brings it back
$I->signInAs('writer');
$I->amOnPage($postEditUrl);
$I->click('summary[title="More actions"]');
$I->click('button[name=intent][value=archive]');
$I->waitForText('Post updated');
$I->see('Archived');
$I->see('Restore as draft', '[data-actionbar-primary]');
$I->seeThePageLooksRight('26-post-archived');
$I->amAGuest();
$I->amOnPage($postUrl);
$I->seeInTitle('Page not found');
$I->signInAs('writer');
$I->amOnPage($postEditUrl);
$I->click('[data-actionbar-primary]');
$I->waitForText('Post updated');
$I->see('Draft');
$I->click('Publish');
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
// A stamp keeps the slug free when the journey runs again on the same blog
$I->fillField('title', 'A post for tomorrow '.date('His'));
$I->waitForJS("return document.querySelector('input[name=slug]').value !== ''");
$scheduledSlug = $I->grabValueFrom('slug');
$I->writeInTheEmptyEditor('Scheduled from the walkthrough.');
// The side column sticks while scrolling, so its lower half only shows at the end of the page
$I->pressKey('body', WebDriverKeys::END);
$I->wait(1);
$I->click('#published_at-shown');
$I->click('.flatpickr-calendar.open [aria-label="'.date('F j, Y', strtotime('+1 day')).'"]');
$I->pressKey('#published_at-shown', WebDriverKeys::ESCAPE);
$I->click('Schedule');
$I->waitForText('Post saved');
$scheduledEditUrl = $I->grabFromCurrentUrl();
$I->seeThePageLooksRight('28-post-scheduled');
$I->amAGuest();
$I->amOnPage("/en/blog/{$blogSlug}/{$scheduledSlug}");
$I->seeInTitle('Page not found');

// Its main button updates the schedule, and clearing the date offers to publish it now instead
$I->signInAs('writer');
$I->amOnPage($scheduledEditUrl);
$I->waitForElement('#content_ifr', 15);
$I->see('Update schedule', '[data-actionbar-primary]');
$I->scrollDownTo('[data-clear-schedule]');
$I->click('[data-clear-schedule]');
$I->see('Publish now', '[data-actionbar-primary]');

// "Publish now" from the menu puts it live straight away, though its date is still ahead
$I->amOnPage($scheduledEditUrl);
$I->waitForElement('#content_ifr', 15);
$I->click('summary[title="More actions"]');
$I->click('Publish now', '[role=menu]');
$I->waitForText('Post updated');
$I->see('Published');
$I->amAGuest();
$I->amOnPage("/en/blog/{$blogSlug}/{$scheduledSlug}");
$I->dontSeeInTitle('Page not found');

// A draft can be deleted
$I->signInAs('writer');
$I->amOnPage('/en/dashboard/post/new');
$I->waitForElement('#content_ifr', 15);
$I->fillField('title', 'A post to delete');
$I->writeInTheEmptyEditor('Short-lived.');
$I->click('Save draft');
$I->waitForText('Post saved');
$draftUrl = $I->grabFromCurrentUrl();
$I->pressKey('body', WebDriverKeys::END);
$I->wait(1);
// The question names the post, and deleting goes back to the post list
$I->click('Delete post');
$I->waitForElementVisible('#confirmModalDanger');
$I->see('Permanently delete “A post to delete”?', '#confirmModalDanger');
$I->click('#confirmModalDanger [data-modal-confirm]');
$I->waitForText('“A post to delete” deleted.');
$I->seeCurrentUrlMatches('~/dashboard/post($|\?)~');
$I->seeThePageLooksRight('29-draft-deleted');
$I->amOnPage($draftUrl);
$I->seeInTitle('not found');

// Deleting a live post with unsaved edits doesn't then ask about leaving the page
$I->amOnPage($scheduledEditUrl);
$I->waitForElement('#content_ifr', 15);
$I->fillField('title', 'Changed, then deleted');
// Added after the page's own leave check, so it records what that check decided
$I->executeJS("sessionStorage.removeItem('leaveWarned'); window.addEventListener('beforeunload', function (e) { sessionStorage.setItem('leaveWarned', String(e.defaultPrevented)); });");
$I->pressKey('body', WebDriverKeys::END);
$I->wait(1);
$I->click('Delete post');
$I->waitForElementVisible('#confirmModalDanger');
$I->click('#confirmModalDanger [data-modal-confirm]');
$I->waitForText('deleted.');
$I->assertSame('false', $I->executeJS("return sessionStorage.getItem('leaveWarned');"));

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
