<?php

declare(strict_types=1);

use App\Controllers\AccountEmailController;
use App\Mail\EmailChangedMail;
use App\Mail\EmailChangeVerificationMail;
use App\Models\PendingEmailChangeModel;
use App\Models\UserModel;
use App\Services\EmailChangeIssuer;
use App\Services\MailQueueService;
use App\Services\PasswordConfirmRateLimiter;
use Framework\Helpers\RateLimiter;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\UserFactory;
use Tests\Helpers\ThrottleTestHelper;

/**
 * The address moves only when both gates are cleared: the current password at
 * request time, and the token from the new inbox at confirm time. Anything
 * short of that has to leave users.email exactly where it was.
 */
beforeEach(function () {
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }
    $this->db = \Framework\Core\App::container()->get(\Framework\Database::class);
    if (!$this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->beginTransaction();
    }

    $_SESSION = [];
    auth()->logout();

    $this->users = new UserModel($this->db);
    $this->pending = new PendingEmailChangeModel($this->db);
    $this->password = 'password123';
    $this->email = faker()->unique()->safeEmail();
    $this->userId = UserFactory::new($this->users)
        ->withAttributes([
            'email' => $this->email,
            'password' => password_hash($this->password, PASSWORD_DEFAULT),
        ])
        ->create();

    expect(auth()->login($this->email, $this->password))->toBeTrue();

    $this->throttle = new PasswordConfirmRateLimiter(new RateLimiter(ThrottleTestHelper::fakeCache()));

    $this->viewer = new class() implements TemplateViewerInterface
    {
        public function render(string $template, array $data = []): string
        {
            return 'mocked';
        }

        public function addGlobals(array $vars): void {}

        public function compiledViewStats(): array
        {
            return [];
        }

        public function pruneCompiledViews(int $maxAgeSeconds): int
        {
            return 0;
        }

        public function clearCompiledViews(): array
        {
            return [];
        }
    };

    $this->makeController = fn ($mailQueue) => new AccountEmailController(
        $this->users,
        $this->pending,
        $this->throttle,
        $mailQueue,
        new EmailChangeIssuer($this->pending, $mailQueue),
    );

    $this->requestChange = function (string $newEmail, string $password) {
        $mail = Mockery::mock(MailQueueService::class);
        $mail->shouldReceive('enqueue')
            ->once()
            ->with(Mockery::type(EmailChangeVerificationMail::class), 'account', $this->userId)
            ->andReturn(1);

        $controller = ($this->makeController)($mail);
        $request = makeRequest('/account/email', 'POST', [
            '_token' => csrf()->getToken(),
            'new_email' => $newEmail,
            'current_password' => $password,
        ]);
        setupController($controller, $request, $this->viewer);

        return callController($controller, 'requestChange', $request);
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
    Mockery::close();
});

test('a request with the wrong password stores nothing and sends nothing', function () {
    $mail = Mockery::mock(MailQueueService::class);
    $mail->shouldNotReceive('enqueue');
    $controller = ($this->makeController)($mail);

    $request = makeRequest('/account/email', 'POST', [
        '_token' => csrf()->getToken(),
        'new_email' => 'attacker@evil.test',
        'current_password' => 'wrong-password',
    ]);
    setupController($controller, $request, $this->viewer);

    $response = callController($controller, 'requestChange', $request);

    expect($response->getStatusCode())->toBe(302)
        ->and($this->users->findById($this->userId)['email'])->toBe($this->email)
        ->and($this->pending->findForUser($this->userId))->toBeFalse();
});

test('once throttled, even the correct password stores nothing', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->throttle->hit($this->userId);
    }

    $mail = Mockery::mock(MailQueueService::class);
    $mail->shouldNotReceive('enqueue');
    $controller = ($this->makeController)($mail);

    $request = makeRequest('/account/email', 'POST', [
        '_token' => csrf()->getToken(),
        'new_email' => faker()->unique()->safeEmail(),
        'current_password' => $this->password,
    ]);
    setupController($controller, $request, $this->viewer);

    callController($controller, 'requestChange', $request);

    expect($this->users->findById($this->userId)['email'])->toBe($this->email)
        ->and($this->pending->findForUser($this->userId))->toBeFalse();
});

test('a correct password records the change but does not apply it yet', function () {
    $newEmail = faker()->unique()->safeEmail();

    $response = ($this->requestChange)($newEmail, $this->password);

    expect($response->getStatusCode())->toBe(302)
        // The whole point: the account still answers to the old address.
        ->and($this->users->findById($this->userId)['email'])->toBe($this->email);

    $row = $this->pending->findForUser($this->userId);
    expect($row)->not->toBeFalse()
        ->and($row['new_email'])->toBe($newEmail)
        ->and((int) $row['is_expired'])->toBe(0);
});

test('the raw token is never stored, only its hash', function () {
    $token = bin2hex(random_bytes(32));
    $this->pending->replaceForUser(
        $this->userId,
        faker()->unique()->safeEmail(),
        hash('sha256', $token),
        gmdate('Y-m-d H:i:s', time() + 3600)
    );

    $stored = $this->db->query(
        'SELECT token FROM pending_email_changes WHERE user_id = ?',
        [$this->userId]
    )->fetchColumn();

    // A database read must not be replayable as a confirmation link.
    expect($stored)->not->toBe($token)
        ->and($stored)->toBe(hash('sha256', $token))
        ->and($this->pending->findValidByTokenHash($token))->toBeFalse();
});

