<?php

declare(strict_types=1);

namespace Support;

/**
 * Inherited Methods
 *
 * @method void wantTo($text)
 * @method void wantToTest($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause($vars = [])
 *
 * @SuppressWarnings(PHPMD)
 */
class JourneyTester extends \Codeception\Actor
{
    use _generated\JourneyTesterActions;

    /**
     * Pick an option the way a person does, through the select menu that replaces the native control.
     */
    public function chooseFromMenu(string $selectSelector, string $label, ?string $search = null): void
    {
        $this->executeJS('document.querySelector('.json_encode($selectSelector).").parentElement.querySelector('.select-menu-button').click();");
        $this->waitForElementVisible('.select-menu-open .select-menu-list', 3);
        if ($search !== null) {
            $this->fillField('.select-menu-open .select-menu-search', $search);
        }
        $this->click("//div[contains(@class,'select-menu-open')]//button[contains(@class,'select-menu-item')][normalize-space()=\"".$label.'"]');
    }

    /**
     * Invite someone from the team page: open the invite dialog, fill it in and send it.
     */
    public function inviteToTeam(string $email, string $role): void
    {
        $this->click('[data-modal-target="teamInviteModal"]');
        $this->waitForElementVisible('#teamInviteForm', 3);
        $this->fillField('#teamInviteForm input[type=email][name=email]', $email);
        $this->selectOption('#invite-role', $role);
        $this->click('Send invite');
    }

    /**
     * Change the only collaborator's role on the team page, through the row menu and its dialog.
     */
    public function changeTeamRole(string $role): void
    {
        $this->click('//tr//button[@data-row-actions-toggle]');
        $this->click('Change role', '//tr//*[@data-row-actions-menu]');
        $this->waitForElementVisible('#teamRoleForm', 3);
        $this->selectOption('#member-role', $role);
        $this->click('Save role');
    }

    /**
     * Press OK in the confirmation dialog, or in a native popup on pages that still use one.
     */
    public function acceptConfirm(?string $expectedText = null): void
    {
        $dialogs = [
            [
                "//*[@role='alertdialog'][not(contains(@class,'hidden'))]//button[@data-modal-confirm]",
                "//*[@role='alertdialog'][not(contains(@class,'hidden'))]//p[@data-modal-message]",
            ],
            ['.blog-confirm-ok', '.blog-confirm-message'],
        ];
        foreach ($dialogs as [$button, $message]) {
            try {
                $this->waitForElementVisible($button, 3);
            } catch (\Throwable) {
                continue;
            }
            if ($expectedText !== null) {
                $this->see($expectedText, $message);
            }
            $this->click($button);

            return;
        }
        if ($expectedText !== null) {
            $this->seeInPopup($expectedText);
        }
        $this->acceptPopup();
    }
}
