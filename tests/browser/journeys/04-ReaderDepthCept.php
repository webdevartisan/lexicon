<?php

declare(strict_types=1);

use Facebook\WebDriver\WebDriverKeys;
use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('get a reply, agree with it, take back a comment, report, like, save, share and unsubscribe');

$postUrl = $I->recall('postUrl');
$commentId = $I->recall('readerCommentId');
$reply = 'Thanks for reading, glad you came by.';

// The writer replies to the reader's comment
$I->signInAs('writer');
$I->amOnPage($postUrl);
$I->declineCookies();
$I->scrollDownTo("#comment-{$commentId} [data-reply-toggle]");
$I->click("#comment-{$commentId} [data-reply-toggle]");
$I->fillField("#reply-form-{$commentId} textarea", $reply);
$I->click('Reply', "#reply-form-{$commentId}");
$I->waitForText('Reply posted', 15);
$I->showRepliesTo($commentId);
$I->waitForText($reply);
$replyId = $I->grabAttributeFrom("#replies-{$commentId} .comment-item", 'id');
$I->seeThePageLooksRight('40-writer-replied');

// The reader hears about it
$I->signInAs('reader');
$I->amOnPage('/en/notifications');
$I->see('replied to your comment');
$I->seeThePageLooksRight('41-notifications');
$I->amOnPage('/en/replies');
$I->see($reply);
$I->seeThePageLooksRight('42-replies');

// The reader agrees with the reply, and it sticks
$I->amOnPage($postUrl);
$I->declineCookies();
$I->showRepliesTo($commentId);
$I->click("#{$replyId} [data-vote=up]");
// The thumb lights up at once; the vote is saved when the busy mark clears
$I->waitForElementNotVisible("#{$replyId} .comment-votes.is-busy");
$I->reloadPage();
$I->seeElementInDOM("#{$replyId} [data-vote=up][aria-pressed=true]");

// The reader takes back a comment
$secondThought = 'A second thought I will take back.';
$I->scrollDownTo('#comment_content');
$I->fillField('#comment_content', $secondThought);
$I->scrollDownTo('button.btn-submit');
$I->click('button.btn-submit');
$I->waitForText($secondThought, 15);
$takenBack = $I->grabAttributeFrom("//li[starts-with(@id, 'comment-')][.//p[contains(., '{$secondThought}')]]", 'id');
$I->scrollDownTo("#{$takenBack} [data-menu-toggle]");
$I->click("#{$takenBack} [data-menu-toggle]");
// The menu slides in, so a click can land before it settles; press its button from the keyboard
$I->pressKey("#{$takenBack} button.is-destructive", WebDriverKeys::ENTER);
$I->acceptConfirm();
$I->waitForElementNotVisible("#{$takenBack}", 15);
$I->seeThePageLooksRight('43-comment-taken-back');

// The reader reports the reply
$I->showRepliesTo($commentId);
$I->click("#{$replyId} [data-menu-toggle]");
$I->pressKey("#{$replyId} [data-report-comment]", WebDriverKeys::ENTER);
$I->waitForElementVisible('dialog.lex-report[open] input[name=reason]');
$I->click('dialog.lex-report[open] input[name=reason]');
$I->fillField('dialog.lex-report[open] textarea[name=details]', 'Testing the report form from the walkthrough.');
$I->click('Send report', 'dialog.lex-report[open]');
$I->waitForElementVisible('.lex-report-done');
$I->seeThePageLooksRight('44-reply-reported');

// The reader likes, saves and shares the post
$I->amOnPage($postUrl);
$I->click('[data-engage=up]');
$I->click('[data-engage=bookmark]');
$I->waitForElement('[data-engage=up][aria-pressed=true]');
$I->waitForElement('[data-engage=bookmark][aria-pressed=true]');
$I->reloadPage();
$I->seeElement('[data-engage=up][aria-pressed=true]');
$I->seeElement('[data-engage=bookmark][aria-pressed=true]');
$I->click('[data-share-toggle]');
$I->seeElement('[data-share-popover] [data-copy-link]');
$I->seeThePageLooksRight('45-share-menu');

// The reader's collections open
foreach (['saved', 'saved/liked', 'replies/mine'] as $page) {
    $I->amOnPage("/en/{$page}");
    $I->dontSeeInCurrentUrl('/login');
    $I->dontSeeInTitle('Page not found');
    $I->seeThePageLooksRight('46-'.str_replace('/', '-', $page));
}

// The reader unsubscribes and changes their mind
$I->amOnPage('/en/subscriptions');
$I->click('Unsubscribe', '.lx-reader-act');
$I->waitForText('Unsubscribed from');
$I->seeThePageLooksRight('47-unsubscribed');
$I->click('Undo', '.lx-reader-undo');
$I->waitForText('again');
$I->see($I->recall('blogName'));
$I->seeThePageLooksRight('48-subscribed-again');
