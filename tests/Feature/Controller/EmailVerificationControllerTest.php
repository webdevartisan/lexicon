<?php

declare(strict_types=1);

use App\Controllers\EmailVerificationController;
use App\Models\EmailVerificationModel;
use App\Models\MailQueueModel;
use App\Models\UserModel;
use App\Services\CommentService;
use App\Services\EmailVerificationService;
use App\Services\LocaleRegistry;
use App\Services\MailQueueService;
use App\Services\MailService;
use App\Services\RecipientLocale;
use Framework\Helpers\RateLimiter;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Session;
use Tests\Factories\UserFactory;

/**
 * Following the confirmation link in the same browser brings a new account
 * back to the page it signed up from.
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
    $_ENV['APP_URL'] = 'https://example.test';

    $this->users = new UserModel($this->db);
    $this->service = new EmailVerificationService(
        new EmailVerificationModel($this->db),
        $this->users,
        new MailQueueService(new MailQueueModel($this->db), Mockery::mock(MailService::class)),
        new RecipientLocale($this->db, LocaleRegistry::instance())
    );

    $this->comments = Mockery::mock(CommentService::class);
    $this->comments->shouldReceive('resumePending')->andReturn(null)->byDefault();

    $password = 'correct-horse-battery';
    $email = faker()->unique()->safeEmail();
    $this->userId = UserFactory::new($this->users)
        ->withAttributes([
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'email_verified_at' => null,
        ])
        ->create();
    expect(auth()->login($email, $password))->toBeTrue();

    $this->service->send($this->userId);
    $mailed = $this->db->query('SELECT body_text FROM mail_queue WHERE related_id = ?', [$this->userId])->fetchColumn();
    preg_match('#/verify-email/([a-f0-9]{64})#', (string) $mailed, $found);
    $this->token = $found[1];

    $this->session = \Framework\Core\App::container()->get(Session::class);
    $this->confirm = function (): \Framework\Core\Response {
        $controller = new EmailVerificationController($this->service, $this->users, app(RateLimiter::class), $this->comments);
        setupController($controller, makeRequest('/verify-email/'.$this->token), Mockery::mock(TemplateViewerInterface::class));

        return $controller->confirm($this->token);
    };
});

afterEach(function () {
    Mockery::close();
});

test('the link goes back to the page the account signed up from', function () {
    $this->session->set(EmailVerificationService::RETURN_SESSION_KEY, '/en/start');

    $response = ($this->confirm)();

    expect($response->getHeader('Location'))->toEndWith('/en/start')
        ->and($this->session->get(EmailVerificationService::RETURN_SESSION_KEY))->toBeNull();
});

test('without a saved page the link goes to the home page', function () {
    $response = ($this->confirm)();

    expect($response->getHeader('Location'))->not->toContain('/dashboard')
        ->and($response->getHeader('Location'))->not->toContain('/login');
});

test('a held comment still wins over the saved page', function () {
    $this->session->set(EmailVerificationService::RETURN_SESSION_KEY, '/en/start');
    $this->comments->shouldReceive('resumePending')->andReturn(['ok' => true, 'message' => 'Posted', 'path' => '/en/blog/a/b']);

    $response = ($this->confirm)();

    expect($response->getHeader('Location'))->toEndWith('/en/blog/a/b')
        ->and($this->session->get(EmailVerificationService::RETURN_SESSION_KEY))->toBeNull();
});
