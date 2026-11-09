<?php

declare(strict_types=1);

use Facebook\WebDriver\WebDriverKeys;
use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('manage the team from one table, with invites and role help in dialogs');

$blog = '/en/dashboard/blog/'.$I->recall('blogId');
$nobody = 'walk-team-'.time().'@example.test';

// Nothing to fill in until the invite dialog opens
$I->signInAs('writer');
$I->amOnPage("{$blog}/team");
$I->dontSeeElement('#teamInviteForm');
$I->dontSee('Editorial workflow is off', 'main');
$I->seeThePageLooksRight('58-team-start');

// The invite dialog shows the chosen role's description, and the same help opens on top of it
$I->click('[data-modal-target="teamInviteModal"]');
$I->waitForElementVisible('#teamInviteForm', 3);
$I->selectOption('#invite-role', 'Editor');
$I->see('Runs the content', '#invite-role-doc');
$I->click('#teamInviteForm [data-modal-target="teamRolesModal"]');
$I->waitForElementVisible('#teamRolesModal', 3);
$I->pressKey('#teamRolesModal [data-modal-close]', WebDriverKeys::ESCAPE);
$I->waitForElementNotVisible('#teamRolesModal', 3);
$I->seeElement('#teamInviteForm');
$I->wait(1);
$I->seeThePageLooksRight('58-team-invite-dialog');
$I->pressKey('#teamInviteForm input[name=email]', WebDriverKeys::ESCAPE);
$I->waitForElementNotVisible('#teamInviteForm', 3);

// A sent invitation becomes a row in the table
$I->inviteToTeam($nobody, 'Author');
$I->waitForText("Invitation sent to {$nobody}");
$row = "//tr[.//p[normalize-space()='{$nobody}']]";
$I->see('Member', 'thead');
$I->see('Status', 'thead');
$I->see('Invited', $row);
$I->seeThePageLooksRight('58-team-table');

// The question mark beside the Role column explains each role
$I->click('thead [data-modal-target="teamRolesModal"]');
$I->waitForElementVisible('#teamRolesModal', 3);
$I->see('Full control', '#teamRolesModal');
$I->see('Editorial workflow is off', '#teamRolesModal');
$I->seeThePageLooksRight('58-team-roles-dialog');
$I->click('Close', '#teamRolesModal');
$I->waitForElementNotVisible('#teamRolesModal', 3);

// The page holds up on a phone and in Arabic
$I->amOnAPhone();
$I->amOnPage("{$blog}/team");
$I->seeThePageLooksRight('58-team-phone');
$I->putThePhoneAway();
$I->amOnPage(str_replace('/en/', '/ar/', "{$blog}/team"));
$I->seeThePageLooksRight('58-team-arabic');

// The row menu cancels the invitation
$I->amOnPage("{$blog}/team");
$I->click("{$row}//button[@data-row-actions-toggle]");
$I->see('Cancel invite', "{$row}//*[@data-row-actions-menu]");
$I->click('Cancel invite', "{$row}//*[@data-row-actions-menu]");
$I->waitForText('Invitation cancelled');
$I->dontSee($nobody);

// With a collaborator the table has a role dropdown, and the transfer dialog lists them
$readerEmail = $I->recall('readerEmail');
// A fresh load clears the success toast, which sits over the Invite button
$I->amOnPage("{$blog}/team");
$I->inviteToTeam($readerEmail, 'Contributor');
$I->waitForText("Invitation sent to {$readerEmail}");
$I->signInAs('reader');
$I->amOnPage('/en/notifications');
$I->click('.notif-action-btn.is-primary');
$I->waitForText("You've joined the blog");
$I->signInAs('writer');
$I->amOnPage("{$blog}/team");
$I->see('Contributor', 'tbody');
$I->see('Active', 'tbody');
$I->seeThePageLooksRight('58-team-member-row');
$I->click('[data-modal-target="teamTransferModal"]');
$I->waitForElementVisible('#teamTransferForm', 3);
$I->see('You cannot undo this yourself', '#teamTransferForm');
$I->wait(1);
$I->seeThePageLooksRight('58-team-transfer-dialog');
$I->click('[data-modal-close="teamTransferModal"]');
$I->waitForElementNotVisible('#teamTransferForm', 3);

// The row menu opens a dialog that already shows the current role, and saving it changes the badge
$I->click('//tr//button[@data-row-actions-toggle]');
$I->click('Change role', '//tr//*[@data-row-actions-menu]');
$I->waitForElementVisible('#teamRoleForm', 3);
$I->seeOptionIsSelected('#member-role', 'Contributor');
$I->see('walkreader', '#teamRoleForm');
$I->wait(1);
$I->seeThePageLooksRight('58-team-role-dialog');
$I->selectOption('#member-role', 'Author');
$I->see('Creates and edits their own posts', '#member-role-doc');
$I->click('Save role');
$I->waitForText('Role updated');
$I->see('Author', 'tbody');

// Every dialog fits a phone and reads right to left
$lookAtDialogs = function (string $page, string $tag) use ($I): void {
    $I->amOnPage($page);
    $dialogs = [
        'invite' => ['[data-modal-target="teamInviteModal"]', 'teamInviteModal'],
        'transfer' => ['[data-modal-target="teamTransferModal"]', 'teamTransferModal'],
        'role' => ['//tr//*[@data-row-actions-menu]//button[@data-role-action]', 'teamRoleModal'],
    ];
    foreach ($dialogs as $name => [$opener, $dialog]) {
        if ($name === 'role') {
            $I->click('//tr//button[@data-row-actions-toggle]');
        }
        $I->click($opener);
        $I->waitForElementVisible("#{$dialog} .confirm-card", 3);
        $I->wait(1);
        $I->seeThePageLooksRight("58-team-{$name}-dialog-{$tag}");
        $I->click("[data-modal-close=\"{$dialog}\"]");
        $I->waitForElementNotVisible("#{$dialog} .confirm-card", 3);
    }
};
$I->amOnAPhone();
$lookAtDialogs("{$blog}/team", 'phone');
$I->putThePhoneAway();
$lookAtDialogs(str_replace('/en/', '/ar/', "{$blog}/team"), 'arabic');

// The collaborator is removed again from the row menu
$I->amOnPage("{$blog}/team");
$I->click('//tr//button[@data-row-actions-toggle]');
$I->seeThePageLooksRight('58-team-row-menu');
$I->click('Remove', '//tr//*[@data-row-actions-menu]');
$I->acceptConfirm();
$I->waitForText('Collaborator access revoked');
