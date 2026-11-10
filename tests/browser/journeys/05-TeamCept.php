<?php

declare(strict_types=1);

use Facebook\WebDriver\WebDriverKeys;
use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('take posts through editorial review with the reader as a contributor, then manage the team');

$blog = '/en/dashboard/blog/'.$I->recall('blogId');
$blogSlug = $I->recall('blogSlug');
$readerEmail = $I->recall('readerEmail');
$stamp = time();
$straight = "For review straight {$stamp}";
$viaDraft = "For review via draft {$stamp}";

// The writer turns review on and invites the reader as a contributor
$I->signInAs('writer');
$I->amOnPage("{$blog}/settings");
$I->click('Discussion & Workflow', '[role=tablist]');
$I->scrollDownTo('#workflow_enabled');
$I->checkOption('#workflow_enabled');
$I->click('button[data-actionbar-primary]');
$I->waitForText('Blog updated');
$I->amOnPage("{$blog}/team");
$I->inviteToTeam($readerEmail, 'Contributor');
$I->waitForText("Invitation sent to {$readerEmail}");
$I->seeThePageLooksRight('50-reader-invited');

// The reader accepts
$I->signInAs('reader');
$I->amOnPage('/en/notifications');
$I->click('.notif-action-btn.is-primary');
$I->waitForText("You've joined the blog");
$I->seeThePageLooksRight('51-invitation-accepted');

// Their overview offers the contributor's steps, never the owner's
$I->amOnPage('/en/dashboard');
$I->waitForElementVisible('[data-next-steps] section');
$I->see('Write your first post here', '[data-next-steps]');
$I->see('See how review works', '[data-next-steps]');
foreach (['Start your blog', 'Publish your first post', 'Make it look like yours', 'Tell people about it'] as $ownerStep) {
    $I->dontSee($ownerStep, '[data-next-steps]');
}
$I->seeThePageLooksRight('51-contributor-next-steps');
$I->amOnAPhone();
$I->seeThePageLooksRight('51-contributor-next-steps-phone');
$I->putThePhoneAway();

// A post submitted straight from New post goes into review
$I->amOnPage('/en/dashboard/post/new');
$I->waitForElement('#content_ifr', 15);
$I->fillField('title', $straight);
$I->writeInTheEmptyEditor('A contributor draft that needs a reviewer.');
$I->click('Submit for review');
$I->waitForText('Submitted for review');
$I->seeElement('#content_ifr');
$straightId = $I->grabFromCurrentUrl('~/post/(\d+)/edit~');
$I->seeThePageLooksRight('52-submitted-straight');

// A post saved as a draft first goes into review too
$I->amOnPage('/en/dashboard/post/new');
$I->waitForElement('#content_ifr', 15);
$I->fillField('title', $viaDraft);
$I->waitForJS("return document.querySelector('input[name=slug]').value !== ''");
$viaDraftSlug = $I->grabValueFrom('slug');
$I->writeInTheEmptyEditor('A contributor draft that needs a reviewer.');
$I->click('Save draft');
$I->waitForText('Post saved');
$I->click('Submit for review');
$I->waitForText('Submitted for review');
$viaDraftId = $I->grabFromCurrentUrl('~/post/(\d+)/edit~');

// Both wait in the writer's review queue
$I->signInAs('writer');
$I->amOnPage("{$blog}/review-queue");
$I->see($straight);
$I->see($viaDraft);
$I->seeThePageLooksRight('53-review-queue');

// The team page points at the queue while posts wait without a reviewer
$I->amOnPage("{$blog}/team");
$I->see('Posts in review without a reviewer:');
$I->seeThePageLooksRight('53-team-review-alert');
$I->click('.bg-amber-50 a');
$I->seeInCurrentUrl('/review-queue');

// The contributor can keep editing until review starts
$I->signInAs('reader');
$I->amOnPage("/en/dashboard/post/{$straightId}/edit");
$I->waitForElement('#content_ifr', 15);
$I->scrollDownTo('#content_ifr');
$I->switchToIFrame('#content_ifr');
$I->click('#tinymce p');
$I->pressKey('#tinymce p', WebDriverKeys::END, WebDriverKeys::ENTER);
$I->type('One more line before anyone reads it.');
$I->switchToIFrame();
$I->click('Save changes');
$I->waitForText('Post updated');
$I->see('Pending review');

