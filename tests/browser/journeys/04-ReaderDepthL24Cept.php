<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('edit my own comment');

// Known bug L24: readers can remove their own comment but not edit it, and removing asks
// through the browser's own confirm box. Delete this line once it is fixed.
$scenario->incomplete('L24: no comment editing');

$text = 'A comment I will fix.';
$I->signInAs('reader');
$I->amOnPage($I->recall('postUrl'));
$I->declineCookies();
$I->scrollDownTo('#comment_content');
$I->fillField('#comment_content', $text);
$I->scrollDownTo('button.btn-submit');
$I->click('button.btn-submit');
$I->waitForText($text, 15);
$mine = $I->grabAttributeFrom("//li[starts-with(@id, 'comment-')][.//p[contains(., '{$text}')]]", 'id');
$I->scrollDownTo("#{$mine} [data-menu-toggle]");
$I->click("#{$mine} [data-menu-toggle]");
$I->seeElementInDOM("//li[@id='{$mine}']//*[@data-menu-list]//button[contains(., 'Edit')]");
