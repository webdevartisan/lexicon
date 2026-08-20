<?php

declare(strict_types=1);

use App\Mail\EmailChangedMail;

/**
 * On a successful email change the PREVIOUS address is notified, so an account
 * takeover is visible to the person losing the account. build() runs in the
 * constructor, so the message is composed on construction.
 */
test('the notice is addressed to the previous address', function () {
    $mail = new EmailChangedMail('old@example.com', 'new@example.com', '2026-08-21T10:00:00Z');

    expect($mail->getTo())->toHaveKey('old@example.com');
    expect($mail->getTo())->not->toHaveKey('new@example.com');
});

test('the body names the new address so the change is legible', function () {
    $mail = new EmailChangedMail('old@example.com', 'new@example.com', '2026-08-21T10:00:00Z');

    expect($mail->getBody())->toContain('new@example.com');
    expect($mail->getSubject())->not->toBe('');
});
