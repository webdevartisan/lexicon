<?php

declare(strict_types=1);

use App\Controllers\Auth\TwoFactorChallengeController;
use App\Models\UserModel;
use App\Policies\SystemPolicy;
use App\Services\TwoFactor\SecretBox;
use App\Services\TwoFactor\Totp;
use App\Services\TwoFactor\TwoFactorPolicy;
use App\Services\TwoFactor\TwoFactorRateLimiter;
use App\Services\TwoFactor\TwoFactorService;
use Framework\Helpers\RateLimiter;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\UserFactory;
use Tests\Helpers\ThrottleTestHelper;

/**
 * Two-step sign-in: switching it on needs a real code, every code works once,
 * recovery codes work once, and the second step is throttled per account.
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
    $this->totp = new Totp();
    $this->service = new TwoFactorService($this->users, $this->totp, new SecretBox('a-long-enough-application-key-for-the-test'));
    $this->throttle = new TwoFactorRateLimiter(new RateLimiter(ThrottleTestHelper::fakeCache()));
    $this->userId = UserFactory::new($this->users)->create();

    $this->viewer = new class() implements TemplateViewerInterface
    {
        public ?string $capturedTemplate = null;

        public ?array $capturedData = null;

        public function render(string $template, array $data = []): string
        {
            $this->capturedTemplate = $template;
            $this->capturedData = $data;

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

    $this->enable = function (): array {
        $secret = $this->service->beginSetup($this->userId);
        $codes = $this->service->confirmSetup($this->userId, $this->totp->codeAt($secret, $this->totp->stepAt(time())));

        return [$secret, $codes];
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

test('a started setup is not on until a code from the app is accepted', function () {
    $this->service->beginSetup($this->userId);

    expect($this->service->isEnabled($this->users->findById($this->userId)))->toBeFalse();
    expect($this->service->pendingSecret($this->userId))->not->toBeNull();
});

test('the secret is stored sealed, not as typed into the app', function () {
    $secret = $this->service->beginSetup($this->userId);

    $stored = $this->users->findById($this->userId)['two_factor_secret'];

    expect($stored)->not->toContain($secret);
    expect($this->service->pendingSecret($this->userId))->toBe($secret);
});

test('a wrong code does not turn it on and the right one does, with ten recovery codes', function () {
    $secret = $this->service->beginSetup($this->userId);

    expect($this->service->confirmSetup($this->userId, '000000'))->toBeNull();
    expect($this->service->isEnabled($this->users->findById($this->userId)))->toBeFalse();

    $codes = $this->service->confirmSetup($this->userId, $this->totp->codeAt($secret, $this->totp->stepAt(time())));

    expect($codes)->toHaveCount(10);
    expect($codes[0])->toMatch('/^[a-z0-9]{5}-[a-z0-9]{5}$/');
    expect($this->service->isEnabled($this->users->findById($this->userId)))->toBeTrue();
    expect($this->service->recoveryCodesLeft($this->users->findById($this->userId)))->toBe(10);
});

test('recovery codes are stored as hashes only', function () {
    [, $codes] = ($this->enable)();

    $stored = $this->users->findById($this->userId)['two_factor_recovery_codes'];

    expect($stored)->not->toContain(str_replace('-', '', $codes[0]));
    expect($stored)->not->toContain($codes[0]);
});

test('a code from the app works once', function () {
    [$secret] = ($this->enable)();
    $next = $this->totp->codeAt($secret, $this->totp->stepAt(time()) + 1);

    expect($this->service->verify($this->userId, $next))->toBeTrue();
    expect($this->service->verify($this->userId, $next))->toBeFalse();
});

test('the code that switched it on cannot sign in straight afterwards', function () {
    [$secret] = ($this->enable)();

    expect($this->service->verify($this->userId, $this->totp->codeAt($secret, $this->totp->stepAt(time()))))->toBeFalse();
});

test('a recovery code works once and is typed any way round', function () {
    [, $codes] = ($this->enable)();

    expect($this->service->verify($this->userId, strtoupper($codes[0])))->toBeTrue();
    expect($this->service->verify($this->userId, $codes[0]))->toBeFalse();
    expect($this->service->verify($this->userId, str_replace('-', ' ', $codes[1])))->toBeTrue();
    expect($this->service->recoveryCodesLeft($this->users->findById($this->userId)))->toBe(8);
});

test('new recovery codes replace the old ones', function () {
    [, $codes] = ($this->enable)();

    $fresh = $this->service->regenerateRecoveryCodes($this->userId);

    expect($this->service->verify($this->userId, $codes[0]))->toBeFalse();
    expect($this->service->verify($this->userId, $fresh[0]))->toBeTrue();
});

test('nothing verifies for an account without it', function () {
    expect($this->service->verify($this->userId, '123456'))->toBeFalse();
    expect($this->service->verify($this->userId, 'aaaaa-bbbbb'))->toBeFalse();
});

test('turning it off clears the secret and the codes', function () {
    ($this->enable)();

    $this->service->disable($this->userId);
    $row = $this->users->findById($this->userId);

    expect($this->service->isEnabled($row))->toBeFalse();
    expect($row['two_factor_secret'])->toBeNull();
    expect($row['two_factor_recovery_codes'])->toBeNull();
});

test('the control panel requirement follows the setting and the panel access rule', function () {
    $admin = ['id' => $this->userId];
    $roles = $this->users->getUserRoles($this->userId);
    $isPanelUser = (new SystemPolicy())->accessDashboard([
        'roles' => $roles,
        'permissions' => $this->users->getUserPermissions($this->userId),
    ]);

    $on = new TwoFactorPolicy($this->users, $this->service, new SystemPolicy(), true);
    $off = new TwoFactorPolicy($this->users, $this->service, new SystemPolicy(), false);

    expect($off->isRequiredFor($admin))->toBeFalse();
    expect($on->isRequiredFor($admin))->toBe($isPanelUser);

    $row = $this->users->findById($this->userId);
    expect($on->mustSetUp($row))->toBe($isPanelUser);

    ($this->enable)();
    expect($on->mustSetUp($this->users->findById($this->userId)))->toBeFalse();
});

describe('second sign-in step', function () {
    beforeEach(function () {
        $this->controller = new TwoFactorChallengeController($this->service, $this->throttle, $this->users);

        $this->post = function (string $code) {
            $request = makeRequest('/login/two-factor', 'POST', ['_token' => csrf()->getToken(), 'code' => $code]);
            setupController($this->controller, $request, $this->viewer);

            return callController($this->controller, 'submit', $request);
        };

        $this->park = function (?int $at = null): void {
            \Framework\Core\App::container()->get(\Framework\Session::class)->set(TwoFactorChallengeController::PENDING_KEY, [
                'user_id' => $this->userId,
                'return_to' => null,
                'at' => $at ?? time(),
            ]);
        };
    });

    test('without a parked password step the page sends you to log in', function () {
        $request = makeRequest('/login/two-factor', 'GET');
        setupController($this->controller, $request, $this->viewer);

        $response = callController($this->controller, 'show', $request);

        expect($response->getStatusCode())->toBe(302);
        expect($response->getHeader('Location'))->toContain('/login');
    });

    test('a parked step older than ten minutes is gone', function () {
        ($this->enable)();
        ($this->park)(time() - 601);

        $response = ($this->post)('123456');

        expect($response->getStatusCode())->toBe(302);
        expect(auth()->check())->toBeFalse();
    });

    test('a wrong code is refused and counted', function () {
        ($this->enable)();
        ($this->park)();

        $response = ($this->post)('000000');

        expect($response->getStatusCode())->toBe(422);
        expect(auth()->check())->toBeFalse();
    });

    test('five wrong codes lock the step even for the right code', function () {
        [$secret] = ($this->enable)();
        ($this->park)();

        for ($i = 0; $i < 5; $i++) {
            ($this->post)('00000'.$i);
        }
        $response = ($this->post)($this->totp->codeAt($secret, $this->totp->stepAt(time()) + 1));

        expect($response->getStatusCode())->toBe(429);
        expect(auth()->check())->toBeFalse();
    });

    test('a right code signs the account in and clears the parked step', function () {
        [$secret] = ($this->enable)();
        ($this->park)();

        $response = ($this->post)($this->totp->codeAt($secret, $this->totp->stepAt(time()) + 1));

        expect($response->getStatusCode())->toBe(302);
        expect(auth()->check())->toBeTrue();
        expect((int) auth()->user()['id'])->toBe($this->userId);
        expect(\Framework\Core\App::container()->get(\Framework\Session::class)->get(TwoFactorChallengeController::PENDING_KEY))->toBeNull();
    });
});
