<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('download a backup of the blog, check it before importing, back out, and import it as copies');

$blog = '/en/dashboard/blog/'.$I->recall('blogId');

$I->signInAs('writer');
$I->amOnPage("{$blog}/backup");
$I->see('Backup & restore');
$I->seeThePageLooksRight('backup-page');

// The download is a zip that holds the blog's posts
$archive = $I->grabBlogArchive();
$I->assertGreaterThan(1000, filesize(codecept_data_dir($archive)));

// Checking it shows what is inside, and the posts already here are flagged
$I->attachFile('input[name=archive]', $archive);
$I->click('Check archive');
$I->waitForText('What this archive contains', 15);
$I->see('Ready to import');
$I->see('Posts whose address is already taken');
$I->seeThePageLooksRight('backup-checked');

// Backing out deletes the upload and changes nothing
$I->click('Cancel import');
$I->waitForText('The import was cancelled');
$I->seeThePageLooksRight('backup-cancelled');

// Importing as copies gives each post a numbered address
$I->attachFile('input[name=archive]', $archive);
$I->click('Check archive');
$I->waitForText('What this archive contains', 15);
$I->checkOption('input[name=on_clash][value=copy]');
$I->click('Import posts');
$I->waitForText('Import finished', 60);
$count = fn (string $label): int => (int) $I->executeJS(
    "return [...document.querySelectorAll('dt')].find(dt => dt.textContent.trim() === '{$label}').nextElementSibling.textContent"
);
$I->assertGreaterThan(0, $count('Created'));
$I->assertSame(0, $count('Failed'));
$I->seeThePageLooksRight('backup-imported');
