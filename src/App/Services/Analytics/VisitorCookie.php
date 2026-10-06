<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Framework\Core\Request;
use Framework\HttpUtils;

/**
 * The first-party analytics cookie, a random id. Set only for visitors who accepted analytics.
 */
class VisitorCookie
{
    public function __construct(
        private string $name,
        private int $lifetimeDays,
    ) {}

    /**
     * The id the browser sent, if it is one this class could have issued.
     */
    public function read(Request $request): ?string
    {
        $value = $request->cookie[$this->name] ?? null;

        return is_string($value) && preg_match('/^[0-9a-f]{32}$/', $value) ? $value : null;
    }

    public function issue(): string
    {
        $id = bin2hex(random_bytes(16));
        $this->write($id, time() + $this->lifetimeDays * 86400);

        return $id;
    }

    public function forget(Request $request): void
    {
        if (isset($request->cookie[$this->name])) {
            $this->write('', time() - 3600);
        }
    }

    private function write(string $value, int $expires): void
    {
        setcookie($this->name, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => HttpUtils::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
