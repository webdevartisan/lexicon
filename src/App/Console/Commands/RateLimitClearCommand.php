<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RateLimitAdminService;

/**
 * CLI command to lift rate limiter locks held against an email or IP.
 *
 * Usage: php cli ratelimit:clear person@example.com 203.0.113.7
 */
class RateLimitClearCommand
{
    public function __construct(private RateLimitAdminService $rateLimits) {}

    /**
     * Unlock each email or IP passed on the command line.
     *
     * @param  array<int, string>  $arguments  Emails and IPs to unlock
     * @return int Exit code (0 = success, 1 = bad or missing input)
     */
    public function handle(array $arguments = []): int
    {
        if ($arguments === []) {
            echo "Usage: php cli ratelimit:clear <email-or-ip> [...]\n";

            return 1;
        }

        $status = 0;
        foreach ($arguments as $subject) {
            try {
                $removed = $this->rateLimits->unlock($subject, '127.0.0.1');
                echo "✓ {$subject}: {$removed} entries removed\n";
            } catch (\InvalidArgumentException $e) {
                echo "✗ {$subject}: {$e->getMessage()}\n";
                $status = 1;
            }
        }

        return $status;
    }
}
