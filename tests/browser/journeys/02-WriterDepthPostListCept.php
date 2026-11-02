<?php

declare(strict_types=1);

use Facebook\WebDriver\WebDriverKeys;
use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('rename a post and duplicate it from the Posts list without opening the editor');

$stamp = time();
$I->signInAs('writer');
$postId = (int) preg_replace('~\D~', '', $I->recall('postEditUrl'));
$title = "[data-post-title][data-rename-url$=\"/post/{$postId}/rename\"]";
$input = "{$title} [data-post-rename-input]";
$hasTitle = "[.//*[@data-post-title and contains(@data-rename-url, '/post/{$postId}/rename')]]";
$card = "//div[contains(concat(' ', @class, ' '), ' card ')]{$hasTitle}";
$row = "//tr{$hasTitle}";
$menuLabels = static fn (string $owner): array => $I->executeJS(
    'var s = document.evaluate(arguments[0], document, null, 7, null), out = [];'
    .'for (var i = 0; i < s.snapshotLength; i++) out.push(s.snapshotItem(i).textContent.trim());'
    .'return out;',
    ["{$owner}//*[@data-row-actions-menu]//*[@role='menuitem']"]
);
$star = "//*[@title='Featured on homepage']";

$I->amOnPage('/en/dashboard/post?view=cards');
$original = $I->grabAttributeFrom($title, 'data-title');

// Escape puts the old title back
$I->click("{$title} [data-post-rename]");
$I->waitForElementVisible($input);
$I->dontSeeElement("{$title} [data-post-title-text]");
$I->pressKey($input, 'Not this one');
$I->pressKey($input, WebDriverKeys::ESCAPE);
$I->dontSeeElement($input);
$I->see($original, $title);

// Enter saves in place, and the new title is still there after a reload
$renamed = "Renamed on the list {$stamp}";
$I->executeJS('window.samePage = true');
$I->click("{$title} [data-post-rename]");
$I->waitForElementVisible($input);
$I->pressKey($input, $renamed);
$I->pressKey($input, WebDriverKeys::ENTER);
$I->waitForElementNotVisible($input);
$I->see($renamed, $title);
$I->assertTrue($I->executeJS('return window.samePage === true'), 'Renaming reloaded the page');
$I->reloadPage();
$I->see($renamed, $title);

// The card menu opens the duplicate dialog with a free title and address filled in
$I->click("{$card}//button[@data-row-actions-toggle]");
$I->click("{$card}//button[@data-post-duplicate]");
$I->waitForElementVisible('#postDuplicateModal #title');
$I->seeInField('#postDuplicateModal #title', "{$renamed} (copy)");
$I->waitForText('This address is available.', 10, '#postDuplicateModal');
$I->assertStringEndsWith('-copy', $I->grabValueFrom('#postDuplicateModal #slug'));
$I->seeThePageLooksRight('post-list-duplicate');
$I->click('Duplicate', '#postDuplicateModal .confirm-actions');
$I->waitForText('saved as a draft', 10);
$I->seeInCurrentUrl('/en/dashboard/post');

// The compact list offers the same, and a second copy is numbered
$I->amOnPage('/en/dashboard/post?view=list');
$I->click("{$row}//button[@data-row-actions-toggle]");
$I->click("{$row}//button[@data-post-duplicate]");
$I->waitForElementVisible('#postDuplicateModal #title');
$I->seeInField('#postDuplicateModal #title', "{$renamed} (copy 2)");
$I->pressKey('#postDuplicateModal #title', WebDriverKeys::ESCAPE);
$I->waitForElementNotVisible('#postDuplicateModal');

// Featuring from the list marks the title with a star, and the card offers the very same menu
$I->click("{$row}//button[@data-row-actions-toggle]");
$I->click('Feature on homepage', "{$row}//*[@data-row-actions-menu]");
$I->waitForText('Featured on the homepage.', 10);
$I->seeElement("{$row}{$star}");
$listMenu = $menuLabels($row);
$I->assertContains('Unfeature', $listMenu);
$I->amOnPage('/en/dashboard/post?view=cards');
$I->seeElement("{$card}{$star}");
$I->assertSame($listMenu, $menuLabels($card));
$I->click("{$card}//button[@data-row-actions-toggle]");
$I->click('Unfeature', "{$card}//*[@data-row-actions-menu]");
$I->waitForText('Removed from the homepage.', 10);
$I->dontSeeElement("{$card}{$star}");
$I->amOnPage('/en/dashboard/post?view=list');

// Leave the walk blog as later journeys expect it: one post, under its old title
$copy = "//tr[.//*[@data-post-title and @data-title='{$renamed} (copy)']]";
$I->click("{$copy}//button[@data-row-actions-toggle]");
$I->click('Delete', "{$copy}//*[@data-row-actions-menu]");
$I->acceptConfirm();
$I->waitForText('deleted', 10);
$I->click("{$title} [data-post-rename]");
$I->waitForElementVisible($input);
$I->pressKey($input, $original);
$I->pressKey($input, WebDriverKeys::ENTER);
$I->waitForElementNotVisible($input);
$I->see($original, $title);
