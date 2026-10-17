<?php

declare(strict_types=1);

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use App\Controllers\Auth\AuthController;
use App\Services\Captcha\AltchaCaptcha;
use App\Services\Captcha\CaptchaFactory;
use App\Services\Captcha\CaptchaInterface;
use App\Services\LoginRateLimiter;
use Framework\Cache\RateLimitStore;
use Framework\Core\App;
use Framework\Helpers\RateLimiter;
use Tests\Helpers\ThrottleTestHelper;

/**
 * The login controller asks for the sign-in check once an account has a few
 * failures, refuses attempts without a valid answer before trying the
 * password, and never charges those refusals to the account.
 *
 * The limiter and the captcha are swapped in the container for an in-memory
 * cache and a temporary store, so these tests never touch storage/limiter.
 */
beforeEach(function () {
    $_SESSION = [];

    $this->cache = ThrottleTestHelper::fakeCache();
    $this->limiter = new LoginRateLimiter(new RateLimiter($this->cache), $this->cache);

    $this->storeDir = sys_get_temp_dir().'/lx-login-captcha-'.bin2hex(random_bytes(4));
    $this->captcha = new AltchaCaptcha(
        new Altcha(hmacSignatureSecret: 'test-signature', hmacKeySignatureSecret: 'test-key-signature'),
        new RateLimitStore($this->storeDir),
        'test-binding',
        ['cost' => 10, 'counter_min' => 1, 'counter_max' => 3, 'max_level' => 3, 'expires_seconds' => 300]
    );

    $container = App::container();
    $container->set(LoginRateLimiter::class, fn () => $this->limiter);
    $container->setShared(CaptchaInterface::class, fn () => $this->captcha);

    $this->viewer = new class() implements \Framework\Interfaces\TemplateViewerInterface
    {
        /** @var array<string, mixed> */
        public array $lastData = [];

        public function render(string $template, array $data = []): string
        {
            $this->lastData = $data;

            return 'mocked view';
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
});

afterEach(function () {
    $_SESSION = [];

    // The container is shared across tests, so the real bindings go back in for the ones that follow
    $container = App::container();
    $container->set(LoginRateLimiter::class, fn ($c) => new LoginRateLimiter(
        $c->get(RateLimiter::class),
        $c->get(RateLimitStore::class),
        (require ROOT_PATH.'/config/captcha.php')['login']
    ));
    $container->setShared(CaptchaInterface::class, fn ($c) => CaptchaFactory::make(
        require ROOT_PATH.'/config/captcha.php',
        (string) env('APP_KEY', ''),
        $c->get(RateLimitStore::class)
    ));

    foreach (glob($this->storeDir.'/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($this->storeDir);
});

/**
 * @param  array<string, string>  $post
 */
function loginAttempt(object $test, array $post, bool $ajax = true): \Framework\Core\Response
{
    $request = makeRequest('/login', 'POST', array_merge(['_token' => csrf()->getToken()], $post));
    if ($ajax) {
        $request->headers['x-requested-with'] = 'XMLHttpRequest';
    }

    $controller = new AuthController();
    setupController($controller, $request, $test->viewer);

    return $controller->submit();
}

/**
 * @param  array<string, mixed>  $challenge
 */
function answerFor(array $challenge): string
{
    $parsed = Challenge::fromArray($challenge);
    $solution = (new Altcha())->solveChallenge(new SolveChallengeOptions(challenge: $parsed, algorithm: new Pbkdf2()));

    return (new Payload($parsed, $solution))->toBase64();
}

function failThreeTimes(LoginRateLimiter $limiter, string $email): void
{
    foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3'] as $ip) {
        $limiter->hit($ip, $email);
    }
}

it('asks for nothing below the threshold', function () {
    $response = loginAttempt($this, ['email' => 'nobody-captcha@example.com', 'password' => 'wrong']);
    $body = json_decode($response->getBody(), true);

    expect($response->getStatusCode())->toBe(401)
        ->and($body)->not->toHaveKey('captcha');
});

it('refuses an attempt without an answer and hands back a challenge', function () {
    failThreeTimes($this->limiter, 'owner-captcha@example.com');
    $scoreBefore = $this->cache->get('login:email:owner-captcha@example.com');

    $response = loginAttempt($this, ['email' => 'owner-captcha@example.com', 'password' => 'wrong']);
    $body = json_decode($response->getBody(), true);

    expect($response->getStatusCode())->toBe(422)
        ->and($body['captcha']['parameters']['algorithm'])->toBe('PBKDF2/SHA-256')
        ->and($this->cache->get('login:email:owner-captcha@example.com'))->toBe($scoreBefore);
});

it('audits a wrong answer without the email and charges only the IP', function () {
    failThreeTimes($this->limiter, 'owner-captcha@example.com');

    $response = loginAttempt($this, ['email' => 'owner-captcha@example.com', 'password' => 'wrong', 'altcha' => 'garbage']);

    $rows = $this->db->query("SELECT user_id, details FROM activity_log WHERE action = 'auth.captcha_failed'")
        ->fetchAll(\PDO::FETCH_ASSOC);

    expect($response->getStatusCode())->toBe(422)
        ->and($rows)->toHaveCount(1)
        ->and($rows[0]['user_id'])->toBeNull()
        ->and((string) $rows[0]['details'])->toContain('malformed')
        ->and((string) $rows[0]['details'])->not->toContain('owner-captcha@example.com')
        ->and($this->cache->get('login:ip:127.0.0.1'))->not->toBeNull();
});

it('lets a solved attempt reach the password and counts the failure', function () {
    failThreeTimes($this->limiter, 'owner-captcha@example.com');
    $answer = answerFor($this->captcha->issue('login', 'owner-captcha@example.com'));

    $response = loginAttempt($this, ['email' => 'owner-captcha@example.com', 'password' => 'wrong', 'altcha' => $answer]);

    expect($response->getStatusCode())->toBe(401)
        ->and($this->cache->get('login:checked_failures:owner-captcha@example.com'))->not->toBeNull();
});

it('refuses an answer used twice', function () {
    failThreeTimes($this->limiter, 'owner-captcha@example.com');
    $answer = answerFor($this->captcha->issue('login', 'owner-captcha@example.com'));

    loginAttempt($this, ['email' => 'owner-captcha@example.com', 'password' => 'wrong', 'altcha' => $answer]);
    $second = loginAttempt($this, ['email' => 'owner-captcha@example.com', 'password' => 'wrong', 'altcha' => $answer]);

    expect($second->getStatusCode())->toBe(422);
});

it('refuses an answer issued for another email', function () {
    failThreeTimes($this->limiter, 'owner-captcha@example.com');
    $answer = answerFor($this->captcha->issue('login', 'attacker-captcha@example.com'));

    $response = loginAttempt($this, ['email' => 'owner-captcha@example.com', 'password' => 'wrong', 'altcha' => $answer]);

    expect($response->getStatusCode())->toBe(422);
});

it('re-renders the login page with a challenge for the full page', function () {
    failThreeTimes($this->limiter, 'owner-captcha@example.com');

    $response = loginAttempt($this, ['email' => 'owner-captcha@example.com', 'password' => 'wrong'], ajax: false);

    expect($response->getStatusCode())->toBe(422)
        ->and($this->viewer->lastData['captcha']['parameters']['algorithm'])->toBe('PBKDF2/SHA-256')
        ->and($this->viewer->lastData['email'])->toBe('owner-captcha@example.com');
});

it('lets the owner in after one solved challenge, whatever the account went through', function () {
    // auth() reads users through the container's connection, so the user must be written on that one
    $this->db->getConnection()->rollBack();
    $this->db = App::container()->get(\Framework\Database::class);
    $this->db->getConnection()->beginTransaction();

    $email = faker()->unique()->safeEmail();
    \Tests\Factories\UserFactory::new(new \App\Models\UserModel($this->db))
        ->withAttributes(['email' => $email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->create();

    // Thirty failures from thirty other addresses: the old email lock would have held for an hour
    for ($i = 0; $i < 30; $i++) {
        $this->limiter->hit('10.0.'.$i.'.1', $email, true);
    }

    $answer = answerFor($this->captcha->issue('login', $email));
    $response = loginAttempt($this, ['email' => $email, 'password' => 'password123', 'altcha' => $answer]);

    expect($response->getStatusCode())->toBe(200)
        ->and($this->limiter->requiresChallenge('192.0.2.1', $email))->toBeFalse();
});