// Once the writer starts reviewing, the contributor can only watch or withdraw
$I->signInAs('writer');
$I->amOnPage("/en/dashboard/post/{$straightId}/review");
$I->click('Start review');
$I->waitForText('You are reviewing this post');
$I->seeThePageLooksRight('54-review-started');
$I->signInAs('reader');
$I->amOnPage("/en/dashboard/post/{$straightId}/edit");
$I->see('A reviewer is reading this post');
$I->dontSeeElement('#content_ifr');
$I->amOnPage('/en/notifications');
$I->see('started reviewing');
$I->amOnPage("/en/dashboard/post/{$straightId}/edit");
$I->click('Withdraw to draft');
$I->waitForText('Back in drafts');
$I->seeThePageLooksRight('55-withdrawn');
$I->signInAs('writer');
$I->amOnPage("{$blog}/review-queue");
$I->dontSee($straight);
$I->amOnPage('/en/notifications');
$I->see('withdrew');

// Sent back with notes, changed, resubmitted, approved and published
$I->amOnPage("/en/dashboard/post/{$viaDraftId}/review");
$I->fillField('feedback', 'Please add a second paragraph.');
$I->click('Send back with notes');
$I->waitForText('Sent back to the writer');
$I->seeThePageLooksRight('56-sent-back');
$I->signInAs('reader');
$I->amOnPage("/en/dashboard/post/{$viaDraftId}/edit");
$I->see('Please add a second paragraph.');
$I->waitForElement('#content_ifr', 15);
$I->scrollDownTo('#content_ifr');
$I->switchToIFrame('#content_ifr');
$I->click('#tinymce p');
$I->pressKey('#tinymce p', WebDriverKeys::END, WebDriverKeys::ENTER);
$I->type('Here is the second paragraph you asked for.');
$I->switchToIFrame();
$I->click('button[data-actionbar-primary]');
$I->waitForText('Submitted for review');
$I->signInAs('writer');
$I->amOnPage("/en/dashboard/post/{$viaDraftId}/review");
$I->click('Approve and publish');
$I->waitForText('Published');
$I->seeThePageLooksRight('57-approved-and-published');
$I->amAGuest();
$I->amOnPage("/en/blog/{$blogSlug}/{$viaDraftSlug}");
$I->dontSeeInTitle('Page not found');
$I->signInAs('reader');
$I->amOnPage('/en/notifications');
$I->see('is live');
$I->amOnPage("/en/dashboard/post/{$viaDraftId}/edit");
$I->see('This post is live');
$I->dontSeeElement('#content_ifr');

// The writer changes the reader's role, then removes them
$I->signInAs('writer');
$I->amOnPage("{$blog}/team");
$I->changeTeamRole('Author');
$I->waitForText('Role updated');
$I->see('Author', 'tbody');
$I->click('//tr//button[@data-row-actions-toggle]');
$I->click('Remove', '//tr//*[@data-row-actions-menu]');
$I->acceptConfirm();
$I->waitForText('Collaborator access revoked');
$I->dontSeeElement('form[action$="/revoke"]');
$I->seeThePageLooksRight('58-reader-removed');
$I->signInAs('reader');
$I->amOnPage("{$blog}/settings");
$I->seeInTitle('Access denied');

// An invitation to someone without an account can be cancelled
$nobody = "walk-nobody-{$stamp}@example.test";
$I->signInAs('writer');
$I->amOnPage("{$blog}/team");
$I->inviteToTeam($nobody, 'Author');
$I->waitForText("Invitation sent to {$nobody}");
$I->see($nobody);
$row = "//tr[.//p[normalize-space()='{$nobody}']]";
$I->click("{$row}//button[@data-row-actions-toggle]");
$I->click('Cancel invite', "{$row}//*[@data-row-actions-menu]");
$I->waitForText('Invitation cancelled');
$I->dontSee($nobody);
$I->seeThePageLooksRight('59-invitation-cancelled');

// Review goes off again
$I->amOnPage("{$blog}/settings");
$I->click('Discussion & Workflow', '[role=tablist]');
$I->scrollDownTo('#workflow_enabled');
$I->uncheckOption('#workflow_enabled');
$I->click('button[data-actionbar-primary]');
$I->waitForText('Blog updated');
