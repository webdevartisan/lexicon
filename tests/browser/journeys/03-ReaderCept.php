<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo("comment on the writer's post as a guest, sign up without losing the comment, and subscribe");

$stamp = time();
$email = "walk-reader-{$stamp}@example.test";
$password = "Walk-{$stamp}-7!";
$comment = 'Lovely first post. Looking forward to more.';

// A guest leaves a comment and is asked to sign in
$I->amAGuest();
$I->amOnPage($I->recall('postUrl'));
$I->declineCookies();
$I->scrollDownTo('#comment_content');
$I->fillField('#comment_content', $comment);
$I->scrollDownTo('button.btn-submit');
$I->click('button.btn-submit');
$I->waitForElementVisible('#lex-auth-email');
$I->seeThePageLooksRight('10-asked-to-sign-in');

// They create an account right there in the sign-in window
$I->fillField('#lex-auth-email', $email);
$I->click('Continue');
$I->waitForElementVisible('#lex-auth-password');
$I->fillField('#lex-auth-password', $password);
$I->checkOption('[data-auth-age]');
$I->waitForTheSignUpCheck();
$I->click('[data-auth-submit]');

// The comment went through and the box is empty again
$I->waitForText($comment, 15);
$I->see($comment, '[id^=comment-]');
$I->seeInField('#comment_content', '');
$I->seeThePageLooksRight('11-comment-posted');
$I->saveSessionSnapshot('reader');
$I->remember('readerEmail', $email);
$I->remember('readerPassword', $password);
$I->remember('readerCommentId', str_replace('comment-', '', $I->grabAttributeFrom('[id^=comment-]', 'id')));

// The reader subscribes to the blog
$I->amOnPage('/en/blog/'.$I->recall('blogSlug'));
$I->scrollDownTo('#newsletter');
$I->fillField('Your address', $email);
$I->click('Subscribe', 'form[action$="/subscribe"]');
$I->waitForText('You are subscribed');
$I->seeThePageLooksRight('12-subscribed');
$I->amOnPage('/en/subscriptions');
$I->see($I->recall('blogName'));

// The reader's own pages open
foreach (['saved', 'subscriptions', 'notifications', 'account/profile'] as $page) {
    $I->amOnPage("/en/{$page}");
    $I->dontSeeInCurrentUrl('/login');
    $I->dontSeeInTitle('Page not found');
    $I->seeThePageLooksRight('13-'.str_replace('/', '-', $page));
}
