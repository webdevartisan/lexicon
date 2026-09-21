<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\ReporterWarningMail;
use App\Models\ContentReportModel;
use App\Models\NotificationModel;
use App\Models\UserModel;
use DateTimeImmutable;
use DateTimeZone;
use Framework\Database;
use RuntimeException;

/**
 * What a moderator can do about someone who keeps filing unfounded reports:
 * warn them, then pause their reporting for a while (DSA Art. 23).
 *
 * The warning can go out on its own (warnIfOverLimit, behind a setting),
 * because it takes nothing away. A pause always needs a person: Art. 23(3) asks
 * for a case-by-case judgment, and a pause is refused until a warning exists.
 * Every step is an activity_log row on the user, which is also the history the
 * reporter page reads back.
 */
class ReporterStandingService
{
    public const WARNED = 'moderation.reporter_warned';

    public const PAUSED = 'moderation.reporting_paused';

    public const RESUMED = 'moderation.reporting_resumed';

    public const PAUSE_DAYS_MAX = 365;

    public const STANDARD_WARNING = 'Several of your recent reports were reviewed and found not to break our rules. '
        .'Please report only posts and comments that do. If this continues, your reports may be paused for a while.';

    public function __construct(
        private Database $database,
        private UserModel $users,
        private ContentReportModel $reports,
        private NotificationModel $notifications,
        private MailQueueService $mailQueue,
        private AuditService $audit,
        private ModerationSettings $settings,
    ) {}

    /**
     * Warn someone about their unfounded reports, in app and by email.
     *
     * @throws RuntimeException When the account does not exist
     */
    public function warn(int $userId, ?int $actorId, string $message, ?string $ip = null): void
    {
        $user = $this->account($userId);
        $unfounded = $this->reports->reporterSummary($userId)['unfounded'];
        $details = ['message' => $message, 'unfounded' => $unfounded];

        if ($actorId === null) {
            $details['rule'] = 'unfounded_limit';
        }

        $this->atomically(function () use ($user, $userId, $actorId, $message, $unfounded, $details, $ip): void {
            $this->notifications->create($userId, 'moderation.reporter_warning', [
                'unfounded' => $unfounded,
                'message' => $message,
            ]);

            $this->mailQueue->enqueue(
                new ReporterWarningMail((string) $user['email'], (string) $user['handle'], $unfounded, $message),
                'moderation_reporter',
                $userId
            );

            $this->audit->log($actorId, self::WARNED, 'user', $userId, $details, $ip);
        });
    }

    /**
     * Send the standard warning when someone has just reached the unfounded
     * limit. Called after a moderator marks reports unfounded.
     *
     * Only warns once per window, and never someone already paused, so a
     * person does not get a new warning for every report that follows.
     *
     * @return bool Whether a warning went out
     */
    public function warnIfOverLimit(int $userId): bool
    {
        if (!$this->settings->autoWarnReporters()) {
            return false;
        }

        $user = $this->users->findById($userId);
        $days = $this->settings->unfoundedWindowDays();

        if ($user === null || self::pausedUntil($user) !== null
            || $this->reports->unfoundedCountWithin($userId, $days) < $this->settings->unfoundedLimit()
            || $this->warnedWithin($userId, $days)) {
            return false;
        }

        $this->warn($userId, null, self::STANDARD_WARNING);

        return true;
    }

    /**
     * Refuse this person's reports for a number of days.
     *
     * @return string The UTC time the pause ends
     *
     * @throws RuntimeException When they were never warned, or the account does not exist
     */
    public function pause(int $userId, int $actorId, int $days, string $note, ?string $ip = null): string
    {
        $this->account($userId);

        if ($days < 1 || $days > self::PAUSE_DAYS_MAX) {
            throw new RuntimeException('A pause lasts from 1 to '.self::PAUSE_DAYS_MAX.' days.');
        }

        if ($this->history($userId, [self::WARNED]) === []) {
            throw new RuntimeException('Warn this person about their unfounded reports before pausing their reporting.');
        }

        $until = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify("+{$days} days")->format('Y-m-d H:i:s');

        $this->database->transaction(function () use ($userId, $actorId, $days, $note, $until, $ip): void {
            $this->users->setReportsPausedUntil($userId, $until);
            $this->notifications->create($userId, 'moderation.reporting_paused', [
                'until' => $until,
                'until_label' => local_datetime($until, 'M j, Y'),
            ]);
            $this->audit->log($actorId, self::PAUSED, 'user', $userId, [
                'days' => $days,
                'until' => $until,
                'note' => $note,
            ], $ip);
        });

        return $until;
    }

    /**
     * End a pause early.
     *
     * @throws RuntimeException When their reporting is not paused
     */
    public function resume(int $userId, int $actorId, string $note, ?string $ip = null): void
    {
        $user = $this->account($userId);

        if (self::pausedUntil($user) === null) {
            throw new RuntimeException('Their reporting is not paused.');
        }

        $this->database->transaction(function () use ($userId, $actorId, $note, $ip): void {
            $this->users->setReportsPausedUntil($userId, null);
            $this->audit->log($actorId, self::RESUMED, 'user', $userId, ['note' => $note], $ip);
        });
    }

    /**
     * Warnings, pauses and early ends for this person, newest first.
     *
     * @param  string[]  $actions  Which of the three to include
     * @return array<int, array{action: string, created_at: string, actor_handle: string|null, details: array<string, mixed>}>
     */
    public function history(int $userId, array $actions = [self::WARNED, self::PAUSED, self::RESUMED]): array
    {
        $marks = implode(', ', array_fill(0, count($actions), '?'));
        $rows = $this->database->query(
            "SELECT a.action, a.created_at, a.details, u.handle AS actor_handle
               FROM activity_log a
               LEFT JOIN users u ON u.id = a.user_id
              WHERE a.resource_type = 'user' AND a.resource_id = ? AND a.action IN ({$marks})
              ORDER BY a.id DESC",
            [$userId, ...$actions]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'action' => (string) $row['action'],
            'created_at' => (string) $row['created_at'],
            'actor_handle' => $row['actor_handle'],
            'details' => json_decode((string) $row['details'], true) ?? [],
        ], $rows);
    }

    /**
     * When this account's pause ends, or null when it can report.
     *
     * @param  array<string, mixed>  $user
     */
    public static function pausedUntil(array $user): ?string
    {
        $until = $user['reports_paused_until'] ?? null;

        if ($until === null || $until === '') {
            return null;
        }

        return strtotime((string) $until.' UTC') > time() ? (string) $until : null;
    }

    private function warnedWithin(int $userId, int $days): bool
    {
        return (bool) $this->database->query(
            "SELECT 1 FROM activity_log
              WHERE resource_type = 'user' AND resource_id = ? AND action = ?
                AND created_at >= NOW() - INTERVAL ? DAY
              LIMIT 1",
            [$userId, self::WARNED, $days]
        )->fetchColumn();
    }

    private function atomically(callable $work): void
    {
        $this->database->inTransaction() ? $work() : $this->database->transaction($work);
    }

    /**
     * @return array<string, mixed>
     */
    private function account(int $userId): array
    {
        return $this->users->findById($userId)
            ?? throw new RuntimeException('That account no longer exists.');
    }
}
