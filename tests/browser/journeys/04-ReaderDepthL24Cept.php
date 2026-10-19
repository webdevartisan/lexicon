<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('edit my own comment, and not be offered Report on it');

$text = 'A comment I will fix.';
$fixed = 'A comment I fixed.';
$I->signInAs('reader');
$I->amOnPage($I->recall('postUrl'));
$I->declineCookies();
$I->scrollDownTo('#comment_content');
$I->fillField('#comment_content', $text);
$I->scrollDownTo('button.btn-submit');
$I->click('button.btn-submit');
$I->waitForText($text, 15);
$mine = $I->grabAttributeFrom("//li[starts-with(@id, 'comment-')][.//p[contains(., '{$text}')]]", 'id');
$id = substr($mine, strlen('comment-'));

$I->scrollDownTo("#{$mine} [data-menu-toggle]");
$I->click("#{$mine} [data-menu-toggle]");
$I->dontSeeElementInDOM("#{$mine} > .comment-row [data-menu-list] [data-report-comment]");
$I->click("#{$mine} [data-edit-comment]");
$I->waitForElementVisible("#edit-form-{$id} textarea", 5);
$I->dontSeeElement("#{$mine} > .comment-row .comment-block > .comment-body");

// Cancel puts the text back as it was
$I->fillField("#edit-form-{$id} textarea", 'Something I will not keep.');
$I->click("#edit-form-{$id} [data-edit-cancel]");
$I->waitForElementVisible("#{$mine} > .comment-row .comment-block > .comment-body", 5);
$I->see($text, "#{$mine} > .comment-row .comment-body");

$I->click("#{$mine} [data-menu-toggle]");
$I->click("#{$mine} [data-edit-comment]");
$I->waitForElementVisible("#edit-form-{$id} textarea", 5);
$I->seeInField("#edit-form-{$id} textarea", $text);
$I->fillField("#edit-form-{$id} textarea", $fixed);
$I->click('Save', "#edit-form-{$id}");
$I->waitForText('Your comment was updated', 15);
$I->see($fixed, "#{$mine} > .comment-row .comment-body");
$I->see('edited', "#{$mine} > .comment-row .comment-meta");
$I->scrollDownTo("#{$mine}");
$I->makeScreenshot('l24-comment-edited');

// Other people's comments still offer Report, and no Edit
$other = $I->grabAttributeFrom("//li[starts-with(@id, 'comment-')][not(@id='{$mine}')][.//*[@data-report-comment]]", 'id');
$I->seeElementInDOM("#{$other} [data-report-comment]");
$I->dontSeeElementInDOM("#{$other} > .comment-row [data-edit-comment]");
