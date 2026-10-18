<?php

declare(strict_types=1);

use App\Mail\NewPostMail;
use App\Models\EmailVerificationModel;
use App\Models\MailQueueModel;
use App\Models\UserModel;
use App\Services\EmailVerificationService;
use App\Services\LocaleRegistry;
use App\Services\MailQueueService;
use App\Services\MailService;
use App\Services\RecipientLocale;
use Tests\Factories\UserFactory;

/**
 * A sign-up proves its address by following a mailed link. Until then the
 * account is unconfirmed and nothing but the link and a password reset is
 * mailed to it.
 */
beforeEach(function () {
    $_ENV['APP_URL'] = 'https://example.test';
    $_ENV['APP_NAME'] = 'Lexicon';

    $this->users = new UserModel($this->db);
    $this->links = new EmailVerificationModel($this->db);
    $this->queue = new MailQueueService(new MailQueueModel($this->db), Mockery::mock(MailService::class));
    $this->service = new EmailVerificationService(
        $this->links,
        $this->users,
        $this->queue,
        new RecipientLocale($this->db, LocaleRegistry::instance())
    );

    $this->unconfirmed = fn (): int => UserFactory::new($this->users)
        ->withAttributes(['email_verified_at' => null])
        ->create();
    $this->queued = fn (int $userId): array => $this->db
        ->query('SELECT subject, body_text FROM mail_queue WHERE related_id = ?', [$userId])
        ->fetchAll(PDO::FETCH_ASSOC);
    $this->tokenFrom = function (int $userId): string {
        preg_match('#/verify-email/([a-f0-9]{64})#', ($this->queued)($userId)[0]['body_text'], $found);

        return $found[1];
    };
});

afterEach(function () {
    Mockery::close();
});

test('an account made any other way than sign-up counts as confirmed', function () {
    $id = UserFactory::new($this->users)->create();

    expect($this->users->find($id)['email_verified_at'])->not->toBeNull();
});

test('sending mails one link, and only the hash of it is stored', function () {
    $id = ($this->unconfirmed)();

    expect($this->service->send($id))->toBeTrue();
    $token = ($this->tokenFrom)($id);

    expect(count(($this->queued)($id)))->toBe(1)
        ->and($this->links->findValidByTokenHash($token))->toBeFalse()
        ->and($this->links->findValidByTokenHash(hash('sha256', $token))['user_id'])->toBe($id);
});

test('following the link confirms the address once, and the link is spent', function () {
    $id = ($this->unconfirmed)();
    $this->service->send($id);
    $token = ($this->tokenFrom)($id);

    expect($this->service->confirm($token))->toBe($id)
        ->and($this->users->find($id)['email_verified_at'])->not->toBeNull()
        ->and($this->service->confirm($token))->toBeNull();
});

test('an expired link confirms nothing', function () {
    $id = ($this->unconfirmed)();
    $this->service->send($id);
    $token = ($this->tokenFrom)($id);
    $this->db->execute('UPDATE email_verifications SET expires_at = UTC_TIMESTAMP() - INTERVAL 1 MINUTE WHERE user_id = ?', [$id]);

    expect($this->service->confirm($token))->toBeNull()
        ->and($this->users->find($id)['email_verified_at'])->toBeNull();
});

test('a resend replaces the earlier link', function () {
    $id = ($this->unconfirmed)();
    $this->service->send($id);
    $first = ($this->tokenFrom)($id);
    $this->service->send($id);

    expect($this->service->confirm($first))->toBeNull()
        ->and((int) $this->db->query('SELECT COUNT(*) FROM email_verifications WHERE user_id = '.$id)->fetchColumn())->toBe(1);
});

test('a confirmed account is sent nothing', function () {
    $id = UserFactory::new($this->users)->create();

    expect($this->service->send($id))->toBeFalse()
        ->and(($this->queued)($id))->toBe([]);
});

test('other mail waits for a confirmed address', function () {
    $email = 'waiting@example.test';
    $id = UserFactory::new($this->users)->withAttributes(['email' => $email, 'email_verified_at' => null])->create();
    $other = new class($email) extends \App\Mail\Mailable
    {
        public function __construct(private string $address)
        {
            parent::__construct();
        }

        public function build(): void
        {
            $this->to($this->address)->subject('Hello')->html('<p>Hello</p>');
        }
    };

    expect($this->queue->enqueue($other, 'user', $id))->toBe(0);

    $this->service->markVerified($id);

    expect($this->queue->enqueue($other, 'user', $id))->toBe(1);
});
