<?php

declare(strict_types=1);

namespace App\Services\Traffic;

use App\Models\TrafficSaltModel;
use Framework\Core\Request;

/**
 * The three ways a visitor is recognised, strongest first.
 *
 * - account: a signed-in reader, so one person on two devices is one visitor
 * - cookie: a guest who accepted analytics, by a random first-party cookie
 * - daily: everyone else, a hash of IP and user agent under today's salt
 *
 * The first two are keyed hashes, so the table alone can't be joined back to users or cookies.
 */
class VisitorIdentity
{
    public function __construct(
        private TrafficSaltModel $salts,
        private string $appKey,
    ) {}

    /**
     * @return string 16 raw bytes
     */
    public function forAccount(int $userId): string
    {
        return $this->keyed('account:'.$userId);
    }

    /**
     * @return string 16 raw bytes
     */
    public function forCookie(string $cookieId): string
    {
        return $this->keyed('cookie:'.$cookieId);
    }

    /**
     * @param  string  $utcDay  Y-m-d
     * @return string 16 raw bytes
     */
    public function daily(string $ip, string $userAgent, string $utcDay): string
    {
        return substr(hash('sha256', $this->salts->saltFor($utcDay).$ip."\n".$userAgent, true), 0, 16);
    }

    /**
     * The daily hash for the browser that sent this request.
     *
     * @return string 16 raw bytes
     */
    public function dailyFor(Request $request, \DateTimeImmutable $now): string
    {
        return $this->daily((string) $request->ip(), (string) $request->header('User-Agent', ''), $now->format('Y-m-d'));
    }

    private function keyed(string $value): string
    {
        return substr(hash_hmac('sha256', $value, $this->appKey, true), 0, 16);
    }
}
