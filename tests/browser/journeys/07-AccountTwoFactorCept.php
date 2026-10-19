<?php

declare(strict_types=1);

use App\Services\TwoFactor\Totp;
use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('add a second sign-in step, use it, and take it off again');

$email = 'person1@example.com';
$password = '123456';
$totp = new Totp();

$signIn = function () use ($I, $email, $password): void {
    $I->amAGuest();
    $I->amOnPage('/en/login');
    $I->declineCookies();
    $I->waitForJS("return document.readyState === 'complete'", 10);
    $I->wait(1);
    $I->executeJS(
        "const f = document.querySelector('form[action=\"/login\"]');"
        .'f.email.value = '.json_encode($email).'; f.password.value = '.json_encode($password).'; f.requestSubmit();'
    );
};

$type = function (string $selector, string $value) use ($I): void {
    $I->waitForElementVisible($selector);
    $I->executeJS('const el = document.querySelector('.json_encode($selector).'); el.value = '.json_encode($value).'; el.dispatchEvent(new Event("input", {bubbles: true}));');
};

$submit = function (string $form) use ($I): void {
    $I->executeJS('document.querySelector('.json_encode($form).').requestSubmit();');
};

// A leftover from an earlier run must not decide how this one starts
shell_exec('php '.escapeshellarg(dirname(__DIR__, 3).'/cli').' two-factor:reset '.escapeshellarg($email));

// Setting it up asks for the password, then for a real code from the app
$signIn();
$I->waitForJS("return !location.pathname.endsWith('/login')", 15);
$I->amOnPage('/en/account/security');
$I->see('Two-step sign-in');
$I->amOnPage('/en/account/security/two-factor');
$I->waitForElement('form[action$="/two-factor/start"]');
$I->fillField('current_password', $password);
$I->click('Set up');
$I->waitForElement('.lx-twofactor-qr svg');
$I->seeElement('.lx-twofactor-qr svg');
$I->seeThePageLooksRight('67-two-factor-setup');
$secret = str_replace(' ', '', $I->grabTextFrom('.lx-twofactor-secret'));

$type('#setup_code', '000000');
$submit('form[action$="/two-factor/confirm"]');
$I->waitForText('That code did not match');

$type('#setup_code', $totp->codeAt($secret, $totp->stepAt(time())));
$submit('form[action$="/two-factor/confirm"]');
$I->waitForJS("return !!document.getElementById('codes-heading')", 15);
$I->seeNumberOfElements('.lx-recovery-codes code', 10);
$recovery = $I->grabTextFrom('.lx-recovery-codes li:first-child code');
$spare = $I->grabTextFrom('.lx-recovery-codes li:nth-child(2) code');
$I->seeThePageLooksRight('68-two-factor-recovery-codes');
$I->reloadPage();
$I->dontSeeElement('#codes-heading');
$I->seeElement('form[action$="/two-factor/disable"]');

// Signing in now stops at the second step
$signIn();
$I->waitForText('Enter the code from your authenticator app.');
$I->seeInCurrentUrl('/login/two-factor');
$I->seeThePageLooksRight('69-two-factor-challenge');
$type('#code', '000000');
$submit('form[action$="/login/two-factor"]');
$I->waitForElementVisible('.lx-authalert');
$I->seeInCurrentUrl('/login/two-factor');

// The step that switched it on is spent, so the next one signs in
$type('#code', $totp->codeAt($secret, $totp->stepAt(time()) + 1));
$submit('form[action$="/login/two-factor"]');
$I->waitForJS("return !location.pathname.includes('/login')", 15);
$I->amOnPage('/en/account/security');
$I->see('Two-step sign-in');

// A recovery code gets in once
$signIn();
$I->waitForText('Enter the code from your authenticator app.');
$type('#code', $recovery);
$submit('form[action$="/login/two-factor"]');
$I->waitForJS("return !location.pathname.includes('/login')", 15);
$signIn();
$I->waitForText('Enter the code from your authenticator app.');
$type('#code', $recovery);
$submit('form[action$="/login/two-factor"]');
$I->waitForElementVisible('.lx-authalert');
$I->seeInCurrentUrl('/login/two-factor');

// Taking it off needs the password again
$signIn();
$I->waitForText('Enter the code from your authenticator app.');
$type('#code', $spare);
$submit('form[action$="/login/two-factor"]');
$I->waitForJS("return !location.pathname.includes('/login')", 15);
$I->amOnPage('/en/account/security/two-factor');
$type('#disable_password', $password);
$submit('form[action$="/two-factor/disable"]');
$I->waitForText('Two-step sign-in is off');
$I->seeElement('form[action$="/two-factor/start"]');
