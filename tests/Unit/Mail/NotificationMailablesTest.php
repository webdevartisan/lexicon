<?php

declare(strict_types=1);

use App\Mail\CollaboratorRemovedMail;
use App\Mail\CollaboratorRoleChangedMail;
use App\Mail\InviteDeclinedMail;
use App\Mail\PostApprovedMail;
use App\Mail\PostNeedsChangesMail;
use App\Mail\PostPublishedMail;
use App\Mail\PostSubmittedMail;
use App\Mail\ReviewerAssignedMail;
use App\Mail\ReviewerStaleMail;
use App\Mail\WorkflowDisabledMail;

/**
 * Unit tests for all RBAC notification Mailable classes.
 *
 * Tests that each Mailable builds with the correct subject, recipient,
 * and body content. No database or mail transport involved.
 */
beforeEach(function () {
    $_ENV['APP_URL'] = 'https://example.test';
    $_ENV['APP_NAME'] = 'Lexicon';
});

test('PostSubmittedMail builds with subject and body', function () {
    $mail = new PostSubmittedMail('rev@example.test', 42, 'My Post', 'alice', false);

    expect($mail->getTo())->toHaveKey('rev@example.test')
        ->and($mail->getSubject())->toContain('My Post')
        ->and($mail->getBody())->toContain('alice')
        ->and($mail->getBody())->toContain('https://example.test/dashboard/posts/42/review')
        ->and($mail->getTextBody())->toContain('My Post');
});

test('PostSubmittedMail unassigned variant says so in body', function () {
    $mail = new PostSubmittedMail('rev@example.test', 42, 'My Post', 'alice', true);
    expect($mail->getBody())->toContain('No reviewer is assigned yet');
});

test('PostApprovedMail builds with reviewer name', function () {
    $mail = new PostApprovedMail('author@example.test', 42, 'My Post', 'bob');
    expect($mail->getSubject())->toContain('approved')
        ->and($mail->getBody())->toContain('bob')
        ->and($mail->getBody())->toContain('My Post');
});

test('PostNeedsChangesMail includes feedback verbatim (escaped)', function () {
    $mail = new PostNeedsChangesMail('author@example.test', 42, 'My Post', 'bob', 'Please fix typos in §2.');
    expect($mail->getBody())->toContain('Please fix typos')
        ->and($mail->getBody())->toContain('§2.');
});

test('PostPublishedMail links to public post URL', function () {
    $mail = new PostPublishedMail('author@example.test', 'My Post', 'my-blog', 'my-post');
    expect($mail->getBody())->toContain('https://example.test/blog/my-blog/my-post');
});

test('ReviewerAssignedMail names the assigner', function () {
    $mail = new ReviewerAssignedMail('rev@example.test', 42, 'My Post', 'alice');
    expect($mail->getBody())->toContain('alice');
});

test('ReviewerStaleMail names the former reviewer', function () {
    $mail = new ReviewerStaleMail('owner@example.test', 42, 'My Post', 'jane');
    expect($mail->getBody())->toContain('jane')
        ->and($mail->getSubject())->toContain('My Post');
});

test('CollaboratorRoleChangedMail includes new role', function () {
    $mail = new CollaboratorRoleChangedMail('user@example.test', 'My Blog', 'editor', 'admin');
    expect($mail->getBody())->toContain('editor')
        ->and($mail->getBody())->toContain('My Blog');
});

test('CollaboratorRemovedMail names the blog and remover', function () {
    $mail = new CollaboratorRemovedMail('user@example.test', 'My Blog', 'admin');
    expect($mail->getBody())->toContain('My Blog')
        ->and($mail->getBody())->toContain('admin');
});

test('InviteDeclinedMail tells the owner who declined', function () {
    $mail = new InviteDeclinedMail('owner@example.test', 'My Blog', 'nope@example.test');
    expect($mail->getBody())->toContain('nope@example.test')
        ->and($mail->getBody())->toContain('My Blog');
});

test('WorkflowDisabledMail explains the post was reset to draft', function () {
    $mail = new WorkflowDisabledMail('author@example.test', 42, 'My Post', 'My Blog');
    expect($mail->getBody())->toContain('My Post')
        ->and($mail->getBody())->toContain('draft');
});
