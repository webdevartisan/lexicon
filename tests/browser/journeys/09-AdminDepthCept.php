<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('decide a report, suspend and restore the reader, act as the writer, and create and delete a role');

if (!getenv('WALK_ADMIN_EMAIL') || !getenv('WALK_ADMIN_PASSWORD')) {
    $scenario->skip('Set WALK_ADMIN_EMAIL and WALK_ADMIN_PASSWORD to a local test admin to run this');
}

$readerEmail = $I->recall('readerEmail');

// The case for the reply the reader reported, found by this run's blog name
$I->signInAs('admin');
$I->amOnPage('/en/admin/reports?status=all');
$I->click("//tr[contains(., '".$I->recall('blogName')."')]//a");
$I->see('Thanks for reading');
$I->seeThePageLooksRight('90-report-case');
$I->click('Dismiss the reports');
$I->waitForElementVisible('#dismiss-note');
$I->fillField('#dismiss-note', 'Walk-through test report, nothing wrong with the reply.');
$I->click('Dismiss', '#dismissModal');
$I->waitForElementNotVisible('#dismissModal');
$I->seeThePageLooksRight('91-report-dismissed');

// Make sure the reader has an open session to watch it end
$I->signInAs('reader');

// Suspending the reader ends their session and keeps them out
$I->signInAs('admin');
$I->amOnPage('/en/admin/users?q='.urlencode($readerEmail));
$I->click('table a[href*="/admin/users/"]');
$readerPage = $I->grabFromCurrentUrl('~^(/(?:en/)?admin/users/\d+)~');
$I->amOnPage("{$readerPage}/suspend");
$I->fillField('reason', 'Walk-through test, lifted right away.');
$I->click('form[action$="/suspend"] button[type=submit]');
$I->waitForText('is suspended');
$I->seeThePageLooksRight('92-reader-suspended');
$I->amAGuest();
$I->loadSessionSnapshot('reader');
$I->amOnPage('/en/saved');
$I->seeInCurrentUrl('/login');

// Lifting it lets the reader back in
$I->signInAs('admin');
$I->amOnPage("{$readerPage}/suspend");
$I->click('form[action$="/lift-suspension"] button[type=submit]');
$I->waitForText('Suspension lifted');
$I->signInAs('reader');
$I->amOnPage('/en/saved');
$I->dontSeeInCurrentUrl('/login');

// The admin acts as the writer, then goes back to being themself
$I->signInAs('admin');
$I->amOnPage('/en/admin/users?q='.urlencode($I->recall('writerEmail')));
$I->click('table a[href*="/admin/users/"]');
$writerPage = $I->grabFromCurrentUrl('~^(/(?:en/)?admin/users/\d+)~');
$I->amOnPage("{$writerPage}/impersonate");
$I->fillField('reason', 'Walk-through check');
$I->click('form[action$="/impersonate"] button[type=submit]');
$I->waitForElement('form[action$="/impersonation/exit"]');
$I->seeThePageLooksRight('93-acting-as-writer');
$I->click('form[action$="/impersonation/exit"] button');
$I->waitForText('You are signed in as yourself again');

// A custom blog role can be created and deleted
$role = 'Walk Guest Editor '.time();
$I->amOnPage('/en/admin/roles/new');
$I->fillField('role_name', $role);
$I->fillField('description', 'Created by the walk-through and deleted right after.');
$I->selectOption('#scope', 'blog');
$I->fillField('level', '20');
$I->click('Create Role');
$I->waitForText('Role created');
$roleId = $I->grabFromCurrentUrl('~/roles/(\d+)~');
$I->seeThePageLooksRight('94-role-created');
$I->amOnPage("/en/admin/roles/{$roleId}/delete");
$I->click('form[action$="/destroy"] button[type=submit]');
$I->waitForText("Role '{$role}' deleted");
$I->amOnPage('/en/admin/roles');
$I->dontSee($role);
