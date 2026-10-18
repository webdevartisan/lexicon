<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UserModel;
use App\Services\Sessions\SessionRevoker;
use App\Services\TwoFactor\TwoFactorRateLimiter;
use App\Services\TwoFactor\TwoFactorService;

/**
 * Turns two-step sign-in off for an account that lost its authenticator and its recovery codes.
 *
 * Needs a shell on the server, which is the point: it is the way back in when
 * no administrator can still sign in to do it from the control panel.
 *
 * Usage: php cli two-factor:reset person@example.com
 */
class TwoFactorResetCommand
{
    public function __construct(
        private UserModel $users,
        private TwoFactorService $twoFactor,
        private TwoFactorRateLimiter $throttle,
        private SessionRevoker $revoker,
    ) {}

    /**
     * @param  array<int, string>  $arguments  The account's email address
     * @return int Exit code (0 = reset, 1 = bad input or unknown account)
     */
    public function handle(array $arguments = []): int
    {
        $email = trim((string) ($arguments[0] ?? ''));

        if ($email === '') {
            echo "Usage: php cli two-factor:reset <email>\n";

            return 1;
        }

        $user = $this->users->findByEmail($email);

        if (!$user) {
            echo "✗ No account uses {$email}\n";

            return 1;
        }

        $userId = (int) $user['id'];

        if (!$this->twoFactor->isEnabled($user) && empty($user['two_factor_secret'])) {
            echo "{$email} does not have two-step sign-in on.\n";

            return 0;
        }

        $this->twoFactor->disable($userId);
        $this->throttle->clear($userId);
        $this->revoker->endAll($userId, 'two_factor_reset');
        audit()->log(null, 'user.two_factor_reset', 'user', $userId, ['via' => 'cli'], '127.0.0.1');

        echo "✓ Two-step sign-in is off for {$email}. Every session of theirs was ended.\n";

        return 0;
    }
}
