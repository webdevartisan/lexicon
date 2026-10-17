<?php

declare(strict_types=1);

use Facebook\WebDriver\WebDriverKeys;
use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('look after my own account, from a forgotten password to deleting it');

$stamp = time();
$email = "walk-member-{$stamp}@example.test";
$password = "Walk-{$stamp}-7!";
$newPassword = "Walk-{$stamp}-9?";
$errors = '[role=alert], .lx-field-error, .invalid-feedback, [data-error]';

// A new member signs up
$I->amAGuest();
$I->amOnPage('/en/register');
$I->declineCookies();
$I->fillField('Email address', $email);
$I->fillField('Password', $password);
$I->checkOption('age_confirmed');
$I->waitForTheSignUpCheck();
$I->click('Create account');
$I->waitForText('What would you like to do?', 15);
$I->saveSessionSnapshot('member');

// A forgotten password gets the same answer for any address
$I->amAGuest();
$I->amOnPage('/en/login');
$I->declineCookies();
$I->seeElement('a[href$="/password/forgot"]');
foreach (["nobody-{$stamp}@example.test", $email] as $address) {
    $I->amOnPage('/en/password/forgot');
    $I->fillField('email', $address);
    $I->waitForTheSignUpCheck();
    $I->click('Send reset link');
    $I->waitForText('a reset link has been sent');
}
$I->seeThePageLooksRight('60-reset-link-sent');

// The member fills in a profile others can see
$I->loadSessionSnapshot('member');
$I->amOnPage('/en/account/profile');
$I->fillField('first_name', 'Robin');
$I->fillField('last_name', 'Walker');
$I->fillField('bio', 'Here to test the account pages.');
$I->checkOption('show_name');
$I->click('Save changes');
$I->waitForText('Profile updated successfully');
$handle = $I->grabValueFrom('handle');
$I->amAGuest();
$I->amOnPage("/en/profile/{$handle}");
$I->dontSeeInTitle('Page not found');
$I->see('Robin Walker');
$I->seeThePageLooksRight('61-public-profile');

// Changing the password refuses mistakes, then works
$I->loadSessionSnapshot('member');
foreach ([['not-my-password', $newPassword, $newPassword], [$password, $newPassword, "{$newPassword}x"], [$password, 'short', 'short']] as [$current, $next, $again]) {
    $I->amOnPage('/en/account/security');
    $I->fillField('current_password', $current);
    $I->fillField('new_password', $next);
    $I->fillField('new_password_confirm', $again);
    $I->click('Update password');
    $I->waitForElementVisible($errors);
}
$I->fillField('current_password', $password);
$I->fillField('new_password', $newPassword);
$I->fillField('new_password_confirm', $newPassword);
$I->click('Update password');
$I->waitForText('Password updated successfully');
// Only a screenshot here: this page scrolls sideways (L25), which 07-AccountL25Cept tracks
$I->makeScreenshot('62-password-changed');

// The old password no longer signs in, the new one does
$I->amAGuest();
$I->amOnPage('/en/login');
$I->declineCookies();
$I->fillField('Email address', $email);
$I->fillField('Password', $password);
$I->waitForTheSignUpCheck();
$I->click('Log in', 'form[action="/login"]');
$I->waitForElementVisible($errors);
$I->seeInCurrentUrl('/login');
$I->fillField('Email address', $email);
$I->fillField('Password', $newPassword);
$I->waitForTheSignUpCheck();
$I->click('Log in', 'form[action="/login"]');
$I->waitForJS("return !location.pathname.endsWith('/login')", 15);
$I->saveSessionSnapshot('member');

// Preferences, an email change that is cancelled, and notification settings
$I->amOnPage('/en/account/preferences');
$I->click("//div[contains(@class, 'choices')][.//select[@id='timezone']]");
$I->type('Athens');
$I->wait(1);
$I->pressKey('.choices.is-open input', WebDriverKeys::ENTER);
$I->click('Save changes', 'form[action$="/account/preferences/update"]');
$I->waitForText('Preferences saved');
$I->click('Change email');
$I->fillField('#email-dialog input[type=email]', "walk-moved-{$stamp}@example.test");
$I->fillField('#email-dialog input[type=password]', $newPassword);
$I->click('Send confirmation link');
$I->waitForText('Your address stays as it is until you follow it');
$I->click('Cancel this change');
$I->waitForText('The pending email change was cancelled');
$I->seeThePageLooksRight('63-preferences');
$I->amOnPage('/en/account/notifications');
$toggle = $I->grabAttributeFrom('input[type=checkbox][name^=notify_]', 'name');
$I->uncheckOption("input[name={$toggle}]");
$I->click('Save notification settings');
$I->waitForText('Notification settings saved');
$I->reloadPage();
$I->dontSeeCheckboxIsChecked("input[name={$toggle}]");

// Signing out another device
$I->amAGuest();
$I->amOnPage('/en/login');
$I->declineCookies();
$I->fillField('Email address', $email);
$I->fillField('Password', $newPassword);
$I->waitForTheSignUpCheck();
$I->click('Log in', 'form[action="/login"]');
$I->waitForJS("return !location.pathname.endsWith('/login')", 15);
$I->saveSessionSnapshot('second device');
$I->amAGuest();
$I->loadSessionSnapshot('member');
$I->amOnPage('/en/account/security');
$I->click('Sign out of all other devices');
$I->fillField('#session_password', $newPassword);
$I->click('Sign out', '#session-dialog');
$I->waitForText('signed out');
$I->makeScreenshot('64-other-devices-signed-out');
$I->amAGuest();
$I->loadSessionSnapshot('second device');
$I->amOnPage('/en/account/profile');
$I->seeInCurrentUrl('/login');

// Taking my data
$I->amAGuest();
$I->loadSessionSnapshot('member');
$I->amOnPage('/en/account/preferences');
$I->assertStringContainsString($email, $I->grabMyDataDownload());

// Deleting the account
$I->amOnPage('/en/account/delete');
$I->seeThePageLooksRight('65-delete-account');
$I->fillField('#deleteForm input[name=password]', 'wrong-password-here');
$I->checkOption('#confirmCheck');
$I->click('Delete my account permanently', '#deleteForm');
$I->click('Delete my account permanently', '#confirm-delete-account');
$I->waitForText('Incorrect password');
$I->amOnPage('/en/account/delete');
$I->fillField('#deleteForm input[name=password]', $newPassword);
$I->checkOption('#confirmCheck');
$I->click('Delete my account permanently', '#deleteForm');
$I->click('Delete my account permanently', '#confirm-delete-account');
$I->waitForText('Your account is deactivated');
$I->seeThePageLooksRight('66-account-deleted');

// The deleted account can no longer sign in
$I->amAGuest();
$I->amOnPage('/en/login');
$I->declineCookies();
$I->fillField('Email address', $email);
$I->fillField('Password', $newPassword);
$I->waitForTheSignUpCheck();
$I->click('Log in', 'form[action="/login"]');
$I->waitForElementVisible($errors);
$I->seeInCurrentUrl('/login');
$I->remember('deletedEmail', $email);
$I->remember('deletedPassword', $newPassword);