test('a second request replaces the first, so the older link stops working', function () {
    $firstToken = bin2hex(random_bytes(32));
    $expiry = gmdate('Y-m-d H:i:s', time() + 3600);
    $this->pending->replaceForUser($this->userId, 'first@example.test', hash('sha256', $firstToken), $expiry);

    $secondToken = bin2hex(random_bytes(32));
    $this->pending->replaceForUser($this->userId, 'second@example.test', hash('sha256', $secondToken), $expiry);

    expect($this->pending->findValidByTokenHash(hash('sha256', $firstToken)))->toBeFalse()
        ->and($this->pending->findForUser($this->userId)['new_email'])->toBe('second@example.test');
});

test('confirming with the mailed token applies the change and notifies the old address', function () {
    $newEmail = faker()->unique()->safeEmail();
    $token = bin2hex(random_bytes(32));
    $this->pending->replaceForUser(
        $this->userId,
        $newEmail,
        hash('sha256', $token),
        gmdate('Y-m-d H:i:s', time() + 3600)
    );

    $mail = Mockery::mock(MailQueueService::class);
    $mail->shouldReceive('enqueue')
        ->once()
        ->with(Mockery::type(EmailChangedMail::class), 'account', $this->userId)
        ->andReturn(1);

    $controller = ($this->makeController)($mail);
    $request = makeRequest('/account/email/confirm/'.$token, 'GET');
    setupController($controller, $request, $this->viewer);

    $response = callController($controller, 'confirm', $request, $token);

    expect($response->getStatusCode())->toBe(302)
        ->and($this->users->findById($this->userId)['email'])->toBe($newEmail)
        ->and($this->pending->findForUser($this->userId))->toBeFalse();
});

test('an unknown token changes nothing', function () {
    $mail = Mockery::mock(MailQueueService::class);
    $mail->shouldNotReceive('enqueue');
    $controller = ($this->makeController)($mail);

    $token = bin2hex(random_bytes(32));
    $request = makeRequest('/account/email/confirm/'.$token, 'GET');
    setupController($controller, $request, $this->viewer);

    callController($controller, 'confirm', $request, $token);

    expect($this->users->findById($this->userId)['email'])->toBe($this->email);
});

test('an expired token changes nothing', function () {
    $token = bin2hex(random_bytes(32));
    $this->pending->replaceForUser(
        $this->userId,
        faker()->unique()->safeEmail(),
        hash('sha256', $token),
        gmdate('Y-m-d H:i:s', time() - 60)
    );

    $mail = Mockery::mock(MailQueueService::class);
    $mail->shouldNotReceive('enqueue');
    $controller = ($this->makeController)($mail);

    $request = makeRequest('/account/email/confirm/'.$token, 'GET');
    setupController($controller, $request, $this->viewer);

    callController($controller, 'confirm', $request, $token);

    expect($this->users->findById($this->userId)['email'])->toBe($this->email);
});

test('a token belonging to another account changes nothing', function () {
    $otherId = UserFactory::new($this->users)->create();
    $token = bin2hex(random_bytes(32));
    $this->pending->replaceForUser(
        $otherId,
        faker()->unique()->safeEmail(),
        hash('sha256', $token),
        gmdate('Y-m-d H:i:s', time() + 3600)
    );

    $mail = Mockery::mock(MailQueueService::class);
    $mail->shouldNotReceive('enqueue');
    $controller = ($this->makeController)($mail);

    $request = makeRequest('/account/email/confirm/'.$token, 'GET');
    setupController($controller, $request, $this->viewer);

    callController($controller, 'confirm', $request, $token);

    expect($this->users->findById($this->userId)['email'])->toBe($this->email)
        ->and($this->users->findById($otherId)['email'])->not->toBe(null);
});

test('an address claimed while the token was in flight is refused at confirm time', function () {
    $contested = faker()->unique()->safeEmail();
    $token = bin2hex(random_bytes(32));
    $this->pending->replaceForUser(
        $this->userId,
        $contested,
        hash('sha256', $token),
        gmdate('Y-m-d H:i:s', time() + 3600)
    );

    // Somebody else registers it before the link is followed.
    UserFactory::new($this->users)->withAttributes(['email' => $contested])->create();

    $mail = Mockery::mock(MailQueueService::class);
    $mail->shouldNotReceive('enqueue');
    $controller = ($this->makeController)($mail);

    $request = makeRequest('/account/email/confirm/'.$token, 'GET');
    setupController($controller, $request, $this->viewer);

    callController($controller, 'confirm', $request, $token);

    expect($this->users->findById($this->userId)['email'])->toBe($this->email)
        ->and($this->pending->findForUser($this->userId))->toBeFalse();
});

test('cancelling drops the pending change', function () {
    $this->pending->replaceForUser(
        $this->userId,
        faker()->unique()->safeEmail(),
        hash('sha256', bin2hex(random_bytes(32))),
        gmdate('Y-m-d H:i:s', time() + 3600)
    );

    $controller = ($this->makeController)(Mockery::mock(MailQueueService::class));
    $request = makeRequest('/account/email/cancel', 'POST', ['_token' => csrf()->getToken()]);
    setupController($controller, $request, $this->viewer);

    callController($controller, 'cancel', $request);

    expect($this->pending->findForUser($this->userId))->toBeFalse();
});
