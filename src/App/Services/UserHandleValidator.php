<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReservedHandleModel;
use App\Models\UserModel;

/**
 * Decides whether a users.handle value can be claimed.
 */
final class UserHandleValidator
{
    /**
     * Characters that read as a letter at a glance. Collapsing them, along with rn
     * to m, lets adm1n, 4dmin and adrnin be judged as admin.
     */
    private const READS_AS = ['0' => 'o', '1' => 'l', 'i' => 'l', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't'];

    /** @var array<string, string>|null Loaded once, since registration may try several candidates */
    private ?array $reservedMatchTypes = null;

    public function __construct(
        private UserModel $users,
        private ReservedHandleModel $reservedHandles
    ) {}

    /**
     * Whether a handle is neither reserved nor held by another user.
     *
     * @param  int|null  $ignoreUserId  User whose own handle should not block them
     */
    public function isAvailable(string $handle, ?int $ignoreUserId = null): bool
    {
        return !$this->isReserved($handle) && !$this->isTaken($handle, $ignoreUserId);
    }

    /**
     * Whether a handle is, or reads as, a reserved word.
     */
    public function isReserved(string $handle): bool
    {
        $candidate = $this->readsAs($handle);

        foreach ($this->reservedMatchTypes() as $word => $matchType) {
            // PHP turns a numeric string key into an int.
            $reserved = $this->readsAs((string) $word);

            $matches = match ($matchType) {
                'exact' => $candidate === $reserved,
                'contains' => str_contains($candidate, $reserved),
            };

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether another user already holds the handle.
     *
     * @param  int|null  $ignoreUserId  User whose own handle should not count
     */
    public function isTaken(string $handle, ?int $ignoreUserId = null): bool
    {
        return !$this->users->isHandleUnique($handle, $ignoreUserId);
    }

    private function readsAs(string $value): string
    {
        $collapsed = str_replace(['-', 'rn'], ['', 'm'], strtolower($value));

        return strtr($collapsed, self::READS_AS);
    }

    /**
     * @return array<string, string>
     */
    private function reservedMatchTypes(): array
    {
        return $this->reservedMatchTypes ??= $this->reservedHandles->matchTypesByHandle();
    }
}
