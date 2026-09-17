<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PendingErasureModel;
use App\Models\UserModel;
use RuntimeException;

/**
 * Defers a self-requested erasure behind a grace period.
 *
 * The account is deactivated the moment it's requested, the same way an
 * administrator suspension is: sessions end immediately and login is
 * refused. What doesn't happen immediately is AccountErasureService itself,
 * so a first offense nobody has reported yet still has a window to surface
 * before the content is gone. An administrator reactivating the account
 * during that window (the same toggle used to lift a suspension) reads as
 * "someone intervened" and cancels the erasure instead of merely delaying it.
 */
class AccountErasureSchedulerService
{
    public function __construct(
        private UserModel $users,
        private PendingErasureModel $pending,
        private AccountErasureService $erasure,
        private int $gracePeriodDays,
    ) {}

    /**
     * @throws RuntimeException When something still blocks erasure
     */
    public function schedule(int $userId, ?int $erasedBy, ?string $erasedByIp): void
    {
        if (!$this->erasure->canErase($userId)) {
            throw new RuntimeException("Account {$userId} still owns blogs with collaborators, has reported content, or is the last administrator.");
        }

        $scheduledFor = gmdate('Y-m-d H:i:s', strtotime("+{$this->gracePeriodDays} days"));

        $this->users->update($userId, ['is_active' => 0]);
        $this->pending->schedule($userId, $erasedBy, $erasedByIp, $scheduledFor);
    }

    /**
     * Run by privacy:process-due-erasures. Erases whatever is due and still
     * wants to be, skips what an administrator reactivated, and leaves
     * anything still blocked (a new report, say) for the next run.
     *
     * @return array{erased: int, cancelled: int, still_blocked: int}
     */
    public function processDue(): array
    {
        $counts = ['erased' => 0, 'cancelled' => 0, 'still_blocked' => 0];

        foreach ($this->pending->due() as $row) {
            $userId = (int) $row['user_id'];
            $user = $this->users->find($userId);

            if ($user === null || (int) $user['is_active'] === 1) {
                $this->pending->cancel($userId);
                $counts['cancelled']++;

                continue;
            }

            try {
                $this->erasure->erase(
                    $userId,
                    $row['erased_by'] !== null ? (int) $row['erased_by'] : null,
                    $row['erased_by_ip'] !== null ? (string) $row['erased_by_ip'] : null
                );
                $counts['erased']++;
            } catch (RuntimeException $e) {
                $counts['still_blocked']++;
            }
        }

        return $counts;
    }
}
