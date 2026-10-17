<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('bring the reader onto the blog as an author who can write but not run it');

$blog = '/en/dashboard/blog/'.$I->recall('blogId');

// The writer finds the reader's comment
$I->signInAs('writer');
$I->amOnPage('/en/dashboard/notifications');
$I->seeThePageLooksRight('70-writer-notifications');
$I->amOnPage("{$blog}/comments?status=all");
$I->see('Lovely first post');
$I->seeThePageLooksRight('71-blog-comments');

// The reader accepts an invitation to write
$I->amOnPage("{$blog}/team");
$I->fillField('input[type=email][name=email]', $I->recall('readerEmail'));
$I->selectOption('#invite-role', 'Author');
$I->click('Send invite');
$I->waitForText('Invitation sent to');
$I->signInAs('reader');
$I->amOnPage('/en/notifications');
$I->click('.notif-action-btn.is-primary');
$I->waitForText("You've joined the blog");
$I->seeThePageLooksRight('72-reader-joined-as-author');

// An author can write but not run the blog
$I->amOnPage('/en/dashboard/post/new');
$I->waitForElement('#content_ifr', 15);
$I->seeThePageLooksRight('73-author-new-post');
foreach (['settings', 'team', 'backup'] as $area) {
    $I->amOnPage("{$blog}/{$area}");
    $I->seeInTitle('403');
}
